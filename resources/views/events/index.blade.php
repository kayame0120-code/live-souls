<x-app-layout :hide-fab="true">
    <div class="ev-lead">
        公演情報はメンバー全員で持ち寄る共有台帳です。参戦・申込のときはここから公演を選ぶだけで日付・会場が入ります。
    </div>
    <div class="ev-actions">
        <a href="{{ route('events.import') }}" class="ev-import">一覧を貼って一括登録</a>
    </div>

    <div class="sec-label">グループ</div>
    @forelse($groups as $group)
    <a href="{{ route('events.group-tours', $group) }}" class="tour-card">
        <div class="tc-body">
            <div class="tc-name">{{ $group->name }}</div>
            <div class="tc-sub">{{ $group->tours_count }}ツアー</div>
        </div>
        <span class="tc-arrow">›</span>
    </a>
    @empty
    @endforelse

    @if($hasUncategorized)
    <a href="{{ route('events.uncategorized') }}" class="tour-card">
        <div class="tc-body">
            <div class="tc-name">未分類</div>
            <div class="tc-sub">グループ未設定のツアー</div>
        </div>
        <span class="tc-arrow">›</span>
    </a>
    @endif

    @if($groups->isEmpty() && !$hasUncategorized)
    <div class="empty-state">
        まだ公演がありません。<br>
        <a href="{{ route('events.import') }}" class="btn btn-secondary btn-sm" style="margin-top:12px;">一覧を貼って一括登録</a>
    </div>
    @endif
</x-app-layout>
