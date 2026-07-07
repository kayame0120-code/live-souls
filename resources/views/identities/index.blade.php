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
            <a href="{{ route('identities.show', $m) }}" class="m-card" style="--oshi-color: {{ $m->oshi_color ?? '#C7414F' }}">
                @if($m->club_name)
                <div class="m-club">{{ $m->club_name }}</div>
                @else
                <div class="m-club">{{ $m->artist_name }}</div>
                @endif
                <div class="m-kind">MEMBERSHIP</div>
                <div class="m-name">{{ $m->person->name }}@if($m->person->label)<small>{{ $m->person->label }}</small>@endif</div>
                @if($m->member_no)
                <div class="m-no">No. {{ $m->member_no }}</div>
                @endif
                <div class="m-foot">
                    @if($m->joined_month)
                    <span>入会 <b>{{ $m->joined_month }}</b></span>
                    @else
                    <span></span>
                    @endif
                    @if($m->renewal_cycle)
                    <span class="badge ok">{{ $m->renewal_cycle }}</span>
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
