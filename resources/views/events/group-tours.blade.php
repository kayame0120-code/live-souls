<x-app-layout :hide-fab="true">
    <a href="{{ route('events.index') }}" class="detail-back">‹ グループ一覧へ戻る</a>

    <div class="venue-hero">
        <div class="vh-name">{{ $idolGroup?->name ?? '未分類' }}</div>
        <div class="vh-sub">{{ $tours->count() }}ツアー</div>
    </div>

    <div class="ev-actions">
        <a href="{{ route('events.import') }}" class="ev-import">一覧を貼って一括登録</a>
    </div>

    <div class="sec-label">ツアー</div>
    @forelse($tours as $tour)
    <a href="{{ route('tours.show', $tour) }}" class="tour-card">
        <div class="tc-body">
            <div class="tc-name">{{ $tour->name }}</div>
            <div class="tc-sub">{{ $tour->status_label }} ・ 全{{ $tour->events_count }}公演</div>
        </div>
        <span class="tc-arrow">›</span>
    </a>
    @empty
    <div class="empty-state" style="padding:24px;">このグループにはまだツアーがありません</div>
    @endforelse
</x-app-layout>
