<x-app-layout :hide-fab="true" :hide-nav="true">
    <a href="{{ route('identities.show', $fcMembership) }}" class="detail-back">‹ 編集をやめる</a>

    <form method="POST" action="{{ route('identities.update', $fcMembership) }}">
        @csrf @method('PUT')

        {{-- 名義の基本 --}}
        <div class="d-block">
            <div class="d-h">名義の基本</div>
            <div class="f-field">
                <label for="group_id">FC（グループ）</label>
                <select class="f-input" id="group_id" name="group_id" required>
                    @foreach($groups as $group)
                    <option value="{{ $group->id }}" {{ old('group_id', $fcMembership->group_id) == $group->id ? 'selected' : '' }}>{{ $group->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="f-field">
                <label for="artist_name">担当アーティスト名</label>
                <input class="f-input" type="text" id="artist_name" name="artist_name" value="{{ old('artist_name', $fcMembership->artist_name) }}" required placeholder="担当を入力">
            </div>
            <div class="f-field">
                <label for="label">名義の呼び名 <span class="opt">（任意）</span></label>
                <input class="f-input" type="text" id="label" name="label" value="{{ old('label', $fcMembership->person->label) }}" placeholder="例：母名義・ゆきちゃん共同">
            </div>
            <div class="f-field" style="margin-bottom:0;">
                @include('partials.member-picker', [
                    'selectedGroupMemberId' => old('group_member_id', $fcMembership->group_member_id),
                    'selectedColor' => old('oshi_color', $fcMembership->oshi_color),
                ])
            </div>
        </div>

        {{-- ログイン情報 --}}
        <div class="d-block">
            <div class="d-h">ログイン情報</div>
            <div class="f-field">
                <label for="member_no">会員番号</label>
                <input class="f-input" type="text" id="member_no" name="member_no" value="{{ old('member_no', $fcMembership->member_no) }}" placeholder="会員番号">
            </div>
            <div class="f-field">
                <label for="fc_password">パスワード</label>
                {{-- 復号済みパスワードをHTMLに出力しない。空のままなら既存値を維持 --}}
                <input class="f-input" type="password" id="fc_password" name="fc_password" value="" placeholder="変更する場合のみ入力">
                <div class="f-hint">保存された値は表示されません。変更したいときだけ入力してください。</div>
            </div>
            <div class="f-field" style="margin-bottom:0;">
                <label for="login_id">ID <span class="opt">（任意・メアドとは別）</span></label>
                <input class="f-input" type="text" id="login_id" name="login_id" value="{{ old('login_id', $fcMembership->login_id) }}" placeholder="ログインIDがあれば入力">
            </div>
        </div>

        {{-- 個人情報（名義人） --}}
        <div class="d-block">
            <div class="d-h">個人情報（名義人）</div>
            <div class="f-field">
                <label for="person_name">氏名</label>
                <input class="f-input @error('person_name') is-invalid @enderror" type="text" id="person_name" name="person_name" value="{{ old('person_name', $fcMembership->person->name) }}" required>
                @error('person_name')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="f-field">
                <label for="address">住所</label>
                <input class="f-input" type="text" id="address" name="address" value="{{ old('address', $fcMembership->person->address) }}">
            </div>
            <div class="f-field">
                <label for="phone">電話番号</label>
                <input class="f-input" type="tel" id="phone" name="phone" value="{{ old('phone', $fcMembership->person->phone) }}">
            </div>
            <div class="f-field">
                <label for="email">メールアドレス <span class="opt">（暗号化保存）</span></label>
                <input class="f-input @error('email') is-invalid @enderror" type="email" id="email" name="email" value="{{ old('email', $fcMembership->email) }}" placeholder="fc-login@example.com">
                @error('email')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="f-field" style="margin-bottom:0;">
                <label for="birth_date">誕生日</label>
                <input class="f-input" type="date" id="birth_date" name="birth_date" value="{{ old('birth_date', optional($fcMembership->person->birth_date)->format('Y-m-d')) }}">
                <div class="f-hint">年齢は誕生日から自動計算されます。</div>
            </div>
        </div>

        {{-- 入会日 --}}
        <div class="d-block">
            <div class="d-h">入会日</div>
            <div class="f-field" style="margin-bottom:0;">
                <label for="joined_month_input">入会年月</label>
                <input class="f-input @error('joined_month_input') is-invalid @enderror" type="month" id="joined_month_input" name="joined_month_input"
                       value="{{ old('joined_month_input', optional($fcMembership->joined_on)->format('Y-m')) }}">
                @error('joined_month_input')<div class="form-error">{{ $message }}</div>@enderror
                <div class="f-hint">有効期限・更新受付期間はこの日付から自動計算されます。</div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">保存する</button>
    </form>
</x-app-layout>
