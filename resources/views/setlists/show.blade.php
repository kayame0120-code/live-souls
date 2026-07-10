<x-app-layout :hide-fab="true" :hide-nav="true">
    <a href="{{ route('tours.show', $event->tour) }}" class="detail-back">‹ 公演詳細へ戻る</a>

    <div class="venue-hero">
        <div class="vh-name">セットリスト</div>
        <div class="vh-sub">{{ $event->displayName() }}{{ $event->venue ? ' ' . $event->venue->name : '' }} {{ $event->event_date->format('m.d') }}</div>
    </div>

    @if($setlist && $setlist->items->isNotEmpty())
    <div class="d-block">
        @foreach($setlist->items as $item)
        <div class="d-row" style="display:flex;align-items:center;gap:10px;">
            <span class="k" style="min-width:28px;text-align:center;font-weight:600;">{{ $item->display_label ?? str_pad($item->sort_order, 2, '0', STR_PAD_LEFT) }}</span>
            <span class="v" style="flex:1;">{{ $item->title }}</span>
            <form method="POST" action="{{ route('setlists.destroy-item', [$event, $item]) }}"
                  onsubmit="return confirm('この曲を削除しますか？')">
                @csrf @method('DELETE')
                <button type="submit" class="copy-btn" style="font-size:11px;padding:2px 8px;">削除</button>
            </form>
        </div>
        @endforeach
    </div>
    @else
    <div class="empty-state" style="padding:24px;">まだセットリストがありません</div>
    @endif

    <div class="d-block" style="margin-top:16px;">
        <div class="d-h">曲を追加</div>
        <form method="POST" action="{{ route('setlists.add-item', $event) }}" style="display:flex;gap:8px;align-items:flex-end;">
            @csrf
            <div style="flex:0 0 50px;">
                <input class="f-input" type="text" name="display_label" placeholder="EN" style="text-align:center;font-size:12px;">
            </div>
            <div style="flex:1;">
                <input class="f-input" type="text" name="title" placeholder="曲名" required>
            </div>
            <button type="submit" class="btn btn-primary" style="white-space:nowrap;">追加</button>
        </form>
    </div>
</x-app-layout>
