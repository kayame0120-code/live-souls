<?php

namespace App\Models;

use App\Models\Scopes\UserScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

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

    public const E2E_PREFIX = 'e2e:';

    protected function casts(): array
    {
        return [
            'name' => 'encrypted',
            'birth_date' => 'encrypted',
        ];
    }

    private static function readProtectedField(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }
        if (str_starts_with($value, self::E2E_PREFIX)) {
            return $value;
        }
        if (str_starts_with($value, 'eyJ')) {
            try {
                return Crypt::decryptString($value);
            } catch (\Throwable) {
                return $value;
            }
        }
        return $value;
    }

    public static function isE2eValue(?string $value): bool
    {
        return $value !== null && str_starts_with($value, self::E2E_PREFIX);
    }

    protected function phone(): Attribute
    {
        return Attribute::make(get: fn ($value) => self::readProtectedField($value));
    }

    protected function address(): Attribute
    {
        return Attribute::make(get: fn ($value) => self::readProtectedField($value));
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
