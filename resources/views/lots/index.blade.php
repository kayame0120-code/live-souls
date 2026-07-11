<x-app-layout>
    <div class="link-row">
        <a href="{{ route('lots.create') }}" class="primary">＋ 申込を登録</a>
        <a href="{{ route('events.index') }}">公演一覧</a>
    </div>

    <div class="sec-label">申込中の公演（ツアー）</div>
    @forelse($tours as $row)
    <a href="{{ route('lots.tour', $row->tour) }}" class="tour-card">
        <div class="tc-body">
            <div class="tc-name">{{ $row->tour->name }}</div>
            <div class="tc-sub">{{ $row->has_pending ? '当落待ちあり' : '発表済' }}</div>
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
