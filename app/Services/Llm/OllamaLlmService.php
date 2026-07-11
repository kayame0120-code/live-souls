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

    public function parseDeadlines(string $text): array
    {
        $prompt = $this->buildDeadlinePrompt($text);
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
Extract concert info AND application deadlines as JSON. Output ONLY valid JSON, no other text.
event_label is ONLY for 昼公演/夜公演/追加公演 etc. Do NOT put venue name in event_label. Set null if none.
If deadline/announce info found, include in deadlines array. If not found, use empty array.
{"tour":"tour name or null","events":[{"event_label":null,"event_date":"YYYY-MM-DD","start_time":"HH:MM or null","venue":"venue name"}],"deadlines":[{"label":"FC先行 or null","application_deadline":"YYYY-MM-DD HH:MM or null","announce_date":"YYYY-MM-DD or null"}]}

Input:
{$text}
PROMPT;
    }

    private function buildSetlistPrompt(string $text): string
    {
        return <<<PROMPT
Extract setlist as JSON. Output ONLY valid JSON, no other text.
{"items":[{"order":1,"title":"song title","note":"encore or null"}]}

Input:
{$text}
PROMPT;
    }

    private function buildDeadlinePrompt(string $text): string
    {
        return <<<PROMPT
Extract deadline info as JSON. Output ONLY valid JSON, no other text.
{"deadlines":[{"venue":"venue","event_date":"YYYY-MM-DD or null","application_deadline":"YYYY-MM-DD HH:MM or null","announce_date":"YYYY-MM-DD or null"}]}

Input:
{$text}
PROMPT;
    }
}
