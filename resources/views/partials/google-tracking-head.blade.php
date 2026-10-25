@if($googleTracking)
    <script id="culbertson-google-context">
    window.dataLayer=window.dataLayer||[];
    window.gtag=window.gtag||function(){dataLayer.push(arguments);};
    (function(){
        function clean(value){if(!value)return '';try{var u=new URL(value,location.origin);return u.origin+u.pathname;}catch(e){return '';}}
        gtag('set',{page_location:clean(location.href),page_referrer:clean(document.referrer),allow_google_signals:false,allow_ad_personalization_signals:false});
    })();
    </script>
    @if($googleTracking['useContainer'])
    <!-- Google Tag Manager -->
    <script id="culbertson-gtm-snippet">(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','{{ $googleTracking['containerId'] }}');</script>
    <!-- End Google Tag Manager -->
    @endif
    <!-- Google tag (gtag.js) -->
    <script id="culbertson-ads-loader" async src="https://www.googletagmanager.com/gtag/js?id={{ $googleTracking['adsId'] }}"></script>
    <script id="culbertson-ads-config">
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', '{{ $googleTracking['adsId'] }}');
    </script>
    <script id="culbertson-tracking-config" type="application/json">{!! json_encode($googleTracking,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
    <script defer src="{{ asset('tracking.js') }}?v={{ filemtime(public_path('tracking.js')) }}"></script>
@endif
