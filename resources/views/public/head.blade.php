<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#1769e0">
<title>{{ $seo['title'] }}</title>
<meta name="description" content="{{ $seo['description'] }}">
<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
<meta name="googlebot" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
<meta property="og:type" content="website">
<meta property="og:locale" content="{{ $locale === 'ur' ? 'ur_PK' : 'en_PK' }}">
<meta property="og:locale:alternate" content="{{ $locale === 'ur' ? 'en_PK' : 'ur_PK' }}">
<meta property="og:site_name" content="Exam Builder">
<meta property="og:title" content="{{ $seo['title'] }}">
<meta property="og:description" content="{{ $seo['description'] }}">
<meta property="og:url" content="{{ $seo['canonical'] }}">
<meta property="og:image" content="{{ $seo['image'] }}">
<meta property="og:image:width" content="1672">
<meta property="og:image:height" content="940">
<meta property="og:image:alt" content="{{ $copy['image_alt'] }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $seo['title'] }}">
<meta name="twitter:description" content="{{ $seo['description'] }}">
<meta name="twitter:image" content="{{ $seo['image'] }}">
<link rel="canonical" href="{{ $seo['canonical'] }}">
@foreach($seo['alternates'] as $language => $url)
<link rel="alternate" hreflang="{{ $language }}" href="{{ $url }}">
@endforeach
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png?v=3">
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png?v=3">
<link rel="manifest" href="/site.webmanifest?v=3">
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet">
@vite(['resources/css/app.css'])
@if($locale === 'ur')
<style>html[dir="rtl"] body{font-family:"Noto Nastaliq Urdu","Urdu Typesetting","Noto Naskh Arabic",Tahoma,serif;line-height:2}h1,h2,h3{line-height:1.8!important}p,li{line-height:2.1}input,a,button{overflow-wrap:anywhere}</style>
@endif
<script type="application/ld+json">{!! json_encode($seo['schema'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
