<?php

namespace App\Models;

use App\Models\Scopes\UserScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[ScopedBy(UserScope::class)]
class FcMembership extends Model
{
    protected $fillable = [
        'user_id',
        'person_id',
        'group_id',
        'artist_name',
        'club_name',
        'member_no',
        'login_id',
        'password',
        'joined_month',
        'renewal_cycle',
        'oshi_color',
    ];

    protected function casts(): array
    {
        return [
            'login_id' => 'encrypted',
            'password' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(IdentityGroup::class, 'group_id');
    }

    public function attendances(): BelongsToMany
    {
        return $this->belongsToMany(Attendance::class, 'attendance_identity')
            ->withPivot(['result', 'ticket_count', 'id'])
            ->withTimestamps();
    }

    public function applicationCount(): int
    {
        return $this->attendances()->count();
    }

    public function winCount(): int
    {
        return $this->attendances()->wherePivot('result', 'won')->count();
    }

    public function winRate(): ?float
    {
        $total = $this->applicationCount();
        if ($total === 0) {
            return null;
        }
        return $this->winCount() / $total;
    }

    public function displayName(): string
    {
        $label = $this->person->label ? "（{$this->person->label}）" : '';
        return $this->person->name . $label;
    }
}
