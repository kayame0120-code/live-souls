<x-app-layout :hide-fab="true">
    <div class="ev-lead">
        公演情報はメンバー全員で持ち寄る共有台帳です。参戦・申込のときはここから公演を選ぶだけで日付・会場が入ります。
    </div>
    <div class="ev-actions">
        <a href="{{ route('events.import') }}" class="ev-import">一覧を貼って一括登録</a>
    </div>

    <div class="sec-label">公演（ツアー）</div>
    @forelse($tours as $tour)
    <a href="{{ route('tours.show', $tour) }}" class="tour-card">
        <div class="tc-body">
            <div class="tc-name">{{ $tour->name }}</div>
            <div class="tc-sub">{{ $tour->status_label }} ・ 全{{ $tour->events_count }}公演</div>
        </div>
        <span class="tc-arrow">›</span>
    </a>
    @empty
    <div class="empty-state">
        まだ公演がありません。<br>
        <a href="{{ route('events.import') }}" class="btn btn-secondary btn-sm" style="margin-top:12px;">一覧を貼って一括登録</a>
    </div>
    @endforelse
</x-app-layout>
