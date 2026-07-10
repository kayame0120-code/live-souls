<x-app-layout :hide-fab="true" :hide-nav="true">
    <a href="{{ route('tours.show', $tour) }}" class="detail-back">‹ 公演詳細へ戻る</a>

    <div class="venue-hero">
        <div class="vh-name">セットリスト</div>
        <div class="vh-sub">{{ $tour->name }}</div>
    </div>

    @forelse($tour->setlists as $setlist)
    <div class="d-block" style="margin-bottom:12px;">
        @if($setlist->label)
        <div class="d-h">{{ $setlist->label }}</div>
        @endif
        @foreach($setlist->items as $item)
        <div class="d-row" style="display:flex;align-items:center;gap:10px;">
            <span class="k" style="min-width:28px;text-align:center;font-weight:600;">{{ $item->display_label ?? str_pad($item->sort_order, 2, '0', STR_PAD_LEFT) }}</span>
            <span class="v" style="flex:1;">{{ $item->title }}</span>
            <form method="POST" action="{{ route('setlists.destroy-item', [$tour, $item]) }}"
                  onsubmit="return confirm('この曲を削除しますか？')">
                @csrf @method('DELETE')
                <button type="submit" class="copy-btn" style="font-size:11px;padding:2px 8px;">削除</button>
            </form>
        </div>
        @endforeach
    </div>
    @empty
    <div class="empty-state" style="padding:24px;">まだセットリストがありません</div>
    @endforelse

    <div class="d-block" style="margin-top:16px;">
        <div class="d-h">曲を追加</div>
        <form method="POST" action="{{ route('setlists.add-item', $tour) }}" style="display:flex;gap:8px;align-items:flex-end;">
            @csrf
            @if($tour->setlists->isEmpty())
            <input type="hidden" name="label" value="">
            @else
            <input type="hidden" name="setlist_id" value="{{ $tour->setlists->first()->id }}">
            @endif
            <div style="flex:0 0 50px;">
                <input class="f-input" type="text" name="display_label" placeholder="EN" style="text-align:center;font-size:12px;">
            </div>
            <div style="flex:1;">
                <input class="f-input" type="text" name="title" placeholder="曲名" required>
            </div>
            <button type="submit" class="btn btn-primary" style="white-space:nowrap;">追加</button>
        </form>
    </div>

    {{-- AI一括登録 --}}
    <div class="d-block" style="margin-top:16px;">
        <div class="d-h">セトリを貼って一括登録</div>
        @if(session('error'))
        <div class="warn">{{ session('error') }}</div>
        @endif

        @if(isset($aiItems))
        <form method="POST" action="{{ route('setlists.bulk-store', $tour) }}">
            @csrf
            @foreach($aiItems as $i => $item)
            <div class="imp-row" style="display:flex;gap:8px;align-items:center;padding:6px 0;">
                <input type="checkbox" name="items[{{ $i }}][include]" value="1" checked>
                <input class="f-input" type="text" name="items[{{ $i }}][display_label]" value="{{ $item['note'] ?? '' }}" style="width:50px;text-align:center;font-size:12px;" placeholder="EN">
                <input class="f-input" type="text" name="items[{{ $i }}][title]" value="{{ $item['title'] ?? '' }}" style="flex:1;">
            </div>
            @endforeach
            <button type="submit" class="btn btn-primary" style="margin-top:12px;">チェックした曲を登録</button>
        </form>
        @else
        <form method="POST" action="{{ route('setlists.ai-parse', $tour) }}">
            @csrf
            <textarea class="f-input" name="text" rows="5" placeholder="セットリストのテキストを貼り付け" required>{{ old('text') }}</textarea>
            <button type="submit" class="btn btn-primary" style="margin-top:8px;">AIで解析する</button>
        </form>
        @endif
    </div>
</x-app-layout>
