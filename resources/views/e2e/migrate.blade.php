<x-app-layout :hide-fab="true" :hide-nav="true">
    <div class="venue-hero">
        <div class="vh-name">名義情報の暗号化</div>
        <div class="vh-sub">セキュリティアップグレード</div>
    </div>

    <div class="d-block" style="margin-bottom:16px;">
        <p style="font-size:13px;line-height:1.8;color:var(--color-ink);">
            FC会員番号・ID・パスワードを、サーバーからも読めない<b>E2E暗号化</b>へ移行します。
            移行が完了するまで名義関連の画面は利用できません。
        </p>
        <p style="font-size:12px;color:#C7414F;font-weight:600;margin-top:8px;">
            移行にはログインパスワードが必要です。復号はこの端末の中だけで行われます。
        </p>
    </div>

    <div data-e2e-migration-banner></div>

    <div id="migration-complete" style="display:none;padding:24px;text-align:center;">
        <div style="font-size:16px;font-weight:700;color:#43A047;margin-bottom:8px;">移行が完了しました</div>
        <a href="{{ route('identities.index') }}" class="btn btn-primary">名義一覧へ</a>
    </div>
</x-app-layout>
