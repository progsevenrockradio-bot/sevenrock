<x-layouts.site>
@php $ui = $themeAppearance['ui_texts']; @endphp
@foreach ($posts as $post)
<article>{{ $post->title }}</article>
@endforeach
</x-layouts.site>
