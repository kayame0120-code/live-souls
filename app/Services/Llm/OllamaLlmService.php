<?php

namespace App\Services\Llm;

use App\Contracts\LlmService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OllamaLlmService implements LlmService
{
    private string $baseUrl;
    private string $model;

    public function __construct()
    {
        $this->baseUrl = config('llm.ollama.base_url');
        $this->model = config('llm.ollama.model');
    }

    public function parseEvents(string $text): array
    {
        $prompt = $this->buildEventPrompt($text);
        return $this->call($prompt);
    }

    public function parseSetlist(string $text): array
    {
        $prompt = $this->buildSetlistPrompt($text);
        return $this->call($prompt);
    }

    private function call(string $prompt): array
    {
        try {
            $response = Http::timeout(120)->post("{$this->baseUrl}/api/generate", [
                'model' => $this->model,
                'prompt' => $prompt,
                'format' => 'json',
                'stream' => false,
            ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException("Ollamaに接続できません（{$this->baseUrl}）。Ollamaが起動しているか確認してください。", 0, $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException("Ollama APIエラー: HTTP {$response->status()}");
        }

        $json = $response->json('response');
        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('OllamaのレスポンスをJSONとして解析できませんでした。');
        }

        return $decoded;
    }

    private function buildEventPrompt(string $text): string
    {
        return <<<PROMPT
以下のテキストからコンサート・ライブの公演情報を抽出し、JSON形式で返してください。

出力形式:
{"tour": "ツアー名", "events": [{"event_label": "公演ラベル(昼/夜など、なければnull)", "event_date": "YYYY-MM-DD", "start_time": "HH:MM(なければnull)", "venue": "会場名"}]}

テキスト:
{$text}
PROMPT;
    }

    private function buildSetlistPrompt(string $text): string
    {
        return <<<PROMPT
以下のテキストからセットリスト（曲順リスト）を抽出し、JSON形式で返してください。

出力形式:
{"items": [{"order": 1, "title": "曲名", "note": "備考(アンコールなど、なければnull)"}]}

テキスト:
{$text}
PROMPT;
    }
}
