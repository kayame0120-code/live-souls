<x-app-layout :hide-header="true" :hide-fab="true" :hide-nav="true">
    <x-slot:pageHeader>
        <div class="page-header">
            <a href="{{ route('lots.index') }}" class="back">← 戻る</a>
            <h1>申込を登録</h1>
        </div>
    </x-slot:pageHeader>

    <form method="POST" action="{{ route('lots.store') }}">
        @csrf

        {{-- 公演は events 共有マスタから検索付きセレクトで選択 --}}
        @include('partials.event-cascade', ['selectedEvent' => null])

        {{-- 申込名義（1つ以上必須 / spec §5-7） --}}
        <label class="form-label">申込名義（複数選択可）</label>
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

        <button type="submit" class="btn btn-primary" style="margin-top:18px;">申込を登録する</button>
    </form>
</x-app-layout>
