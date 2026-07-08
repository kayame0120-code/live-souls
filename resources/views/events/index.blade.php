<x-app-layout :hide-header="true" :hide-fab="true">
    <x-slot:pageHeader>
        <div class="page-header">
            <a href="{{ route('lots.index') }}" class="back">← 戻る</a>
            <h1>公演（共有マスタ）</h1>
        </div>
    </x-slot:pageHeader>

    <div class="link-row">
        <a href="{{ route('events.create') }}" class="primary">＋ 公演を登録</a>
        <a href="{{ route('events.import') }}">一括インポート</a>
    </div>

    @forelse($events as $event)
    <div class="ev-card">
        <span class="d">{{ $event->event_date->format('m.d') }}</span>
        <div class="t">
            {{ $event->event_name }}
            <div class="vn">{{ $event->venue?->name ?? '会場未設定' }}</div>
        </div>
        @if($event->canBeDeleted())
        <form method="POST" action="{{ route('events.destroy', $event) }}"
              onsubmit="return confirm('この公演を削除しますか？')">
            @csrf @method('DELETE')
            <button type="submit" class="copy-btn" style="color:#C7414F;">削除</button>
        </form>
        @else
        <span class="shared">参戦あり</span>
        @endif
    </div>
    @empty
    <div class="empty-state">
        まだ公演がありません。<br>
        <a href="{{ route('events.create') }}" class="btn btn-secondary btn-sm" style="margin-top:12px;">公演を登録する</a>
    </div>
    @endforelse
</x-app-layout>
