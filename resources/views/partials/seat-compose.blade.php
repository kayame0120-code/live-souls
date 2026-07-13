{{-- 座席3フィールド → seat_raw 自動合成（手動編集後は上書きしない / spec §5-8） --}}
<script nonce="{{ $cspNonce ?? '' }}">
document.addEventListener('DOMContentLoaded', function () {
    const block = document.getElementById('seat_block');
    const row = document.getElementById('seat_row');
    const number = document.getElementById('seat_number');
    const raw = document.getElementById('seat_raw');
    if (!block || !raw) return;

    const compose = () => {
        const parts = [];
        if (block.value.trim()) parts.push(block.value.trim());
        if (row.value.trim()) parts.push(row.value.trim() + '列');
        if (number.value.trim()) parts.push(number.value.trim() + '番');
        return parts.join(' ');
    };

    // seat_raw が現在の合成結果と一致すれば自動モード、差異があれば手動モード
    let manual = raw.value !== '' && raw.value !== compose();
    raw.addEventListener('input', () => { manual = true; });
    [block, row, number].forEach(el => el.addEventListener('input', () => {
        if (!manual) raw.value = compose();
    }));
});
</script>
