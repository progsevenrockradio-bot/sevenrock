#!/usr/bin/env python3
"""Fix SiteController: convert all Collection/Model cache methods to arrays"""
import re

with open('app/Http/Controllers/SiteController.php', 'r') as f:
    content = f.read()

# 1. Fix cachedGalleryImages: mixed → array, add ->toArray()
old1 = '''    private function cachedGalleryImages(int $limit = 20, int $minutes = 15): mixed
    {
        $version = $this->cacheVersion('gallery');

        return Cache::remember(
            "site.gallery.images.v{$version}.limit{$limit}",
            now()->addMinutes($minutes),
            fn () => \\App\\Models\\TalentMedia::query()
                ->where('type', 'photo')
                ->whereHas('talent', fn ($q) => $q->where('subscription_status', 'active'))
                ->with('talent')
                ->latest()
                ->limit($limit)
                ->get()
        );
    }'''

new1 = '''    private function cachedGalleryImages(int $limit = 20, int $minutes = 15): array
    {
        $version = $this->cacheVersion('gallery');

        return Cache::remember(
            "site.gallery.images.v{$version}.limit{$limit}",
            now()->addMinutes($minutes),
            fn () => \\App\\Models\\TalentMedia::query()
                ->where('type', 'photo')
                ->whereHas('talent', fn ($q) => $q->where('subscription_status', 'active'))
                ->with('talent')
                ->latest()
                ->limit($limit)
                ->get()
                ->toArray()
        );
    }'''

if old1 in content:
    content = content.replace(old1, new1)
    print('1. cachedGalleryImages fixed')
else:
    print('1. cachedGalleryImages NOT FOUND')

# 2. Fix cachedLatestAlbum: ?Album → ?array, add ->toArray()
old2 = '''    private function cachedLatestAlbum(int $minutes = 15): ?Album
    {
        $version = $this->cacheVersion('albums');

        return Cache::remember(
            "site.albums.latest.v{$version}",
            now()->addMinutes($minutes),
            fn () => Album::query()->latest('released_at')->first()
        );
    }'''

new2 = '''    private function cachedLatestAlbum(int $minutes = 15): ?array
    {
        $version = $this->cacheVersion('albums');

        return Cache::remember(
            "site.albums.latest.v{$version}",
            now()->addMinutes($minutes),
            fn () => Album::query()->latest('released_at')->first()?->toArray()
        );
    }'''

if old2 in content:
    content = content.replace(old2, new2)
    print('2. cachedLatestAlbum fixed')
else:
    print('2. cachedLatestAlbum NOT FOUND')

# 3. Fix cachedAlbumBySlug: ?Album → ?array
old3 = '''    private function cachedAlbumBySlug(string $slug): ?Album
    {
        $version = $this->cacheVersion('albums');

        return Cache::remember(
            "site.albums.single.admin.{$slug}.v{$version}",
            now()->addMinutes(20),
            fn () => Album::query()->where('slug', $slug)->first()
        );
    }'''

new3 = '''    private function cachedAlbumBySlug(string $slug): ?array
    {
        $version = $this->cacheVersion('albums');

        return Cache::remember(
            "site.albums.single.admin.{$slug}.v{$version}",
            now()->addMinutes(20),
            fn () => Album::query()->where('slug', $slug)->first()?->toArray()
        );
    }'''

if old3 in content:
    content = content.replace(old3, new3)
    print('3. cachedAlbumBySlug fixed')
else:
    print('3. cachedAlbumBySlug NOT FOUND')

# 4. Fix cachedTalentAlbumBySlug: ?TalentAlbum → ?array
old4 = '''    private function cachedTalentAlbumBySlug(string $slug): ?TalentAlbum
    {
        $version = $this->cacheVersion('albums');

        return Cache::remember(
            "site.albums.single.talent.{$slug}.v{$version}",
            now()->addMinutes(20),
            fn () => TalentAlbum::query()->where('slug', $slug)->with('talent.media')->first()
        );
    }'''

new4 = '''    private function cachedTalentAlbumBySlug(string $slug): ?array
    {
        $version = $this->cacheVersion('albums');

        return Cache::remember(
            "site.albums.single.talent.{$slug}.v{$version}",
            now()->addMinutes(20),
            fn () => TalentAlbum::query()->where('slug', $slug)->with('talent.media')->first()?->toArray()
        );
    }'''

if old4 in content:
    content = content.replace(old4, new4)
    print('4. cachedTalentAlbumBySlug fixed')
else:
    print('4. cachedTalentAlbumBySlug NOT FOUND')

# Write
with open('app/Http/Controllers/SiteController.php', 'w') as f:
    f.write(content)

print('DONE')
