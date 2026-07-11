<x-app-layout :hide-header="true" :hide-fab="true" :hide-nav="true">
    <x-slot:pageHeader>
        <div class="page-header">
            <a href="{{ route('lots.index') }}" class="back">← 戻る</a>
            <h1>申込を登録</h1>
        </div>
    </x-slot:pageHeader>

    <form method="POST" action="{{ route('lots.store') }}">
        @csrf

        @include('partials.event-cascade', ['selectedEvent' => null])

        <label class="form-label">申込名義</label>
        @if($memberships->isEmpty())
        <div class="empty-state" style="padding:16px;">先に名義を登録してください</div>
        @else
        <div class="form-checkbox-group">
            @foreach($memberships as $m)
            <label class="form-checkbox-label">
                <input type="radio" name="identity_id" value="{{ $m->id }}"
                       {{ old('identity_id') == $m->id ? 'checked' : '' }}>
                <span class="dot" style="--oshi-color: {{ $m->oshi_color ?? '#C7414F' }}"></span>
                {{ $m->displayName() }}
            </label>
            @endforeach
        </div>
        @endif
        @error('identity_id')<div class="form-error">{{ $message }}</div>@enderror

        <label class="form-label">同行者 <span class="opt">（任意）</span></label>
        <select class="f-input" name="companion_id">
            <option value="">なし</option>
            @foreach($memberships as $m)
            <option value="{{ $m->id }}" {{ old('companion_id') == $m->id ? 'selected' : '' }}>{{ $m->displayName() }}</option>
            @endforeach
        </select>

        <button type="submit" class="btn btn-primary" style="margin-top:18px;">申込を登録する</button>
    </form>
</x-app-layout>
