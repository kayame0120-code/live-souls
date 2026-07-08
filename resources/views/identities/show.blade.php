<x-app-layout :hide-header="true" :hide-fab="true">
    <x-slot:pageHeader>
        <div class="page-header">
            <a href="{{ route('identities.index') }}" class="back">← 戻る</a>
            <h1>名義詳細</h1>
            <div class="actions">
                <a href="{{ route('identities.edit', $fcMembership) }}" class="btn btn-secondary btn-sm">編集</a>
            </div>
        </div>
    </x-slot:pageHeader>

    <div class="m-card" style="margin-top:16px;">
        <span class="swatch" style="--oshi-color: {{ $fcMembership->oshi_color ?? '#C7414F' }}"></span>
        {{-- グループ名＝FC名（spec v1.1 §4） --}}
        <div class="m-club">{{ $fcMembership->group->name }}</div>
        <div class="m-kind">MEMBERSHIP</div>
        <div class="m-name">{{ $fcMembership->person->name }}@if($fcMembership->person->label)<small>{{ $fcMembership->person->label }}</small>@endif</div>
        @if($fcMembership->member_no)
        {{-- S4: 名義詳細では会員番号も伏字（コピーで取得） --}}
        <div class="m-no">No. ••••••••</div>
        @endif
    </div>

    <div class="detail-section">
        <div class="detail-label">担当アーティスト</div>
        <div class="detail-value">{{ $fcMembership->artist_name }}</div>
    </div>

    {{-- 機密情報（伏字+コピー） --}}
    @if($fcMembership->member_no)
    <div class="detail-section">
        <div class="detail-label">会員番号</div>
        <div style="display:flex; align-items:center; gap:8px;">
            <div class="detail-value">••••••••</div>
            <button class="copy-btn" data-copy="{{ $fcMembership->member_no }}" type="button">コピー</button>
        </div>
    </div>
    @endif

    @if($fcMembership->login_id)
    <div class="detail-section">
        <div class="detail-label">ログインID</div>
        <div style="display:flex; align-items:center; gap:8px;">
            <div class="detail-value">••••••••</div>
            <button class="copy-btn" data-copy="{{ $fcMembership->login_id }}" type="button">コピー</button>
        </div>
    </div>
    @endif

    @if($fcMembership->password)
    <div class="detail-section">
        <div class="detail-label">パスワード</div>
        <div style="display:flex; align-items:center; gap:8px;">
            <div class="detail-value">••••••••</div>
            <button class="copy-btn" data-copy="{{ $fcMembership->password }}" type="button">コピー</button>
        </div>
    </div>
    @endif

    {{-- 名義人情報 --}}
    @if($fcMembership->person->birth_date)
    <div class="detail-section">
        <div class="detail-label">生年月日</div>
        <div class="detail-value">
            {{ $fcMembership->person->birth_date->format('Y.m.d') }}
            @if($fcMembership->person->age() !== null)
            <span style="color:var(--color-ink-sub); font-size:12px;">（{{ $fcMembership->person->age() }}歳）</span>
            @endif
        </div>
    </div>
    @endif

    {{-- 更新期間（spec §5-6・joined_on null なら欄ごと非表示） --}}
    @if($fcMembership->joined_on)
    <div class="detail-section">
        <div class="detail-label">入会</div>
        <div class="detail-value">{{ $fcMembership->joined_on->format('Y.m') }}</div>
    </div>
    <div class="detail-section">
        <div class="detail-label">有効期限</div>
        <div class="detail-value" style="display:flex; align-items:center; gap:10px;">
            {{ $fcMembership->expiryDate()->format('Y.m.d') }}
            @if($fcMembership->isInRenewalWindow())
            <span class="badge renewal">更新受付中</span>
            @endif
        </div>
    </div>
    <div class="detail-section">
        <div class="detail-label">更新受付期間</div>
        <div class="detail-value">
            {{ $fcMembership->renewalWindowStart()->format('Y.m.d') }} 〜 {{ $fcMembership->expiryDate()->format('Y.m.d') }}
        </div>
    </div>
    @endif

    {{-- 当選率集計 --}}
    <div class="detail-section">
        <div class="detail-label">申込・当選実績</div>
        @php
            $appCount = $fcMembership->applicationCount();
            $winCount = $fcMembership->winCount();
            $winRate = $fcMembership->winRate();
        @endphp
        <div class="ledger" style="margin-top:8px;">
            <div><div class="v">{{ $appCount }}</div><div class="k">申込数</div></div>
            <div><div class="v">{{ $winCount }}</div><div class="k">当選数</div></div>
            <div><div class="v">{{ $winRate !== null ? round($winRate * 100) . '%' : '—' }}</div><div class="k">当選率</div></div>
        </div>
    </div>

    <div style="margin-top:24px;">
        <form method="POST" action="{{ route('identities.destroy', $fcMembership) }}"
              onsubmit="return confirm('この名義を削除しますか？')">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-secondary btn-sm" style="color:#C7414F;">名義を削除</button>
        </form>
    </div>
</x-app-layout>

@push('scripts')
<script>
document.querySelectorAll('.copy-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const text = this.dataset.copy;
        const done = () => {
            this.textContent = 'コピー済';
            this.classList.add('copied');
            setTimeout(() => { this.textContent = 'コピー'; this.classList.remove('copied'); }, 1500);
        };
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(done);
        } else {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.cssText = 'position:fixed;left:-9999px';
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            done();
        }
    });
});
</script>
@endpush
