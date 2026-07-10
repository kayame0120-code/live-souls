<x-app-layout :hide-fab="true">
    @if($memberships->isEmpty())
        <div class="empty-state">
            まだ名義がありません<br>
            <a href="{{ route('identities.create') }}" class="btn btn-secondary btn-sm" style="margin-top:12px;">名義を追加</a>
        </div>
    @else
        {{-- グループタブ束ね（idol_groupsベース・ユーザーが持つグループのみ） --}}
        <div class="fc-tabs">
            <a href="{{ route('identities.index') }}" class="fc-tab {{ !$currentGroupId ? 'on' : '' }}">すべて</a>
            @foreach($groups as $group)
            <a href="{{ route('identities.index', ['group' => $group->id]) }}"
               class="fc-tab {{ $currentGroupId == $group->id ? 'on' : '' }}">{{ $group->name }}</a>
            @endforeach
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
</x-app-layout>
