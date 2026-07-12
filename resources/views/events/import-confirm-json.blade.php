<x-app-layout :hide-fab="true" :hide-nav="true">
    <a href="{{ route('events.import') }}" class="detail-back">‹ やり直す</a>
    <div class="sec-label">JSON読み込み結果</div>

    @php
        $totalEvents = collect($eventsGroups)->sum(fn($g) => count(array_filter($g['events'], fn($e) => !empty($e['event_date']))));
        $totalDeadlines = collect($eventsGroups)->sum(fn($g) => count($g['deadlines'] ?? []));
        $totalItems = collect($setlistGroups)->sum(fn($g) => count($g['items']));
    @endphp

    @if(!empty($errors))
    <div class="warn" style="margin-bottom:12px;">
        @foreach($errors as $err)
        <div>{{ $err }}</div>
        @endforeach
    </div>
    @endif

    @if(!empty($validationErrors))
    <div class="warn" style="margin-bottom:12px;">
        @foreach($validationErrors as $ve)
        <div>{{ $ve }}</div>
        @endforeach
    </div>
    @endif

    @if(!empty($unknownFiles))
    <div class="d-block" style="margin-bottom:12px;opacity:0.6;">
        <div class="d-h">スキップ（種別不明）</div>
        @foreach($unknownFiles as $uf)
        <div style="font-size:12px;padding:4px 14px;">{{ $uf }}</div>
        @endforeach
    </div>
    @endif

    @if(empty($eventsGroups) && empty($setlistGroups))
    <div class="empty-state" style="padding:24px;">読み込めるデータがありませんでした</div>
    @else
    <p style="font-size:12px; color:var(--color-ink-sub); margin-bottom:12px;">
        @if($totalEvents > 0){{ $totalEvents }}公演@endif
        @if($totalEvents > 0 && $totalItems > 0)・@endif
        @if($totalItems > 0){{ $totalItems }}曲@endif
        @if($totalDeadlines > 0)・締切{{ $totalDeadlines }}件@endif
        を登録します。
    </p>

    <form method="POST" action="{{ route('events.import.json.store') }}">
        @csrf

        {{-- 公演グループ --}}
        @foreach($eventsGroups as $gi => $group)
        <div class="venue-hero" style="margin-bottom:8px;">
            <div class="vh-name">{{ $group['tour'] ?? 'ツアー名なし' }}</div>
            <div class="vh-sub">公演 {{ count($group['events']) }}件{{ !empty($group['deadlines']) ? '・締切' . count($group['deadlines']) . '件' : '' }}</div>
        </div>

        <div class="f-field" style="margin-bottom:8px;">
            <label>ツアー名</label>
            <input class="f-input" type="text" name="events_groups[{{ $gi }}][tour_name]" value="{{ $group['tour'] ?? '' }}" required>
        </div>

        @foreach($group['events'] as $ei => $event)
        @php $hasDate = !empty($event['event_date']) && empty($event['_invalid']); @endphp
        <div class="d-block" style="margin-bottom:4px;padding:8px 14px;{{ $hasDate ? '' : 'opacity:0.5;' }}">
            <input type="hidden" name="events_groups[{{ $gi }}][events][{{ $ei }}][include]" value="{{ $hasDate ? '1' : '0' }}">
            <input type="hidden" name="events_groups[{{ $gi }}][events][{{ $ei }}][event_date]" value="{{ $event['event_date'] ?? '' }}">
            <input type="hidden" name="events_groups[{{ $gi }}][events][{{ $ei }}][start_time]" value="{{ $event['start_time'] ?? '' }}">
            <input type="hidden" name="events_groups[{{ $gi }}][events][{{ $ei }}][venue_name]" value="{{ $event['venue'] ?? '' }}">
            <input type="hidden" name="events_groups[{{ $gi }}][events][{{ $ei }}][event_label]" value="{{ $event['event_label'] ?? '' }}">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <div style="font-size:13px;font-weight:600;">{{ $event['event_date'] ?? '日付不明' }}@if(!empty($event['start_time'])) {{ $event['start_time'] }}@endif</div>
                    <div style="font-size:12px;color:var(--color-ink-sub);">{{ $event['venue'] ?? '' }}@if(!empty($event['event_label'])) ・{{ $event['event_label'] }}@endif</div>
                </div>
                @if($hasDate)
                <span style="font-size:11px;color:#43A047;">登録</span>
                @elseif(!empty($event['_invalid']))
                <span style="font-size:11px;color:#E53935;">不正</span>
                @else
                <span style="font-size:11px;color:var(--color-ink-sub);">スキップ</span>
                @endif
            </div>
        </div>
        @endforeach

        @foreach($group['deadlines'] ?? [] as $di => $dl)
        <div class="d-block" style="margin-bottom:4px;padding:8px 14px;">
            <input type="hidden" name="events_groups[{{ $gi }}][deadlines][{{ $di }}][label]" value="{{ $dl['label'] ?? '' }}">
            <input type="hidden" name="events_groups[{{ $gi }}][deadlines][{{ $di }}][application_deadline]" value="{{ $dl['application_deadline'] ?? '' }}">
            <input type="hidden" name="events_groups[{{ $gi }}][deadlines][{{ $di }}][announce_date]" value="{{ $dl['announce_date'] ?? '' }}">
            <div style="font-size:12px;">
                <span style="font-weight:600;">{{ $dl['label'] ?? '締切' }}</span>
                @if(!empty($dl['application_deadline'])) — 締切 {{ $dl['application_deadline'] }}@endif
                @if(!empty($dl['announce_date'])) ・発表 {{ $dl['announce_date'] }}@endif
            </div>
        </div>
        @endforeach
        @endforeach

        {{-- セットリストグループ --}}
        @foreach($setlistGroups as $si => $group)
        <div class="venue-hero" style="margin-bottom:8px;margin-top:16px;">
            <div class="vh-name">セットリスト</div>
            <div class="vh-sub">{{ count($group['items']) }}曲</div>
        </div>

        <div class="f-field" style="margin-bottom:8px;">
            <label>ツアー名</label>
            <input class="f-input" type="text" name="setlist_groups[{{ $si }}][tour_name]" value="{{ $group['tour'] ?? '' }}" required>
        </div>

        @foreach($group['items'] as $ii => $item)
        @php $valid = !empty($item['title']) && empty($item['_invalid']); @endphp
        <div class="d-block" style="margin-bottom:4px;padding:8px 14px;{{ $valid ? '' : 'opacity:0.5;' }}">
            <input type="hidden" name="setlist_groups[{{ $si }}][items][{{ $ii }}][include]" value="{{ $valid ? '1' : '0' }}">
            <input type="hidden" name="setlist_groups[{{ $si }}][items][{{ $ii }}][title]" value="{{ $item['title'] ?? '' }}">
            <input type="hidden" name="setlist_groups[{{ $si }}][items][{{ $ii }}][display_label]" value="{{ $item['note'] ?? '' }}">
            <div style="display:flex;gap:10px;align-items:center;">
                <span style="min-width:28px;text-align:center;font-weight:600;font-size:12px;">{{ $item['order'] ?? $ii + 1 }}</span>
                <span style="flex:1;font-size:13px;">{{ $item['title'] ?? '(曲名なし)' }}</span>
                @if(!empty($item['note']))<span style="font-size:11px;color:var(--color-ink-sub);">{{ $item['note'] }}</span>@endif
                @if($valid)
                <span style="font-size:11px;color:#43A047;">登録</span>
                @else
                <span style="font-size:11px;color:#E53935;">不正</span>
                @endif
            </div>
        </div>
        @endforeach
        @endforeach

        @php
            $btnParts = [];
            if ($totalEvents > 0) $btnParts[] = $totalEvents . '公演';
            if ($totalItems > 0) $btnParts[] = $totalItems . '曲';
        @endphp
        <button type="submit" class="btn btn-primary" style="margin-top:18px;">{{ implode('・', $btnParts) }}を一括登録する</button>
    </form>
    @endif
</x-app-layout>
