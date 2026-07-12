<x-app-layout>
    <a href="{{ route('lots.index') }}" class="detail-back">‹ グループ一覧へ戻る</a>

    <div class="venue-hero" style="display:flex;align-items:center;justify-content:space-between;">
        <div>
            <div class="vh-name">{{ $idolGroup?->name ?? '未分類' }}</div>
            <div class="vh-sub">申込中のツアー</div>
        </div>
        <button type="button" id="lot-edit-toggle" class="copy-btn" style="font-size:11px;padding:4px 10px;">編集</button>
    </div>

    <div class="link-row">
        <a href="{{ route('lots.create') }}" class="primary">＋ 申込を登録</a>
    </div>

    <div class="sec-label">ツアー</div>
    @forelse($tours as $row)
    <div class="d-block" style="margin-bottom:6px;padding:10px 14px;">
        <a href="{{ route('lots.tour', $row->tour) }}" style="text-decoration:none;color:inherit;display:block;">
            <div style="font-size:14px;font-weight:600;">{{ $row->tour->name }}</div>
            <div style="font-size:12px;color:var(--color-ink-sub);">{{ $row->has_pending ? '当落待ちあり' : '発表済' }}</div>
        </a>
        <div class="lot-edit-controls" style="display:none;margin-top:8px;">
            @if($row->tour->canBeDeleted())
            <form method="POST" action="{{ route('tours.destroy', $row->tour) }}" style="display:inline;"
                  onsubmit="return confirm('「{{ $row->tour->name }}」を削除しますか？')">
                @csrf @method('DELETE')
                <button type="submit" class="copy-btn" style="font-size:10px;padding:2px 8px;color:#C7414F;">ツアーを削除</button>
            </form>
            @else
            <span style="font-size:10px;color:var(--color-ink-sub);">日程が登録されているため削除できません</span>
            @endif
        </div>
    </div>
    @empty
    <div class="empty-state" style="padding:24px;">このグループにはまだ申込がありません</div>
    @endforelse

<script nonce="{{ $cspNonce ?? '' }}">
document.addEventListener('DOMContentLoaded', function(){
    var btn = document.getElementById('lot-edit-toggle');
    var editing = false;
    if(!btn) return;
    btn.addEventListener('click', function(){
        editing = !editing;
        btn.textContent = editing ? '完了' : '編集';
        document.querySelectorAll('.lot-edit-controls').forEach(function(el){
            el.style.display = editing ? '' : 'none';
        });
    });
});
</script>
</x-app-layout>
