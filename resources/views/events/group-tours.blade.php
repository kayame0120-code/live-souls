<x-app-layout :hide-fab="true" :hide-nav="true">
    <a href="{{ route('events.index') }}" class="detail-back">‹ グループ一覧へ戻る</a>

    <div class="venue-hero">
        <div class="vh-name">{{ $idolGroup?->name ?? '未分類' }}</div>
        <div class="vh-sub">{{ $tours->count() }}ツアー</div>
    </div>

    <div class="ev-actions">
        <a href="{{ route('events.import') }}" class="ev-import">一覧を貼って一括登録</a>
    </div>

    @php $allGroups = \App\Models\IdolGroup::orderBy('name')->get(); @endphp

    <div class="sec-label">ツアー</div>
    @forelse($tours as $tour)
    <div class="d-block" style="margin-bottom:6px;padding:10px 14px;">
        <a href="{{ route('tours.show', $tour) }}" style="text-decoration:none;color:inherit;display:block;">
            <div style="font-size:14px;font-weight:600;">{{ $tour->name }}</div>
            <div style="font-size:12px;color:var(--color-ink-sub);">
                @if($tour->status_label === '開催中')
                <span style="font-weight:700;color:#E65100;">開催中</span>
                @elseif($tour->status_label === '終了')
                <span style="font-weight:700;">終了</span>
                @else
                <span>{{ $tour->status_label }}</span>
                @endif
                ・ 全{{ $tour->events_count }}公演
            </div>
        </a>
        <div style="display:flex;gap:6px;margin-top:8px;flex-wrap:wrap;">
            {{-- グループ移動 --}}
            <form method="POST" action="{{ route('tours.update-group', $tour) }}" style="display:flex;gap:4px;align-items:center;">
                @csrf
                <select name="idol_group_id" class="f-input" style="font-size:11px;padding:2px 6px;min-width:0;flex:1;">
                    <option value="">未分類</option>
                    @foreach($allGroups as $ig)
                    <option value="{{ $ig->id }}" {{ $tour->idol_group_id == $ig->id ? 'selected' : '' }}>{{ $ig->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="copy-btn" style="font-size:10px;padding:2px 8px;white-space:nowrap;">移動</button>
            </form>
            @if($tour->events_count === 0)
            <form method="POST" action="{{ route('tours.destroy', $tour) }}" style="display:inline;"
                  onsubmit="return confirm('「{{ $tour->name }}」を削除しますか？')">
                @csrf @method('DELETE')
                <button type="submit" class="copy-btn" style="font-size:10px;padding:2px 8px;color:#C7414F;">削除</button>
            </form>
            @endif
        </div>
    </div>
    @empty
    <div class="empty-state" style="padding:24px;">このグループにはまだツアーがありません</div>
    @endforelse
</x-app-layout>
