<x-app-layout :hide-header="true" :hide-fab="true">
    <x-slot:pageHeader>
        <div class="page-header">
            <a href="javascript:history.back()" class="back">← 戻る</a>
            <h1>{{ $venue->name }}</h1>
        </div>
    </x-slot:pageHeader>

    <div class="sec-label">会場情報</div>
    <div class="card">
        <div class="detail-section" style="padding-top:0;">
            <div class="detail-label">住所</div>
            <div class="detail-value {{ !$venue->address ? 'muted' : '' }}">{{ $venue->address ?? '未登録' }}</div>
        </div>
        <div class="detail-section">
            <div class="detail-label">最寄駅</div>
            <div class="detail-value {{ !$venue->nearest_station ? 'muted' : '' }}">{{ $venue->nearest_station ?? '未登録' }}</div>
        </div>
        <div class="detail-section" style="border-bottom:none;">
            <div class="detail-label">キャパシティ</div>
            <div class="detail-value {{ !$venue->capacity ? 'muted' : '' }}">{{ $venue->capacity ? number_format($venue->capacity) . '人' : '未登録' }}</div>
        </div>
    </div>

    <div class="sec-label">自分のメモ</div>
    <form method="POST" action="{{ route('venues.update-note', $venue) }}">
        @csrf @method('PUT')

        <div class="form-group">
            <label class="form-label" for="lodging">定宿・ホテルエリア</label>
            <input class="form-input" type="text" id="lodging" name="lodging"
                   value="{{ old('lodging', $note->lodging ?? '') }}" placeholder="未記入">
        </div>

        <div class="form-group">
            <label class="form-label" for="transport_cost">交通費目安</label>
            <input class="form-input" type="text" id="transport_cost" name="transport_cost"
                   value="{{ old('transport_cost', $note->transport_cost ?? '') }}" placeholder="未記入">
        </div>

        <div class="form-group">
            <label class="form-label" for="memo">個人メモ</label>
            <textarea class="form-textarea" id="memo" name="memo" placeholder="未記入">{{ old('memo', $note->memo ?? '') }}</textarea>
        </div>

        <button type="submit" class="btn btn-primary">メモを保存</button>
    </form>

    @if($attendances->isNotEmpty())
    <div class="sec-label">この会場の参戦履歴</div>
    @foreach($attendances as $attendance)
        @php $oshi = optional($attendance->fcMemberships->first())->oshi_color ?? '#C7414F'; @endphp
        <a href="{{ route('attendances.show', $attendance) }}" class="rec">
            <div class="rec-head">
                <span class="dot" style="--oshi-color: {{ $oshi }}"></span>
                <div class="rec-title">{{ $attendance->event_name }}</div>
                <div class="rec-date">{{ $attendance->event_date->format('Y.m.d') }}</div>
            </div>
            <div class="rec-meta">
                @if($attendance->seat_raw)
                <span>座席 <b>{{ $attendance->seat_raw }}</b></span>
                @endif
            </div>
        </a>
    @endforeach
    @endif
</x-app-layout>
