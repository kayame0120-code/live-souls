<x-app-layout :hide-fab="true">
    @if($groups->isEmpty())
        <div class="empty-state">
            まずグループを作成しましょう<br>
            <a href="{{ route('identity-groups.index') }}" class="btn btn-secondary btn-sm" style="margin-top:12px;">グループを作成</a>
        </div>
    @else
        <div class="group-tabs">
            <a href="{{ route('identities.index') }}" class="chip {{ !$currentGroupId ? 'on' : '' }}">すべて</a>
            @foreach($groups as $group)
            <a href="{{ route('identities.index', ['group' => $group->id]) }}"
               class="chip {{ $currentGroupId == $group->id ? 'on' : '' }}">{{ $group->name }}</a>
            @endforeach
            <a href="{{ route('identity-groups.index') }}" class="chip" style="border-style:dashed;">管理</a>
        </div>

        @php
            $filtered = $currentGroupId
                ? $groups->firstWhere('id', $currentGroupId)?->fcMemberships ?? collect()
                : $groups->flatMap->fcMemberships;
        @endphp

        @forelse($filtered as $m)
            <a href="{{ route('identities.show', $m) }}" class="m-card">
                {{-- 担当色は丸スウォッチ（バー表現は廃止 / spec v1.1 §3） --}}
                <span class="swatch" style="--oshi-color: {{ $m->oshi_color ?? '#C7414F' }}"></span>
                {{-- グループ名＝FC名（spec v1.1 §4） --}}
                <div class="m-club">{{ $m->group->name }}</div>
                <div class="m-kind">MEMBERSHIP</div>
                <div class="m-name">{{ $m->person->name }}@if($m->person->label)<small>{{ $m->person->label }}</small>@endif</div>
                @if($m->member_no)
                <div class="m-no">No. {{ $m->member_no }}</div>
                @endif
                <div class="m-foot">
                    @if($m->joined_on)
                    <span>入会 <b>{{ $m->joined_on->format('Y.m') }}</b></span>
                    @else
                    <span></span>
                    @endif
                    @if($m->isInRenewalWindow())
                    <span class="badge renewal">更新受付中</span>
                    @endif
                </div>
            </a>
        @empty
            <div class="empty-state">
                このグループにはまだ名義がありません<br>
                <a href="{{ route('identities.create') }}" class="btn btn-secondary btn-sm" style="margin-top:12px;">名義を追加</a>
            </div>
        @endforelse

        @if($filtered->isNotEmpty())
        <p class="privacy-note">名義情報は暗号化してサーバーに保存されます。他のユーザーからは見えません。</p>
        @endif

        <div style="text-align:center; margin-top:16px;">
            <a href="{{ route('identities.create') }}" class="btn btn-secondary btn-sm">＋ 名義を追加</a>
        </div>
    @endif
</x-app-layout>
