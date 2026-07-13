<?php

namespace App\Models;

use App\Models\Scopes\UserScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ScopedBy(UserScope::class)]
class Person extends Model
{
    protected $table = 'persons';

    protected $fillable = [
        'user_id',
        'name',
        'birth_date',
        'phone',
        'address',
        'label',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'encrypted',
            'birth_date' => 'encrypted',
            'phone' => 'encrypted',
            'address' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fcMemberships(): HasMany
    {
        return $this->hasMany(FcMembership::class);
    }

    public function age(): ?int
    {
        if (! $this->birth_date) {
            return null;
        }

        return \Carbon\Carbon::parse($this->birth_date)->age;
    }
}
