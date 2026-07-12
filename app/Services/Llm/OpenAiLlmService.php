<?php

namespace App\Services\Llm;

use App\Contracts\LlmService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiLlmService implements LlmService
{
    private ?string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('llm.openai.api_key');
        $this->model = config('llm.openai.model') ?? 'gpt-4o-mini';
    }

    public function parseEvents(?string $text = null, array $imagePaths = []): array
    {
        return $this->call(
            'コンサート・ライブの公演情報を抽出するアシスタントです。JSON形式でのみ応答します。',
            $this->buildEventUserContent($text, $imagePaths),
        );
    }

    public function parseSetlist(?string $text = null, array $imagePaths = []): array
    {
        return $this->call(
            'セットリスト（曲順リスト）を抽出するアシスタントです。JSON形式でのみ応答します。',
            $this->buildSetlistUserContent($text, $imagePaths),
        );
    }

    public function parseDeadlines(?string $text = null, array $imagePaths = []): array
    {
        return $this->call(
            'コンサート・ライブの申込締切・当落発表日を抽出するアシスタントです。JSON形式でのみ応答します。',
            $this->buildDeadlineUserContent($text, $imagePaths),
        );
    }

    private function call(string $system, string|array $userContent): array
    {
        if (empty($this->apiKey)) {
            throw new RuntimeException('OPENAI_API_KEY が設定されていません。.envを確認してください。');
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
            ])->timeout(60)->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $userContent],
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
            throw new RuntimeException('AIの応答をJSON形式として解析できませんでした。内容を変えて再度お試しください。');
        }

        return $decoded;
    }

    private function buildUserContent(string $instruction, ?string $text, array $imagePaths): string|array
    {
        if (empty($imagePaths)) {
            return $instruction . "\n\nテキスト:\n" . ($text ?? '');
        }

        $parts = [];
        $parts[] = ['type' => 'text', 'text' => $instruction];

        if ($text) {
            $parts[] = ['type' => 'text', 'text' => "テキスト:\n{$text}"];
        }

        foreach ($imagePaths as $path) {
            $mime = $this->detectMime($path);
            $base64 = base64_encode(file_get_contents($path));
            $parts[] = [
                'type' => 'image_url',
                'image_url' => ['url' => "data:{$mime};base64,{$base64}"],
            ];
        }

        return $parts;
    }

    private function detectMime(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }

    private function buildEventUserContent(?string $text, array $imagePaths): string|array
    {
        $instruction = <<<'MSG'
以下の入力（テキストや画像）からコンサート・ライブの公演情報と申込締切・当落発表日を抽出し、JSON形式で返してください。
event_labelは「昼公演」「夜公演」「追加公演」など公演区分のみ。会場名は入れないでください。
締切情報がテキストに含まれていれば deadlines に抽出。なければ空配列。

出力形式:
{"tour": "ツアー名(不明ならnull)", "events": [{"event_label": null, "event_date": "YYYY-MM-DD", "start_time": "HH:MM(不明ならnull)", "venue": "会場名"}], "deadlines": [{"label": "FC先行等(不明ならnull)", "application_deadline": "YYYY-MM-DD HH:MM(不明ならnull)", "announce_date": "YYYY-MM-DD(不明ならnull)"}]}
MSG;

        return $this->buildUserContent($instruction, $text, $imagePaths);
    }

    private function buildSetlistUserContent(?string $text, array $imagePaths): string|array
    {
        $instruction = <<<'MSG'
以下の入力（テキストや画像）からセットリスト（曲順リスト）を抽出し、JSON形式で返してください。

出力形式:
{"items": [{"order": 1, "title": "曲名", "note": "備考(アンコールなど、なければnull)"}]}
MSG;

        return $this->buildUserContent($instruction, $text, $imagePaths);
    }

    private function buildDeadlineUserContent(?string $text, array $imagePaths): string|array
    {
        $instruction = <<<'MSG'
以下の入力（テキストや画像）からコンサート・ライブの申込締切・当落発表日の情報を抽出し、JSON形式で返してください。

出力形式:
{"deadlines": [{"venue": "会場名", "event_date": "YYYY-MM-DD(なければnull)", "application_deadline": "YYYY-MM-DD HH:MM(なければnull)", "announce_date": "YYYY-MM-DD(なければnull)"}]}
MSG;

        return $this->buildUserContent($instruction, $text, $imagePaths);
    }
}
