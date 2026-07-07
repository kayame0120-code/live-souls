<x-app-layout :hide-header="true" :hide-fab="true" :hide-nav="true">
    <x-slot:pageHeader>
        <div class="page-header">
            <a href="{{ route('identities.show', $fcMembership) }}" class="back">← 戻る</a>
            <h1>名義を編集</h1>
        </div>
    </x-slot:pageHeader>

    <form method="POST" action="{{ route('identities.update', $fcMembership) }}">
        @csrf @method('PUT')

        <div class="sec-label" style="margin-top:8px;">FC情報</div>

        <div class="form-group">
            <label class="form-label" for="group_id">グループ</label>
            <select class="form-select" id="group_id" name="group_id" required>
                @foreach($groups as $group)
                <option value="{{ $group->id }}" {{ old('group_id', $fcMembership->group_id) == $group->id ? 'selected' : '' }}>{{ $group->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="form-group">
            <label class="form-label" for="artist_name">アーティスト名</label>
            <input class="form-input" type="text" id="artist_name" name="artist_name" value="{{ old('artist_name', $fcMembership->artist_name) }}" required>
        </div>

        <div class="form-group">
            <label class="form-label" for="club_name">FC名</label>
            <input class="form-input" type="text" id="club_name" name="club_name" value="{{ old('club_name', $fcMembership->club_name) }}">
        </div>

        <div class="form-group">
            <label class="form-label" for="member_no">会員番号</label>
            <input class="form-input" type="text" id="member_no" name="member_no" value="{{ old('member_no', $fcMembership->member_no) }}">
        </div>

        <div class="form-group">
            <label class="form-label" for="login_id">ログインID</label>
            <input class="form-input" type="text" id="login_id" name="login_id" value="{{ old('login_id', $fcMembership->login_id) }}">
        </div>

        <div class="form-group">
            <label class="form-label" for="fc_password">パスワード</label>
            {{-- 復号済みパスワードをHTMLに出力しない。空のままなら既存値を維持 --}}
            <input class="form-input" type="password" id="fc_password" name="fc_password" value="" placeholder="変更する場合のみ入力（空欄なら現在のまま）">
        </div>

        <div style="display:flex; gap:12px;">
            <div class="form-group" style="flex:1">
                <label class="form-label" for="joined_month">入会月</label>
                <input class="form-input" type="month" id="joined_month" name="joined_month" value="{{ old('joined_month', $fcMembership->joined_month) }}">
            </div>
            <div class="form-group" style="flex:1">
                <label class="form-label" for="oshi_color">担当色</label>
                <input class="form-input" type="color" id="oshi_color" name="oshi_color" value="{{ old('oshi_color', $fcMembership->oshi_color ?? '#C7414F') }}" style="height:42px; padding:4px;">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label" for="renewal_cycle">更新サイクル</label>
            <input class="form-input" type="text" id="renewal_cycle" name="renewal_cycle" value="{{ old('renewal_cycle', $fcMembership->renewal_cycle) }}">
        </div>

        <div class="sec-label">名義人情報</div>

        <div class="form-group">
            <label class="form-label" for="person_name">氏名</label>
            <input class="form-input" type="text" id="person_name" name="person_name" value="{{ old('person_name', $fcMembership->person->name) }}" required>
        </div>

        <div class="form-group">
            <label class="form-label" for="label">表示ラベル</label>
            <input class="form-input" type="text" id="label" name="label" value="{{ old('label', $fcMembership->person->label) }}">
        </div>

        <div class="form-group">
            <label class="form-label" for="birth_date">生年月日</label>
            <input class="form-input" type="date" id="birth_date" name="birth_date" value="{{ old('birth_date', optional($fcMembership->person->birth_date)->format('Y-m-d')) }}">
        </div>

        <div class="form-group">
            <label class="form-label" for="phone">電話番号</label>
            <input class="form-input" type="tel" id="phone" name="phone" value="{{ old('phone', $fcMembership->person->phone) }}">
        </div>

        <div class="form-group">
            <label class="form-label" for="address">住所</label>
            <input class="form-input" type="text" id="address" name="address" value="{{ old('address', $fcMembership->person->address) }}">
        </div>

        <button type="submit" class="btn btn-primary" style="margin-top:8px;">更新する</button>
    </form>
</x-app-layout>
