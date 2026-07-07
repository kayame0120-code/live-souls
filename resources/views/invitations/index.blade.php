<x-app-layout :hide-header="true" :hide-fab="true">
    <x-slot:pageHeader>
        <div class="page-header">
            <a href="{{ route('home') }}" class="back">← 戻る</a>
            <h1>招待管理</h1>
        </div>
    </x-slot:pageHeader>

    <form method="POST" action="{{ route('invitations.store') }}" style="margin-bottom:20px;">
        @csrf
        <button type="submit" class="btn btn-primary">＋ 招待コードを発行</button>
    </form>

    @forelse($invitations as $invitation)
    <div class="card">
        <div style="display:flex; align-items:flex-start; gap:12px;">
            <div style="flex:1; min-width:0;">
                <div style="font-size:12px; font-family:monospace; word-break:break-all; color:var(--color-ink);">
                    {{ url('/register/' . $invitation->code) }}
                </div>
                <div style="font-size:11px; color:var(--color-ink-sub); margin-top:4px;">
                    発行: {{ $invitation->created_at->format('Y.m.d H:i') }}
                </div>
            </div>
            <div>
                @if($invitation->used_by)
                    <span class="badge ok">使用済</span>
                @elseif($invitation->expires_at && $invitation->expires_at->isPast())
                    <span class="badge ok">失効</span>
                @else
                    <form method="POST" action="{{ route('invitations.destroy', $invitation) }}"
                          onsubmit="return confirm('この招待コードを失効させますか？')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-secondary btn-sm" style="color:#C7414F;">失効</button>
                    </form>
                @endif
            </div>
        </div>
        @if($invitation->used_by)
        <div style="font-size:11px; color:var(--color-ink-sub); margin-top:6px;">
            使用者: {{ optional($invitation->usedByUser)->name }} ({{ optional($invitation->used_at)->format('Y.m.d') }})
        </div>
        @endif
    </div>
    @empty
    <div class="empty-state">発行した招待コードはありません</div>
    @endforelse
</x-app-layout>
