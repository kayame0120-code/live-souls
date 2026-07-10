<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TourDeadline extends Model
{
    protected $fillable = [
        'tour_id',
        'label',
        'application_deadline',
        'announce_date',
    ];

    protected function casts(): array
    {
        return [
            'application_deadline' => 'datetime',
            'announce_date' => 'date',
        ];
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function isDeadlinePassed(): bool
    {
        return $this->application_deadline !== null && now()->gte($this->application_deadline);
    }
}
