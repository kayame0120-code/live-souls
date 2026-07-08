<x-app-layout :hide-fab="true">
    @if($groups->isEmpty())
        <div class="empty-state">
            まずグループを作成しましょう<br>
            <a href="{{ route('identity-groups.index') }}" class="btn btn-secondary btn-sm" style="margin-top:12px;">グループを作成</a>
        </div>
    @else
        {{-- FC（グループ）タブ束ね（mockup .fc-tabs） --}}
        <div class="fc-tabs">
            <a href="{{ route('identities.index') }}" class="fc-tab {{ !$currentGroupId ? 'on' : '' }}">すべて</a>
            @foreach($groups as $group)
            <a href="{{ route('identities.index', ['group' => $group->id]) }}"
               class="fc-tab {{ $currentGroupId == $group->id ? 'on' : '' }}">{{ $group->name }}</a>
            @endforeach
            <a href="{{ route('identity-groups.index') }}" class="fc-tab add">＋ FC管理</a>
        </div>

        @php
            $filtered = $currentGroupId
                ? $groups->firstWhere('id', $currentGroupId)?->fcMemberships ?? collect()
                : $groups->flatMap->fcMemberships;
        @endphp

        @forelse($filtered as $m)
            {{-- 担当色でカード全体を淡く塗る（mockup v1.3） --}}
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
                        {{ $m->group->name }}
                        @if($m->joined_on)・入会 <b>{{ $m->joined_on->format('Y.m') }}</b>@endif
                    </span>
                    @if($m->isInRenewalWindow())
                    <span class="badge">更新受付中</span>
                    @endif
                </div>
            </a>
        @empty
            <div class="empty-state">
                このグループにはまだ名義がありません
            </div>
        @endforelse

        {{-- 名義追加（mockup .m-add） --}}
        <a href="{{ route('identities.create') }}" class="m-add">＋ 名義を追加</a>

        @if($filtered->isNotEmpty())
        <p class="privacy-note">名義情報は暗号化してサーバーに保存されます。他のユーザーからは見えません。</p>
        @endif
    @endif
</x-app-layout>
