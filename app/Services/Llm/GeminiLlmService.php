<?php

namespace App\Services\Llm;

use App\Contracts\LlmService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiLlmService implements LlmService
{
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('llm.gemini.api_key');
        $this->model = config('llm.gemini.model');

        if (empty($this->apiKey)) {
            throw new RuntimeException('GEMINI_API_KEY が設定されていません。');
        }
    }

    public function parseEvents(string $text): array
    {
        return $this->call(<<<PROMPT
以下のテキストからコンサート・ライブの公演情報を抽出し、JSON形式で返してください。
複数のサイトや形式が混在していても、すべての公演を漏れなく抽出してください。
event_labelは「昼公演」「夜公演」「追加公演」など公演の区分のみ。会場名は入れないでください。

出力形式（これ以外のテキストを含めないこと）:
{"tour": "ツアー名(不明ならnull)", "events": [{"event_label": null, "event_date": "YYYY-MM-DD", "start_time": "HH:MM(不明ならnull)", "venue": "会場名"}]}

テキスト:
{$text}
PROMPT);
    }

    public function parseSetlist(string $text): array
    {
        return $this->call(<<<PROMPT
以下のテキストからセットリスト（曲順リスト）を抽出し、JSON形式で返してください。
MCやトーク区間は含めず、楽曲のみを抽出してください。

出力形式（これ以外のテキストを含めないこと）:
{"items": [{"order": 1, "title": "曲名", "note": "アンコール等の備考(なければnull)"}]}

テキスト:
{$text}
PROMPT);
    }

    public function parseDeadlines(string $text): array
    {
        return $this->call(<<<PROMPT
以下のテキストから申込締切・当落発表日の情報を抽出し、JSON形式で返してください。

出力形式（これ以外のテキストを含めないこと）:
{"deadlines": [{"venue": "会場名", "event_date": "YYYY-MM-DD(不明ならnull)", "application_deadline": "YYYY-MM-DD HH:MM(不明ならnull)", "announce_date": "YYYY-MM-DD(不明ならnull)"}]}

テキスト:
{$text}
PROMPT);
    }

    private function call(string $prompt): array
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

        try {
            $response = Http::timeout(60)->post($url, [
                'contents' => [
                    ['parts' => [['text' => $prompt]]],
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                ],
            ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Gemini APIに接続できません。', 0, $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException("Gemini APIエラー: HTTP {$response->status()} — {$response->body()}");
        }

        $text = $response->json('candidates.0.content.parts.0.text');
        $decoded = json_decode($text, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('GeminiのレスポンスをJSONとして解析できませんでした。');
        }

        return $decoded;
    }
}
