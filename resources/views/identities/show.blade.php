<x-app-layout :hide-fab="true">
    <div class="detail-topbar">
        <a href="{{ route('identities.index') }}" class="detail-back">‹ 名義へ戻る</a>
        <a href="{{ route('identities.edit', $fcMembership) }}" class="detail-edit">編集</a>
    </div>

    {{-- 担当色でカード全体を淡く塗る（mockup v1.3） --}}
    <div class="m-card" style="--oshi-color: {{ $fcMembership->oshi_color ?? '#C7414F' }}; margin-bottom:16px;">
        <div class="m-cardhead">
            <span class="m-swatch"></span>
            <div class="m-name">{{ $fcMembership->person->name }}@if($fcMembership->label)<small>{{ $fcMembership->label }}</small>@endif</div>
        </div>
        @if($fcMembership->member_no)
        {{-- S4: 名義詳細では会員番号も伏字（コピーで取得） --}}
        <div class="m-no">No. ••••••••</div>
        @endif
        <div class="m-foot">
            <span>
                {{ $fcMembership->group->name }}
                @if($fcMembership->joined_on)・入会 <b>{{ $fcMembership->joined_on->format('Y.m') }}</b>（{{ (int) floor($fcMembership->joined_on->diffInYears(now())) + 1 }}年目）@endif
            </span>
            @if($fcMembership->joined_on)
                @if($fcMembership->isInRenewalWindow())
                <span class="badge">更新受付中</span>
                @else
                <span class="badge ok">更新済</span>
                @endif
            @endif
        </div>
    </div>

    {{-- ログイン情報（コピー専用・平文はDOMに出さない） --}}
    <div class="d-block">
        <div class="d-h">ログイン情報（コピー専用・平文は表示しない）</div>
        <div class="copy-field">
            <span class="cf-k">担当</span>
            <span class="cf-v">{{ $fcMembership->artist_name }}</span>
            <span style="width:56px;"></span>
        </div>
        @if($fcMembership->member_no)
        <div class="copy-field">
            <span class="cf-k">会員番号</span>
            <span class="cf-v">••••••••</span>
            <button class="copy-btn" data-copy="{{ $fcMembership->member_no }}" type="button">コピー</button>
        </div>
        @endif
        @if($fcMembership->password)
        <div class="copy-field">
            <span class="cf-k">パスワード</span>
            <span class="cf-v">••••••••</span>
            <button class="copy-btn" data-copy="{{ $fcMembership->password }}" type="button">コピー</button>
        </div>
        @endif
        @if($fcMembership->login_id)
        <div class="copy-field">
            <span class="cf-k">ID</span>
            <span class="cf-v">••••••••</span>
            <button class="copy-btn" data-copy="{{ $fcMembership->login_id }}" type="button">コピー</button>
        </div>
        @endif
    </div>

    {{-- 個人情報（名義人）。住所・電話・メールは伏字＋コピー、誕生日は表示 --}}
    <div class="d-block">
        <div class="d-h">個人情報（名義人）</div>
        @if($fcMembership->person->address)
        <div class="copy-field">
            <span class="cf-k">住所</span>
            <span class="cf-v">••••••••••••</span>
            <button class="copy-btn" data-copy="{{ $fcMembership->person->address }}" type="button">コピー</button>
        </div>
        @endif
        @if($fcMembership->person->phone)
        <div class="copy-field">
            <span class="cf-k">電話番号</span>
            <span class="cf-v">••••••••••</span>
            <button class="copy-btn" data-copy="{{ $fcMembership->person->phone }}" type="button">コピー</button>
        </div>
        @endif
        @if($fcMembership->email)
        <div class="copy-field">
            <span class="cf-k">メールアドレス</span>
            <span class="cf-v">••••••••••••</span>
            <button class="copy-btn" data-copy="{{ $fcMembership->email }}" type="button">コピー</button>
        </div>
        @endif
        @if($fcMembership->person->birth_date)
        <div class="d-row" style="border-bottom:none;">
            <span class="k">誕生日</span>
            <span class="v mono">
                {{ \Carbon\Carbon::parse($fcMembership->person->birth_date)->format('Y.m.d') }}
                @if($fcMembership->person->age() !== null)<span style="color:var(--color-ink-sub);font-weight:400;">（{{ $fcMembership->person->age() }}歳）</span>@endif
            </span>
        </div>
        @endif
    </div>

    {{-- 会員期限（joined_on null なら欄ごと非表示・spec §5-6） --}}
    @if($fcMembership->joined_on)
    <div class="d-block">
        <div class="d-h">会員期限</div>
        <div class="d-row"><span class="k">有効期限</span><span class="v mono">{{ $fcMembership->expiryDate()->format('Y.m.d') }}</span></div>
        <div class="d-row">
            <span class="k">更新受付</span>
            <span class="v mono">{{ $fcMembership->renewalWindowStart()->format('Y.m.d') }} – {{ $fcMembership->expiryDate()->format('m.d') }}</span>
        </div>
    </div>
    @endif

    {{-- この名義の申込・当落（率は出さない・spec v1.2） --}}
    <div class="d-block">
        <div class="d-h">この名義の申込・当落</div>
        @php $applications = $fcMembership->applications(); @endphp
        @forelse($applications as $app)
        <div class="apply-row">
            <div class="ar-body">
                <div class="ar-name">{{ $app->event_name }}</div>
                <div class="ar-date">{{ optional($app->event_date)->format('Y.m.d') }}</div>
            </div>
            <span class="ar-st {{ $app->pivot->result }}">
                {{ ['won'=>'当選','lost'=>'落選','pending'=>'未発表'][$app->pivot->result] }}
            </span>
        </div>
        @empty
        <div style="font-size:12px; color:var(--color-ink-sub); padding:8px 0;">申込はまだありません</div>
        @endforelse
    </div>

    <a href="{{ route('identities.duplicate', $fcMembership) }}" class="btn btn-primary" style="display:block;text-align:center;margin-bottom:12px;text-decoration:none;">この名義を複製する</a>

    <form method="POST" action="{{ route('identities.destroy', $fcMembership) }}"
          onsubmit="return confirm('この名義を削除しますか？')">
        @csrf @method('DELETE')
        <button type="submit" class="f-danger">この名義を削除する</button>
    </form>

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
</x-app-layout>
