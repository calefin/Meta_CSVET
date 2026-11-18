{{-- Google AdSense (Auto Ads). Só carrega em produção, para não gerar
     impressões/cliques inválidos a partir do ambiente local. O ads.txt
     correspondente fica em public/ads.txt. --}}
@if (app()->environment('production') && filled(config('services.adsense.client')))
    <meta name="google-adsense-account" content="{{ config('services.adsense.client') }}">
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client={{ config('services.adsense.client') }}" crossorigin="anonymous"></script>
@endif
