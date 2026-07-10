<x-app-layout :hide-fab="true" :hide-nav="true">
    <a href="{{ route('deadlines.form') }}" class="detail-back">‹ やり直す</a>

    <div class="sec-label">解析結果の確認</div>

    @if(session('error'))
    <div class="warn">{{ session('error') }}</div>
    @endif

    @if(empty($rows))
    <div class="empty-state" style="padding:24px;">解析できる締切情報がありませんでした</div>
    @else
    <p style="font-size:12px; color:var(--color-ink-sub); line-height:1.8; margin-bottom:12px;">
        対象の公演を確認・選択してください。マッチしない場合はプルダウンで手動選択できます。
    </p>

    <form method="POST" action="{{ route('deadlines.store') }}">
        @csrf

        @foreach($rows as $i => $row)
        <div class="d-block" style="margin-bottom:12px;padding:10px 14px;">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                <input type="checkbox" name="rows[{{ $i }}][include]" value="1" {{ $row['matched_event_id'] ? 'checked' : '' }}>
                <strong style="font-size:13px;">{{ $row['venue'] }} {{ $row['event_date'] }}</strong>
            </div>

            <div class="f-field" style="margin-bottom:6px;">
                <label style="font-size:11px;">対象の公演</label>
                <select class="f-input" name="rows[{{ $i }}][event_id]" style="font-size:12px;">
                    <option value="">-- 選択 --</option>
                    @foreach($events as $event)
                    <option value="{{ $event->id }}" {{ (int)$row['matched_event_id'] === $event->id ? 'selected' : '' }}>
                        {{ $event->displayName() }} {{ $event->event_date->format('m.d') }} {{ $event->venue?->name ?? '' }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div style="display:flex;gap:8px;">
                <div class="f-field" style="flex:1;margin-bottom:0;">
                    <label style="font-size:11px;">申込締切</label>
                    <input class="f-input" type="datetime-local" name="rows[{{ $i }}][application_deadline]"
                           value="{{ $row['application_deadline'] ? str_replace(' ', 'T', $row['application_deadline']) : '' }}" style="font-size:12px;">
                </div>
                <div class="f-field" style="flex:1;margin-bottom:0;">
                    <label style="font-size:11px;">発表日</label>
                    <input class="f-input" type="date" name="rows[{{ $i }}][announce_date]"
                           value="{{ $row['announce_date'] ?? '' }}" style="font-size:12px;">
                </div>
            </div>
        </div>
        @endforeach

        <button type="submit" class="btn btn-primary" style="margin-top:12px;">チェックした行を更新</button>
    </form>
    @endif
</x-app-layout>
