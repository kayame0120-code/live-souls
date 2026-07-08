<x-app-layout :hide-header="true" :hide-fab="true" :hide-nav="true">
    <x-slot:pageHeader>
        <div class="page-header">
            <a href="{{ route('events.import') }}" class="back">← やり直す</a>
            <h1>解析結果の確認</h1>
        </div>
    </x-slot:pageHeader>

    @if(empty($rows))
        <div class="empty-state">解析できる行がありませんでした。形式を確認してください</div>
    @else
    <p style="font-size:12px; color:var(--color-ink-sub); line-height:1.8; margin-bottom:12px;">
        内容を確認・修正してください。公演名と日付が空の行は取込対象外です（背景色つき）。名義選択はありません。
    </p>

    <form method="POST" action="{{ route('events.import.store') }}">
        @csrf
        <div class="imp-head"><span>日付</span><span>公演名 / 会場</span><span></span></div>
        @foreach($rows as $i => $row)
        @php $invalid = empty($row['event_name']) || empty($row['event_date']); @endphp
        <div class="imp-row {{ $invalid ? 'imp-row-invalid' : '' }}">
            <input type="hidden" name="rows[{{ $i }}][include]" value="0">
            <div style="display:flex; align-items:center; gap:4px;">
                <input type="checkbox" name="rows[{{ $i }}][include]" value="1" {{ $invalid ? '' : 'checked' }}>
                <input class="form-input" type="date" name="rows[{{ $i }}][event_date]" value="{{ $row['event_date'] }}">
            </div>
            <div>
                <input class="form-input" type="text" name="rows[{{ $i }}][event_name]" value="{{ $row['event_name'] }}" placeholder="（解析できず）">
                <input class="form-input" type="text" name="rows[{{ $i }}][venue_name]" value="{{ $row['venue_name'] }}" placeholder="会場（任意）" style="margin-top:4px;">
            </div>
            <span></span>
        </div>
        @endforeach

        <button type="submit" class="btn btn-primary" style="margin-top:18px;">チェックした行を共有マスタへ登録</button>
    </form>
    @endif
</x-app-layout>
