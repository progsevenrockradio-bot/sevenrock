<?php
$content = file_get_contents('app/Http/Controllers/SiteController.php');

// 1. cachedGalleryImages: mixed → array, add ->toArray()
$old1 = <<<'PHP'
    private function cachedGalleryImages(int $limit = 20, int $minutes = 15): mixed
    {
        $version = $this->cacheVersion('gallery');

        return Cache::remember(
            "site.gallery.images.v{$version}.limit{$limit}",
            now()->addMinutes($minutes),
            fn () => \App\Models\TalentMedia::query()
                ->where('type', 'photo')
                ->whereHas('talent', fn ($q) => $q->where('subscription_status', 'active'))
                ->with('talent')
                ->latest()
                ->limit($limit)
                ->get()
        );
    }
PHP;
$new1 = <<<'PHP'
    private function cachedGalleryImages(int $limit = 20, int $minutes = 15): array
    {
        $version = $this->cacheVersion('gallery');

        return Cache::remember(
            "site.gallery.images.v{$version}.limit{$limit}",
            now()->addMinutes($minutes),
            fn () => \App\Models\TalentMedia::query()
                ->where('type', 'photo')
                ->whereHas('talent', fn ($q) => $q->where('subscription_status', 'active'))
                ->with('talent')
                ->latest()
                ->limit($limit)
                ->get()
                ->toArray()
        );
    }
PHP;
if (str_contains($content, $old1)) {
    $content = str_replace($old1, $new1, $content);
    echo "1. cachedGalleryImages: FIXED\n";
} else {
    echo "1. cachedGalleryImages: NOT FOUND\n";
}

// 2. cachedLatestAlbum: ?Album → ?array
$old2 = <<<'PHP'
    private function cachedLatestAlbum(int $minutes = 15): ?Album
    {
        $version = $this->cacheVersion('albums');

        return Cache::remember(
            "site.albums.latest.v{$version}",
            now()->addMinutes($minutes),
            fn () => Album::query()->latest('released_at')->first()
        );
    }
PHP;
$new2 = <<<'PHP'
    private function cachedLatestAlbum(int $minutes = 15): ?array
    {
        $version = $this->cacheVersion('albums');

        return Cache::remember(
            "site.albums.latest.v{$version}",
            now()->addMinutes($minutes),
            fn () => Album::query()->latest('released_at')->first()?->toArray()
        );
    }
PHP;
if (str_contains($content, $old2)) {
    $content = str_replace($old2, $new2, $content);
    echo "2. cachedLatestAlbum: FIXED\n";
} else {
    echo "2. cachedLatestAlbum: NOT FOUND\n";
}

// 3. cachedAlbumBySlug: ?Album → ?array
$old3 = <<<'PHP'
    private function cachedAlbumBySlug(string $slug): ?Album
    {
        $version = $this->cacheVersion('albums');

        return Cache::remember(
            "site.albums.single.admin.{$slug}.v{$version}",
            now()->addMinutes(20),
            fn () => Album::query()->where('slug', $slug)->first()
        );
    }
PHP;
$new3 = <<<'PHP'
    private function cachedAlbumBySlug(string $slug): ?array
    {
        $version = $this->cacheVersion('albums');

        return Cache::remember(
            "site.albums.single.admin.{$slug}.v{$version}",
            now()->addMinutes(20),
            fn () => Album::query()->where('slug', $slug)->first()?->toArray()
        );
    }
PHP;
if (str_contains($content, $old3)) {
    $content = str_replace($old3, $new3, $content);
    echo "3. cachedAlbumBySlug: FIXED\n";
} else {
    echo "3. cachedAlbumBySlug: NOT FOUND\n";
}

// 4. cachedTalentAlbumBySlug: ?TalentAlbum → ?array
$old4 = <<<'PHP'
    private function cachedTalentAlbumBySlug(string $slug): ?TalentAlbum
    {
        $version = $this->cacheVersion('albums');

        return Cache::remember(
            "site.albums.single.talent.{$slug}.v{$version}",
            now()->addMinutes(20),
            fn () => TalentAlbum::query()->where('slug', $slug)->with('talent.media')->first()
        );
    }
PHP;
$new4 = <<<'PHP'
    private function cachedTalentAlbumBySlug(string $slug): ?array
    {
        $version = $this->cacheVersion('albums');

        return Cache::remember(
            "site.albums.single.talent.{$slug}.v{$version}",
            now()->addMinutes(20),
            fn () => TalentAlbum::query()->where('slug', $slug)->with('talent.media')->first()?->toArray()
        );
    }
PHP;
if (str_contains($content, $old4)) {
    $content = str_replace($old4, $new4, $content);
    echo "4. cachedTalentAlbumBySlug: FIXED\n";
} else {
    echo "4. cachedTalentAlbumBySlug: NOT FOUND\n";
}

file_put_contents('app/Http/Controllers/SiteController.php', $content);
echo "FILE WRITTEN\n";
