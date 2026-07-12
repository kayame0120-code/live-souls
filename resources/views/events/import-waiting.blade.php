<x-app-layout :hide-fab="true" :hide-nav="true">
    <x-slot:pageHeader>
        <div class="page-header">
            <a href="{{ route('events.import') }}" class="back">← 戻る</a>
            <h1>AI解析中</h1>
        </div>
    </x-slot:pageHeader>

    <div id="waiting" style="text-align:center;padding:40px 20px;">
        <div style="font-size:16px;font-weight:700;margin-bottom:8px;">AI解析中...</div>
        <div style="font-size:13px;color:var(--color-ink-sub);line-height:1.8;">
            画像やテキストを解析しています。<br>このままお待ちください。
        </div>
        <div id="progress-dots" style="margin-top:16px;font-size:20px;letter-spacing:4px;">●○○</div>
    </div>

    <div id="error-state" style="display:none;text-align:center;padding:40px 20px;">
        <div style="font-size:16px;font-weight:700;color:#C7414F;margin-bottom:8px;">解析に失敗しました</div>
        <div id="error-message" style="font-size:13px;color:var(--color-ink-sub);margin-bottom:16px;"></div>
        <a href="{{ route('events.import') }}" class="btn btn-primary">やり直す</a>
    </div>

<script nonce="{{ $cspNonce ?? '' }}">
(function(){
    var cacheKey = @json($cacheKey);
    var type = @json($type);
    var dots = ['●○○','○●○','○○●'];
    var di = 0;
    var dotsEl = document.getElementById('progress-dots');

    var dotInterval = setInterval(function(){
        di = (di + 1) % dots.length;
        if(dotsEl) dotsEl.textContent = dots[di];
    }, 500);

    function poll(){
        fetch('{{ route("events.import.poll") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({cache_key: cacheKey})
        })
        .then(function(r){ return r.json(); })
        .then(function(data){
            if(data.status === 'processing'){
                setTimeout(poll, 2000);
                return;
            }
            clearInterval(dotInterval);
            if(data.status === 'completed'){
                // 確認画面へ遷移（結果をフォームPOSTで渡す）
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = '{{ route("events.import.store") }}';
                form.style.display = 'none';

                var csrf = document.createElement('input');
                csrf.name = '_token'; csrf.value = document.querySelector('meta[name="csrf-token"]').content;
                form.appendChild(csrf);

                var result = data.result || {};
                // ツアー名
                var tn = document.createElement('input');
                tn.name = 'tour_name'; tn.value = result.tour || '';
                form.appendChild(tn);

                // 各行
                (result.events || []).forEach(function(ev, i){
                    ['event_label','event_date','start_time','venue_name'].forEach(function(k){
                        var inp = document.createElement('input');
                        inp.name = 'rows['+i+']['+k+']';
                        inp.value = k === 'venue_name' ? (ev.venue || '') : (ev[k] || '');
                        form.appendChild(inp);
                    });
                    var inc = document.createElement('input');
                    inc.name = 'rows['+i+'][include]'; inc.value = ev.event_date ? '1' : '0';
                    form.appendChild(inc);
                });

                // 確認画面表示のために直接importStoreへ送るのではなく、
                // 結果をsessionに入れてimport-confirmにリダイレクト
                // → 簡易方式: hidden formで確認画面へPOST（ステートレスのまま）
                document.body.appendChild(form);
                form.submit();
            } else {
                document.getElementById('waiting').style.display = 'none';
                document.getElementById('error-state').style.display = '';
                document.getElementById('error-message').textContent = data.error || '不明なエラーが発生しました';
            }
        })
        .catch(function(){
            setTimeout(poll, 3000);
        });
    }

    setTimeout(poll, 2000);
})();
</script>
</x-app-layout>
