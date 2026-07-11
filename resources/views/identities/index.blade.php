<x-app-layout :hide-fab="true">
    @if($memberships->isEmpty() && $myGroups->isEmpty())
        <div class="empty-state">
            まだ名義がありません<br>
            <a href="{{ route('identities.create') }}" class="btn btn-secondary btn-sm" style="margin-top:12px;">名義を追加</a>
        </div>
    @else
        {{-- グループタブ + 右端に追加ボタン --}}
        <div style="display:flex;align-items:center;gap:0;">
            <div class="fc-tabs" style="flex:1;min-width:0;">
                <a href="{{ route('identities.index') }}" class="fc-tab {{ !$currentGroupId ? 'on' : '' }}">すべて</a>
                @foreach($myGroups as $ig)
                <a href="{{ route('identities.index', ['group' => $ig->id]) }}"
                   class="fc-tab {{ $currentGroupId == $ig->id ? 'on' : '' }}">{{ $ig->name }}</a>
                @endforeach
            </div>
            <button type="button" class="fc-tab add" id="toggle-add-group" style="flex:none;">＋</button>
        </div>

        {{-- グループ追加フォーム（トグル） --}}
        <div id="add-group-form" style="display:none;padding:8px 0;">
            <form method="POST" action="{{ route('idol-groups.store') }}" style="display:flex;gap:8px;align-items:center;">
                @csrf
                <input class="f-input" type="text" name="name" placeholder="新しいグループ名" required style="flex:1;font-size:12px;">
                <button type="submit" class="btn btn-primary" style="white-space:nowrap;font-size:12px;padding:6px 12px;">追加</button>
            </form>
        </div>

        @php
            $filtered = $currentGroupId
                ? $memberships->where('group_id', (int) $currentGroupId)
                : $memberships;
        @endphp

        @forelse($filtered as $m)
            <a href="{{ route('identities.show', $m) }}" class="m-card tappable" style="--oshi-color: {{ $m->oshi_color ?? '#C7414F' }}">
                <div class="m-cardhead">
                    <span class="m-swatch"></span>
                    <div class="m-name">{{ $m->person->name }}@if($m->person->label)<small>{{ $m->person->label }}</small>@endif</div>
                </div>
                @if($m->member_no)
                <div class="m-no">No. {{ $m->member_no }}</div>
                @endif
                <div class="m-foot">
                    <span>
                        {{ $m->group?->name ?? '' }}
                        @if($m->joined_on)
                        ・入会 <b>{{ $m->joined_on->format('Y.m') }}</b>（{{ (int) floor($m->joined_on->diffInYears(now())) + 1 }}年目）
                        @endif
                    </span>
                    @if($m->joined_on)
                        @if($m->isInRenewalWindow())
                        <span class="badge">更新受付中</span>
                        @else
                        <span class="badge ok">更新済</span>
                        @endif
                    @endif
                </div>
            </a>
        @empty
            <div class="empty-state">
                このグループにはまだ名義がありません
            </div>
        @endforelse

        <a href="{{ route('identities.create') }}" class="m-add">＋ 名義を追加</a>

        @if($filtered->isNotEmpty())
        <p class="privacy-note">名義情報は暗号化してサーバーに保存されます。他のユーザーからは見えません。</p>
        @endif
    @endif

<script>
document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('toggle-add-group');
    var formWrap = document.getElementById('add-group-form');
    if (btn && formWrap) {
        btn.addEventListener('click', function () {
            formWrap.style.display = formWrap.style.display === 'none' ? '' : 'none';
            if (formWrap.style.display !== 'none') formWrap.querySelector('input[name="name"]').focus();
        });
    }
});
</script>
</x-app-layout>
