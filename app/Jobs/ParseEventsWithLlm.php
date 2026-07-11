<?php

namespace App\Jobs;

use App\Contracts\LlmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * LLMによる公演情報の非同期パース（spec v2.0 §4.1）。
 * 結果はCacheに一時保存し、確認画面で取得する。
 * 人間確認テーブルの設計確定後にCache→DB保存へ移行予定（QV20-4）。
 */
class ParseEventsWithLlm implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 180;

    public function __construct(
        private string $cacheKey,
        private string $inputText,
    ) {}

    public function handle(LlmService $llm): void
    {
        try {
            $result = $llm->parseEvents($this->inputText, []);

            Cache::put($this->cacheKey, [
                'status' => 'completed',
                'result' => $result,
            ], now()->addHour());
        } catch (\Throwable $e) {
            Log::error('LLM公演パース失敗', ['error' => $e->getMessage()]);

            Cache::put($this->cacheKey, [
                'status' => 'failed',
                'error' => $e->getMessage(),
            ], now()->addHour());
        }
    }
}
