<?php

namespace App\Models;

use App\Models\Scopes\UserScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

#[ScopedBy(UserScope::class)]
class FcMembership extends Model
{
    protected $fillable = [
        'user_id',
        'person_id',
        'group_id',
        'artist_name',
        'label',
        'member_no',
        'login_id',
        'email',
        'password',
        'joined_on',
        'oshi_color',
        'group_member_id',
        'renewal_dismissed_at',
    ];

    protected function casts(): array
    {
        return [
            // member_no, login_id, password はE2E暗号化(クライアント側で暗号化済みの暗号文を保存)
            'email' => 'encrypted',
            'joined_on' => 'date',
            'renewal_dismissed_at' => 'datetime',
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
        return $this->belongsTo(IdolGroup::class, 'group_id');
    }

    public function groupMember(): BelongsTo
    {
        return $this->belongsTo(GroupMember::class);
    }

    public function attendances(): BelongsToMany
    {
        return $this->belongsToMany(Attendance::class, 'attendance_identity')
            ->withPivot(['result', 'ticket_count', 'id'])
            ->withTimestamps();
    }

    /**
     * この名義の各申込を当落ステータスつきで取得する（spec §5・当落一覧）。
     * v1.2で当選率などの割合計算は廃止。当落が分かる一覧のみ提供する。
     */
    public function applications()
    {
        return $this->attendances()
            ->with('event.tour')
            ->orderByEventDateDesc()
            ->get();
    }

    public function displayName(): string
    {
        $displayLabel = $this->label ?? $this->person->label;
        $suffix = $displayLabel ? "（{$displayLabel}）" : '';
        return $this->person->name . $suffix;
    }

    /*
    |--------------------------------------------------------------------------
    | 更新期間の自動計算（spec §5-6・全FC共通式・PHP側計算）
    |--------------------------------------------------------------------------
    | 有効期限 = joined_on の月の1日 + 1年 − 1日（以後毎年同月日）
    |          = 入会前月の月末日（毎年）。3月入会は2月末（うるう年考慮）
    | 更新受付 = 有効期限月の前月2日 〜 有効期限日
    */

    /** 次に到来する有効期限（今日を含む・joined_on null なら null） */
    public function expiryDate(?Carbon $today = null): ?CarbonImmutable
    {
        if (! $this->joined_on) {
            return null;
        }

        $today = CarbonImmutable::parse(($today ?? Carbon::today())->toDateString());

        // 入会月1日 + 1年 - 1日 → 有効期限の月日（入会前月の月末）
        $firstExpiry = CarbonImmutable::parse($this->joined_on->toDateString())
            ->startOfMonth()->addYear()->subDay();

        // 今年の同月の月末を候補とし、過ぎていれば翌年（月末はうるう年で日が動くため endOfMonth で再計算）
        $candidate = CarbonImmutable::create($today->year, $firstExpiry->month, 1)
            ->endOfMonth()->startOfDay();
        if ($candidate->lt($today)) {
            $candidate = CarbonImmutable::create($today->year + 1, $firstExpiry->month, 1)
                ->endOfMonth()->startOfDay();
        }

        return $candidate;
    }

    /** 更新受付開始日（有効期限月の前月2日） */
    public function renewalWindowStart(?Carbon $today = null): ?CarbonImmutable
    {
        $expiry = $this->expiryDate($today);

        return $expiry?->startOfMonth()->subMonth()->setDay(2);
    }

    /** 今日が更新受付期間内か（境界日=初日2日・期限日当日を含む） */
    public function isInRenewalWindow(?Carbon $today = null): bool
    {
        if (! $this->joined_on) {
            return false;
        }

        $todayDate = CarbonImmutable::parse(($today ?? Carbon::today())->toDateString());
        $expiry = $this->expiryDate($today);
        $start = $this->renewalWindowStart($today);

        return $todayDate->gte($start) && $todayDate->lte($expiry);
    }
}
