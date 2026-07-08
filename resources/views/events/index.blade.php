<x-app-layout :hide-fab="true">
    <div class="ev-lead">
        公演情報はメンバー全員で持ち寄る共有台帳です。参戦・申込のときはここから公演を選ぶだけで日付・会場が入ります。
    </div>
    <div class="ev-actions">
        <a href="{{ route('events.create') }}" class="ev-new">＋ 公演を追加</a>
        <a href="{{ route('events.import') }}" class="ev-import">一覧を貼って一括登録</a>
    </div>

    <div class="sec-label">今後の公演</div>
    @forelse($upcoming as $event)
        @include('events._ev', ['event' => $event])
    @empty
        <div class="empty-state" style="padding:24px;">今後の公演はありません</div>
    @endforelse

    @if($past->isNotEmpty())
    <div class="sec-label">過去の公演</div>
    @foreach($past as $event)
        @include('events._ev', ['event' => $event])
    @endforeach
    @endif
</x-app-layout>
