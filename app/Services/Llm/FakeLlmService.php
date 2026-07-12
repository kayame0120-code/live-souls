<?php

namespace App\Services\Llm;

use App\Contracts\LlmService;

class FakeLlmService implements LlmService
{
    private array $eventsResponse = [];
    private array $setlistResponse = [];
    private array $deadlinesResponse = [];

    public function setEventsResponse(array $response): self
    {
        $this->eventsResponse = $response;
        return $this;
    }

    public function setSetlistResponse(array $response): self
    {
        $this->setlistResponse = $response;
        return $this;
    }

    public function setDeadlinesResponse(array $response): self
    {
        $this->deadlinesResponse = $response;
        return $this;
    }

    public function parseEvents(?string $text = null, array $imagePaths = []): array
    {
        return $this->eventsResponse ?: [
            'tour' => 'フェイクツアー',
            'events' => [
                ['event_label' => null, 'event_date' => '2026-08-15', 'start_time' => '17:00', 'venue' => 'フェイク会場'],
            ],
            'deadlines' => [],
        ];
    }

    public function parseSetlist(?string $text = null, array $imagePaths = []): array
    {
        return $this->setlistResponse ?: [
            'items' => [
                ['order' => 1, 'title' => 'フェイク曲1', 'note' => null],
                ['order' => 2, 'title' => 'フェイク曲2', 'note' => 'アンコール'],
            ],
        ];
    }

    public function parseDeadlines(?string $text = null, array $imagePaths = []): array
    {
        return $this->deadlinesResponse ?: [
            'deadlines' => [
                ['venue' => 'フェイク会場', 'event_date' => '2026-08-15', 'application_deadline' => '2026-07-20', 'announce_date' => '2026-07-28'],
            ],
        ];
    }
}
