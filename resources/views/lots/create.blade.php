<x-app-layout :hide-header="true" :hide-fab="true" :hide-nav="true">
    <x-slot:pageHeader>
        <div class="page-header">
            <a href="{{ route('lots.index') }}" class="back">← 戻る</a>
            <h1>申込を登録</h1>
        </div>
    </x-slot:pageHeader>

    <form method="POST" action="{{ route('lots.store') }}">
        @csrf

        <div class="form-group">
            <label class="form-label" for="event_name">公演名</label>
            <input class="form-input @error('event_name') is-invalid @enderror"
                   type="text" id="event_name" name="event_name"
                   value="{{ old('event_name') }}" required autocomplete="off">
            <div id="event-suggestions" style="display:none; background:var(--color-card); border:1px solid var(--color-keisen); border-radius:10px; margin-top:4px; overflow:hidden;"></div>
            @error('event_name')<div class="form-error">{{ $message }}</div>@enderror
        </div>

        <div class="form-group">
            <label class="form-label" for="event_date">公演日</label>
            <input class="form-input @error('event_date') is-invalid @enderror"
                   type="date" id="event_date" name="event_date" value="{{ old('event_date') }}" required>
            @error('event_date')<div class="form-error">{{ $message }}</div>@enderror
        </div>

        <div class="form-group">
            <label class="form-label" for="venue_name">会場</label>
            <input class="form-input" type="text" id="venue_name" name="venue_name"
                   value="{{ old('venue_name') }}" placeholder="会場名を入力" autocomplete="off">
            <input type="hidden" id="venue_id" name="venue_id" value="{{ old('venue_id') }}">
            <div id="venue-suggestions" style="display:none; background:var(--color-card); border:1px solid var(--color-keisen); border-radius:10px; margin-top:4px; overflow:hidden;"></div>
        </div>

        <div class="form-group" id="venue-address-wrap" style="{{ old('venue_id') ? 'display:none;' : '' }}">
            <label class="form-label" for="venue_address">会場住所（新規会場のみ・自動補完）</label>
            <input class="form-input" type="text" id="venue_address" name="venue_address"
                   value="{{ old('venue_address') }}" placeholder="空欄可">
        </div>

        {{-- 申込名義（1つ以上必須 / spec §6） --}}
        <div class="form-group">
            <label class="form-label">申込名義</label>
            @if($memberships->isEmpty())
            <div class="empty-state" style="padding:16px;">先に名義を登録してください</div>
            @else
            <div class="form-checkbox-group">
                @foreach($memberships as $m)
                <label class="form-checkbox-label">
                    <input type="checkbox" name="identity_ids[]" value="{{ $m->id }}"
                           {{ in_array($m->id, old('identity_ids', [])) ? 'checked' : '' }}>
                    <span class="dot" style="--oshi-color: {{ $m->oshi_color ?? '#C7414F' }}"></span>
                    {{ $m->displayName() }}
                </label>
                @endforeach
            </div>
            @endif
            @error('identity_ids')<div class="form-error">{{ $message }}</div>@enderror
        </div>

        <button type="submit" class="btn btn-primary">申込を登録する</button>
    </form>
</x-app-layout>

@push('scripts')
@include('partials.suggest-scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    setupVenueField();
    setupEventNameField();
});
</script>
@endpush
