<x-app-layout :hide-header="true" :hide-fab="true" :hide-nav="true">
    <x-slot:pageHeader>
        <div class="page-header">
            <a href="{{ route('lots.import') }}" class="back">← やり直す</a>
            <h1>解析結果の確認</h1>
        </div>
    </x-slot:pageHeader>

    @if(empty($rows))
        <div class="empty-state">解析できる行がありませんでした。形式を確認してください</div>
    @else
    <p style="font-size:12px; color:var(--color-ink-sub); line-height:1.8; margin-bottom:12px;">
        内容を確認・修正してください。公演名と日付が空の行は取込対象外です（背景色つき）。
    </p>

    <form method="POST" action="{{ route('lots.import.store') }}">
        @csrf
        <table class="import-table">
            <thead>
                <tr>
                    <th></th>
                    <th>日付</th>
                    <th>公演名</th>
                    <th>会場</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $i => $row)
                @php $invalid = empty($row['event_name']) || empty($row['event_date']); @endphp
                <tr class="{{ $invalid ? 'import-row-invalid' : '' }}">
                    <td>
                        <input type="checkbox" name="rows[{{ $i }}][include]" value="1" {{ $invalid ? '' : 'checked' }}>
                    </td>
                    <td style="min-width:120px;">
                        <input class="form-input" type="date" name="rows[{{ $i }}][event_date]" value="{{ $row['event_date'] }}">
                    </td>
                    <td>
                        <input class="form-input" type="text" name="rows[{{ $i }}][event_name]" value="{{ $row['event_name'] }}" placeholder="（解析できず）">
                    </td>
                    <td>
                        <input class="form-input" type="text" name="rows[{{ $i }}][venue_name]" value="{{ $row['venue_name'] }}" placeholder="（任意）">
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        {{-- 申込名義（1つ以上必須） --}}
        <div class="form-group" style="margin-top:20px;">
            <label class="form-label">申込名義（全行に適用）</label>
            @if($memberships->isEmpty())
            <div class="empty-state" style="padding:16px;">先に名義を登録してください</div>
            @else
            <div class="form-checkbox-group">
                @foreach($memberships as $m)
                <label class="form-checkbox-label">
                    <input type="checkbox" name="identity_ids[]" value="{{ $m->id }}">
                    <span class="dot" style="--oshi-color: {{ $m->oshi_color ?? '#C7414F' }}"></span>
                    {{ $m->displayName() }}
                </label>
                @endforeach
            </div>
            @endif
            @error('identity_ids')<div class="form-error">{{ $message }}</div>@enderror
        </div>

        <button type="submit" class="btn btn-primary" style="margin-top:8px;">チェックした行を一括登録</button>
    </form>
    @endif
</x-app-layout>
