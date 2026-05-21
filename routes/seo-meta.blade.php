@props([
    'title' => config('app.name', 'Seven Rock Radio'),
    'description' => 'Seven Rock Radio: Tu estación de radio online con lo mejor del rock, eventos, discografía, videos y blog.',
    'image' => asset('img/default-og-image.jpg'), // Asegúrate de tener una imagen por defecto
    'url' => url()->current(),
    'type' => 'website',
])

<title>{{ $title }}</title>
<meta name="description" content="{{ $description }}">
<link rel="canonical" href="{{ $url }}">

{{-- Open Graph / Facebook --}}
<meta property="og:type" content="{{ $type }}">
<meta property="og:url" content="{{ $url }}">
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:image" content="{{ $image }}">

{{-- Twitter --}}
<meta property="twitter:card" content="summary_large_image">
<meta property="twitter:url" content="{{ $url }}">
<meta property="twitter:title" content="{{ $title }}">
<meta property="twitter:description" content="{{ $description }}">
<meta property="twitter:image" content="{{ $image }}">

{{-- Puedes añadir más meta tags aquí, como para Google Scholar, etc. --}}