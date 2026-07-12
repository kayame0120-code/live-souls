<?php

namespace App\Jobs;

use App\Contracts\LlmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * LLMによる非同期パース（spec v2.6 §4.1・キュー経由で実行）。
 * 公演・セットリスト・当落締切の3系統で共用。
 * 結果はCacheに一時保存し、ポーリングで取得後に確認画面へ。
 */
class ParseWithLlm implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 180;

    public function __construct(
        private string $cacheKey,
        private string $type,
        private ?string $text,
        private array $imagePaths,
        private ?int $userId = null,
    ) {}

    public function handle(LlmService $llm): void
    {
        try {
            $result = match ($this->type) {
                'events' => $llm->parseEvents($this->text, $this->imagePaths),
                'setlist' => $llm->parseSetlist($this->text, $this->imagePaths),
                'deadlines' => $llm->parseDeadlines($this->text, $this->imagePaths),
            };

            Cache::put($this->cacheKey, [
                'status' => 'completed',
                'result' => $result,
                'user_id' => $this->userId,
            ], now()->addHour());
        } catch (\Throwable $e) {
            Cache::put($this->cacheKey, [
                'status' => 'failed',
                'error' => $e->getMessage(),
                'user_id' => $this->userId,
            ], now()->addHour());
        }

        $this->cleanupImages();
    }

    public function failed(?\Throwable $e): void
    {
        Cache::put($this->cacheKey, [
            'status' => 'failed',
            'error' => 'AI解析がタイムアウトしました。再度お試しください。',
        ], now()->addHour());

        $this->cleanupImages();
    }

    private function cleanupImages(): void
    {
        foreach ($this->imagePaths as $path) {
            if (file_exists($path)) {
                @unlink($path);
            }
        }
    }
}
