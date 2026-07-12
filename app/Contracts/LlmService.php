<?php

namespace App\Contracts;

/**
 * LLM呼び出しの抽象化（spec v2.6 §4.1）。
 * 実装: OpenAiLlmService（唯一の実ドライバ）/ FakeLlmService（テスト用）。
 */
interface LlmService
{
    /**
     * テキストまたは画像から公演情報＋締切情報を一括抽出する。
     *
     * @param  string|null  $text  テキスト入力（テキストか画像の少なくとも一方が必須）
     * @param  array<string>  $imagePaths  画像ファイルパスの配列（最大5枚）
     * @return array{tour: string, events: array, deadlines: array}
     */
    public function parseEvents(?string $text = null, array $imagePaths = []): array;

    /**
     * テキストまたは画像からセットリスト情報をJSON配列として抽出する。
     *
     * @param  string|null  $text
     * @param  array<string>  $imagePaths
     * @return array{items: array<array{order: int, title: string, note: ?string}>}
     */
    public function parseSetlist(?string $text = null, array $imagePaths = []): array;

    /**
     * テキストまたは画像から当落締切情報をJSON配列として抽出する。
     *
     * @param  string|null  $text
     * @param  array<string>  $imagePaths
     * @return array{deadlines: array<array{venue: string, event_date: ?string, application_deadline: ?string, announce_date: ?string}>}
     */
    public function parseDeadlines(?string $text = null, array $imagePaths = []): array;
}
