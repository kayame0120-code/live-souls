<x-app-layout>
    <a href="{{ route('lots.index') }}" class="detail-back">‹ グループ一覧へ戻る</a>

    <div class="venue-hero">
        <div class="vh-name">{{ $idolGroup?->name ?? '未分類' }}</div>
        <div class="vh-sub">申込中のツアー</div>
    </div>

    <div class="link-row">
        <a href="{{ route('lots.create') }}" class="primary">＋ 申込を登録</a>
    </div>

    <div class="sec-label">ツアー</div>
    @forelse($tours as $row)
    <a href="{{ route('lots.tour', $row->tour) }}" class="tour-card">
        <div class="tc-body">
            <div class="tc-name">{{ $row->tour->name }}</div>
            <div class="tc-sub">{{ $row->has_pending ? '当落待ちあり' : '発表済' }}</div>
        </div>
        <span class="tc-arrow">›</span>
    </a>
    @empty
    <div class="empty-state" style="padding:24px;">このグループにはまだ申込がありません</div>
    @endforelse
</x-app-layout>
