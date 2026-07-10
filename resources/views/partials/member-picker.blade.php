{{-- 担当グループ・メンバー選択（spec §2.2・§4.5）
     ①アイドルグループ選択→②公式メンバーが色付きチップで並ぶ→③選択で担当色・artist_name自動反映→④手動色上書き可
     $selectedGroupMemberId / $selectedColor を受け取る --}}
@php
    $selectedGroupMemberId = $selectedGroupMemberId ?? old('group_member_id');
    $selectedColor = $selectedColor ?? old('oshi_color');
    $idolGroups = \App\Models\IdolGroup::with('members')->orderBy('name')->get();
    $currentMember = $selectedGroupMemberId ? \App\Models\GroupMember::find($selectedGroupMemberId) : null;
    $currentIdolGroupId = $currentMember?->idol_group_id;
@endphp

<input type="hidden" name="group_id" id="group_id" value="{{ old('group_id', $currentIdolGroupId) }}">
<input type="hidden" name="group_member_id" id="group_member_id" value="{{ $selectedGroupMemberId }}">
<input type="hidden" name="oshi_color" id="oshi_color" value="{{ $selectedColor }}">
<input type="hidden" name="artist_name" id="artist_name" value="{{ old('artist_name', $currentMember?->name ?? '') }}">

<div class="f-field">
    <label for="idol_group_select">担当グループ</label>
    <select class="f-input @error('group_member_id') is-invalid @enderror" id="idol_group_select">
        <option value="">選択してください</option>
        @foreach($idolGroups as $ig)
        <option value="{{ $ig->id }}" {{ (int)$currentIdolGroupId === $ig->id ? 'selected' : '' }}>{{ $ig->name }}{{ $ig->status ? "（{$ig->status}）" : '' }}</option>
        @endforeach
    </select>
    @error('group_member_id')<div class="form-error">担当メンバーを選択してください</div>@enderror
</div>

<div id="member-chips" class="swatch-picker" style="flex-wrap:wrap;gap:8px;margin-bottom:12px;{{ $currentIdolGroupId ? '' : 'display:none;' }}">
</div>

<div id="selected-member-display" style="margin-bottom:12px;font-size:13px;{{ $currentMember ? '' : 'display:none;' }}">
    担当: <strong id="selected-member-name">{{ $currentMember?->name ?? '' }}</strong>
</div>

<div id="manual-color-section" style="{{ $selectedColor && !$selectedGroupMemberId ? '' : 'display:none;' }}">
    <label class="form-label">担当色（手動選択・11色プリセット）</label>
    <div class="swatch-picker" id="oshi-picker">
        @foreach(config('oshi_colors') as $name => $hex)
        <span class="swatch-opt {{ $selectedColor === $hex ? 'sel' : '' }}"
              data-hex="{{ $hex }}" title="{{ $name }}"
              style="background: {{ $hex }};{{ $hex === '#FFFFFF' ? ' border-color: var(--color-keisen-strong);' : '' }}"></span>
        @endforeach
    </div>
</div>

<div style="margin-bottom:12px;">
    <button type="button" id="toggle-manual-color" class="copy-btn" style="font-size:11px;">手動で色を変える</button>
</div>

@error('oshi_color')<div class="form-error">{{ $message }}</div>@enderror

<script id="idol-groups-data" type="application/json">@json($idolGroups)</script>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const groups = JSON.parse(document.getElementById('idol-groups-data').textContent);
    const select = document.getElementById('idol_group_select');
    const chipsWrap = document.getElementById('member-chips');
    const hiddenGroup = document.getElementById('group_id');
    const hiddenMember = document.getElementById('group_member_id');
    const hiddenColor = document.getElementById('oshi_color');
    const hiddenArtist = document.getElementById('artist_name');
    const memberDisplay = document.getElementById('selected-member-display');
    const memberNameEl = document.getElementById('selected-member-name');
    const manualSection = document.getElementById('manual-color-section');
    const toggleBtn = document.getElementById('toggle-manual-color');
    const picker = document.getElementById('oshi-picker');
    if (!select || !chipsWrap) return;

    let selectedMemberId = parseInt(hiddenMember.value) || null;

    function renderMembers(groupId) {
        chipsWrap.textContent = '';
        if (!groupId) { chipsWrap.style.display = 'none'; return; }
        const group = groups.find(g => g.id === parseInt(groupId));
        if (!group) return;
        chipsWrap.style.display = 'flex';
        group.members.forEach(m => {
            const chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'member-chip' + (selectedMemberId === m.id ? ' sel' : '');
            chip.dataset.id = m.id;
            chip.dataset.hex = m.color_hex || '';
            chip.style.cssText = 'display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:16px;border:2px solid ' + (selectedMemberId === m.id ? (m.color_hex || '#888') : 'var(--color-keisen)') + ';background:' + (selectedMemberId === m.id ? (m.color_hex || '#888') + '22' : 'transparent') + ';font-size:13px;cursor:pointer;';
            const dot = document.createElement('span');
            dot.style.cssText = 'width:12px;height:12px;border-radius:50%;background:' + (m.color_hex || '#888') + ';display:inline-block;flex-shrink:0;';
            chip.appendChild(dot);
            chip.appendChild(document.createTextNode(m.name));
            chip.addEventListener('click', () => {
                selectedMemberId = m.id;
                hiddenMember.value = m.id;
                hiddenArtist.value = m.name;
                if (m.color_hex) { hiddenColor.value = m.color_hex; }
                memberNameEl.textContent = m.name;
                memberDisplay.style.display = '';
                manualSection.style.display = 'none';
                picker.querySelectorAll('.swatch-opt').forEach(x => x.classList.remove('sel'));
                renderMembers(groupId);
            });
            chipsWrap.appendChild(chip);
        });
    }

    select.addEventListener('change', function () {
        selectedMemberId = null;
        hiddenGroup.value = this.value;
        hiddenMember.value = '';
        hiddenArtist.value = '';
        memberDisplay.style.display = 'none';
        renderMembers(this.value);
    });

    toggleBtn.addEventListener('click', function () {
        manualSection.style.display = manualSection.style.display === 'none' ? '' : 'none';
    });

    if (picker) {
        picker.querySelectorAll('.swatch-opt').forEach(opt => {
            opt.addEventListener('click', () => {
                picker.querySelectorAll('.swatch-opt').forEach(x => x.classList.remove('sel'));
                opt.classList.add('sel');
                hiddenColor.value = opt.dataset.hex;
            });
        });
    }

    if (select.value) renderMembers(select.value);
});
</script>
@endpush
