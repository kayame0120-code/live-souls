<x-app-layout>
    <div class="link-row">
        <a href="{{ route('lots.create') }}" class="primary">＋ 申込を登録</a>
    </div>

    <div class="sec-label">申込中のグループ</div>
    @forelse($toursByGroup as $row)
    <a href="{{ $row->idol_group ? route('lots.group-tours', $row->idol_group) : route('lots.uncategorized') }}" class="tour-card">
        <div class="tc-body">
            <div class="tc-name">{{ $row->idol_group?->name ?? '未分類' }}</div>
            <div class="tc-sub">{{ $row->tour_count }}ツアー{{ $row->has_pending ? ' ・ 当落待ちあり' : '' }}</div>
        </div>
        <span class="tc-arrow">›</span>
    </a>
    @empty
    <div class="empty-state">
        当落待ちの申込はありません<br>
        <a href="{{ route('lots.create') }}" class="btn btn-secondary btn-sm" style="margin-top:12px;">申込を登録する</a>
    </div>
    @endforelse
</x-app-layout>
