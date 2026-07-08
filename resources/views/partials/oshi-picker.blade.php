{{-- 担当色プリセット選択（spec §3・config/oshi_colors.php の11色）
     $selectedColor（nullable hex）で初期選択。hidden の oshi_color に値を入れる。 --}}
@php $selectedColor = $selectedColor ?? old('oshi_color'); @endphp
<label class="form-label">担当色（プリセット11色から選択）</label>
<input type="hidden" name="oshi_color" id="oshi_color" value="{{ $selectedColor }}">
<div class="swatch-picker" id="oshi-picker">
    @foreach(config('oshi_colors') as $name => $hex)
    <span class="swatch-opt {{ $selectedColor === $hex ? 'sel' : '' }}"
          data-hex="{{ $hex }}" title="{{ $name }}"
          style="background: {{ $hex }};{{ $hex === '#FFFFFF' ? ' border-color: var(--color-keisen-strong);' : '' }}"></span>
    @endforeach
</div>
@error('oshi_color')<div class="form-error">{{ $message }}</div>@enderror

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const picker = document.getElementById('oshi-picker');
    const hidden = document.getElementById('oshi_color');
    if (!picker) return;
    picker.querySelectorAll('.swatch-opt').forEach(opt => {
        opt.addEventListener('click', () => {
            picker.querySelectorAll('.swatch-opt').forEach(x => x.classList.remove('sel'));
            opt.classList.add('sel');
            hidden.value = opt.dataset.hex;
        });
    });
});
</script>
@endpush
