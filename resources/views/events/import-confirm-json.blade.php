<x-app-layout :hide-fab="true" :hide-nav="true">
    <a href="{{ route('events.import') }}" class="detail-back">‹ やり直す</a>
    <div class="sec-label">JSON読み込み結果</div>

    @php
        $totalEvents = collect($tours)->sum(fn($t) => count(array_filter($t['events'], fn($e) => !empty($e['event_date']))));
        $totalDeadlines = collect($tours)->sum(fn($t) => count($t['deadlines'] ?? []));
    @endphp

    <p style="font-size:12px; color:var(--color-ink-sub); margin-bottom:12px;">
        {{ count($tours) }}ツアー・{{ $totalEvents }}公演{{ $totalDeadlines ? '・締切' . $totalDeadlines . '件' : '' }}を登録します。
    </p>

    <form method="POST" action="{{ route('events.import.json.store') }}">
        @csrf

        @foreach($tours as $ti => $tourData)
        <div class="venue-hero" style="margin-bottom:8px;">
            <div class="vh-name">{{ $tourData['tour'] ?? 'ツアー名なし' }}</div>
            <div class="vh-sub">{{ count($tourData['events']) }}公演{{ !empty($tourData['deadlines']) ? '・締切' . count($tourData['deadlines']) . '件' : '' }}</div>
        </div>

        <input type="hidden" name="tours[{{ $ti }}][tour_name]" value="{{ $tourData['tour'] ?? '' }}">

        @foreach($tourData['events'] as $ei => $event)
        @php $hasDate = !empty($event['event_date']); @endphp
        <div class="d-block" style="margin-bottom:4px;padding:8px 14px;{{ $hasDate ? '' : 'opacity:0.5;' }}">
            <input type="hidden" name="tours[{{ $ti }}][events][{{ $ei }}][include]" value="{{ $hasDate ? '1' : '0' }}">
            <input type="hidden" name="tours[{{ $ti }}][events][{{ $ei }}][event_date]" value="{{ $event['event_date'] ?? '' }}">
            <input type="hidden" name="tours[{{ $ti }}][events][{{ $ei }}][start_time]" value="{{ $event['start_time'] ?? '' }}">
            <input type="hidden" name="tours[{{ $ti }}][events][{{ $ei }}][venue_name]" value="{{ $event['venue'] ?? '' }}">
            <input type="hidden" name="tours[{{ $ti }}][events][{{ $ei }}][event_label]" value="{{ $event['event_label'] ?? '' }}">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <div style="font-size:13px;font-weight:600;">{{ $event['event_date'] ?? '日付不明' }}@if(!empty($event['start_time'])) {{ $event['start_time'] }}@endif</div>
                    <div style="font-size:12px;color:var(--color-ink-sub);">{{ $event['venue'] ?? '' }}@if(!empty($event['event_label'])) ・{{ $event['event_label'] }}@endif</div>
                </div>
                @if($hasDate)
                <span style="font-size:11px;color:#43A047;">登録</span>
                @else
                <span style="font-size:11px;color:var(--color-ink-sub);">スキップ</span>
                @endif
            </div>
        </div>
        @endforeach

        @foreach($tourData['deadlines'] ?? [] as $di => $dl)
        <div class="d-block" style="margin-bottom:4px;padding:8px 14px;">
            <input type="hidden" name="tours[{{ $ti }}][deadlines][{{ $di }}][label]" value="{{ $dl['label'] ?? '' }}">
            <input type="hidden" name="tours[{{ $ti }}][deadlines][{{ $di }}][application_deadline]" value="{{ $dl['application_deadline'] ?? '' }}">
            <input type="hidden" name="tours[{{ $ti }}][deadlines][{{ $di }}][announce_date]" value="{{ $dl['announce_date'] ?? '' }}">
            <div style="font-size:12px;">
                <span style="font-weight:600;">{{ $dl['label'] ?? '締切' }}</span>
                @if(!empty($dl['application_deadline'])) — 締切 {{ $dl['application_deadline'] }}@endif
                @if(!empty($dl['announce_date'])) ・発表 {{ $dl['announce_date'] }}@endif
            </div>
        </div>
        @endforeach
        @endforeach

        <button type="submit" class="btn btn-primary" style="margin-top:18px;">{{ count($tours) }}ツアー・{{ $totalEvents }}件を一括登録する</button>
    </form>
</x-app-layout>
