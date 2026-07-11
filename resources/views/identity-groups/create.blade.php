<x-app-layout :hide-header="true" :hide-fab="true" :hide-nav="true">
    <x-slot:pageHeader>
        <div class="page-header">
            <a href="{{ route('identity-groups.index') }}" class="back">← 戻る</a>
            <h1>グループを追加</h1>
        </div>
    </x-slot:pageHeader>

    <form method="POST" action="{{ route('identity-groups.store') }}">
        @csrf
        <div class="form-group">
            <label class="form-label" for="name">グループ名</label>
            <input class="form-input @error('name') is-invalid @enderror"
                   type="text" id="name" name="name" value="{{ old('name') }}"
                   required maxlength="50" placeholder="例: ジャニーズ系">
            @error('name')<div class="form-error">{{ $message }}</div>@enderror
        </div>
        <button type="submit" class="btn btn-primary">作成する</button>
    </form>
</x-app-layout>
