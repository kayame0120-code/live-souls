<x-app-layout :hide-header="true" :hide-fab="true">
    <x-slot:pageHeader>
        <div class="page-header">
            <a href="{{ route('identities.index') }}" class="back">← 戻る</a>
            <h1>グループ管理</h1>
        </div>
    </x-slot:pageHeader>

    @forelse($groups as $group)
    <div class="card" style="display:flex; align-items:center; gap:12px;">
        <div style="flex:1;">
            <div style="font-size:14px; font-weight:700;">{{ $group->name }}</div>
            <div style="font-size:11px; color:var(--color-ink-sub);">{{ $group->fc_memberships_count }}件の名義</div>
        </div>
        <a href="{{ route('identity-groups.edit', $group) }}" class="btn btn-secondary btn-sm">改名</a>
        @if($group->fc_memberships_count === 0)
        <form method="POST" action="{{ route('identity-groups.destroy', $group) }}"
              onsubmit="return confirm('「{{ $group->name }}」を削除しますか？')">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-secondary btn-sm" style="color:#C7414F;">削除</button>
        </form>
        @else
        <span style="font-size:10px; color:var(--color-ink-sub);">削除不可</span>
        @endif
    </div>
    @empty
    <div class="empty-state">グループがありません</div>
    @endforelse

    <div style="text-align:center; margin-top:20px;">
        <a href="{{ route('identity-groups.create') }}" class="btn btn-primary" style="max-width:280px;">＋ グループを追加</a>
    </div>
</x-app-layout>
