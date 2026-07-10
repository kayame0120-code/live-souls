<?php

namespace App\Contracts;

/**
 * LLM呼び出しの抽象化（spec v2.0 §4.1）。
 * 実装: OllamaLlmService（ローカル）/ OpenAiLlmService（本番）。
 * LLM_DRIVER 環境変数で切替。
 */
interface LlmService
{
    /**
     * テキストからイベント情報をJSON配列として抽出する。
     *
     * @return array{tour: string, events: array<array{event_label: ?string, event_date: string, start_time: ?string, venue: string}>}
     */
    public function parseEvents(string $text): array;

    /**
     * テキストからセットリスト情報をJSON配列として抽出する。
     *
     * @return array{items: array<array{order: int, title: string, note: ?string}>}
     */
    public function parseSetlist(string $text): array;

    /**
     * テキストから当落締切情報をJSON配列として抽出する。
     *
     * @return array{deadlines: array<array{venue: string, event_date: ?string, application_deadline: ?string, announce_date: ?string}>}
     */
    public function parseDeadlines(string $text): array;
}
