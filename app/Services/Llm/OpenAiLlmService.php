<?php

namespace App\Services\Llm;

use App\Contracts\LlmService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiLlmService implements LlmService
{
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('llm.openai.api_key');
        $this->model = config('llm.openai.model');

        if (empty($this->apiKey)) {
            throw new RuntimeException('OPENAI_API_KEY が設定されていません。');
        }
    }

    public function parseEvents(string $text): array
    {
        return $this->call(
            'コンサート・ライブの公演情報を抽出するアシスタントです。JSON形式でのみ応答します。',
            $this->buildEventUserMessage($text),
        );
    }

    public function parseSetlist(string $text): array
    {
        return $this->call(
            'セットリスト（曲順リスト）を抽出するアシスタントです。JSON形式でのみ応答します。',
            $this->buildSetlistUserMessage($text),
        );
    }

    private function call(string $system, string $user): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
            ])->timeout(60)->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
            ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException('OpenAI APIに接続できません。', 0, $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException("OpenAI APIエラー: HTTP {$response->status()} — {$response->body()}");
        }

        $content = $response->json('choices.0.message.content');
        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('OpenAIのレスポンスをJSONとして解析できませんでした。');
        }

        return $decoded;
    }

    private function buildEventUserMessage(string $text): string
    {
        return <<<MSG
以下のテキストからコンサート・ライブの公演情報を抽出し、JSON形式で返してください。

出力形式:
{"tour": "ツアー名", "events": [{"event_label": "公演ラベル(昼/夜など、なければnull)", "event_date": "YYYY-MM-DD", "start_time": "HH:MM(なければnull)", "venue": "会場名"}]}

テキスト:
{$text}
MSG;
    }

    private function buildSetlistUserMessage(string $text): string
    {
        return <<<MSG
以下のテキストからセットリスト（曲順リスト）を抽出し、JSON形式で返してください。

出力形式:
{"items": [{"order": 1, "title": "曲名", "note": "備考(アンコールなど、なければnull)"}]}

テキスト:
{$text}
MSG;
    }
}
