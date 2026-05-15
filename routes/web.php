<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\AlbumController as AdminAlbumController;
use App\Http\Controllers\Admin\EventController as AdminEventController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\GalleryImageController as AdminGalleryImageController;
use App\Http\Controllers\Admin\BandProfileController as AdminBandProfileController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\VideoController as AdminVideoController;
use App\Http\Controllers\Admin\ThemeSettingsController as AdminThemeSettingsController;
use App\Http\Controllers\Admin\PostController as AdminPostController;
use App\Http\Controllers\Admin\SongController as AdminSongController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\SiteController;

Route::get('/', [SiteController::class, 'home'])->name('home');
Route::get('/events', [SiteController::class, 'events'])->name('events');
Route::get('/js_events/{slug}', [SiteController::class, 'eventSingle'])->name('events.single');
Route::get('/discography', [SiteController::class, 'discography'])->name('discography');
Route::get('/js_albums/{slug}', [SiteController::class, 'albumSingle'])->name('albums.single');
Route::get('/videos', [SiteController::class, 'videos'])->name('videos');
Route::get('/js_videos/{slug}', [SiteController::class, 'videoSingle'])->name('videos.single');
Route::get('/gallery', [SiteController::class, 'gallery'])->name('gallery');
Route::get('/js_photo_albums/5', [SiteController::class, 'photoAlbum'])->name('gallery.green-day');
Route::get('/blog', [SiteController::class, 'blog'])->name('blog');
Route::get('/blog-standard', [SiteController::class, 'blogStandard'])->name('blog.standard');
Route::get('/{year}/{month}/{day}/{slug}', [SiteController::class, 'singlePost'])
    ->where(['year' => '\d{4}', 'month' => '\d{2}', 'day' => '\d{2}'])
    ->name('posts.single');
Route::get('/shop', [SiteController::class, 'shop'])->name('shop');
Route::get('/product/{slug}', [SiteController::class, 'productSingle'])->name('products.single');
Route::get('/contact', [SiteController::class, 'contact'])->name('contact');
Route::get('/player/popup', [PlayerController::class, 'show'])->name('player.popup');

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AdminAuthController::class, 'login'])->name('login.store');

    Route::middleware('admin')->group(function (): void {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('/albums', [AdminAlbumController::class, 'index'])->name('albums.index');
        Route::get('/albums/create', [AdminAlbumController::class, 'create'])->name('albums.create');
        Route::post('/albums', [AdminAlbumController::class, 'store'])->name('albums.store');
        Route::get('/albums/{album}/edit', [AdminAlbumController::class, 'edit'])->name('albums.edit');
        Route::put('/albums/{album}', [AdminAlbumController::class, 'update'])->name('albums.update');
        Route::delete('/albums/{album}', [AdminAlbumController::class, 'destroy'])->name('albums.destroy');
        Route::get('/videos', [AdminVideoController::class, 'index'])->name('videos.index');
        Route::get('/videos/create', [AdminVideoController::class, 'create'])->name('videos.create');
        Route::post('/videos', [AdminVideoController::class, 'store'])->name('videos.store');
        Route::get('/videos/{video}/edit', [AdminVideoController::class, 'edit'])->name('videos.edit');
        Route::put('/videos/{video}', [AdminVideoController::class, 'update'])->name('videos.update');
        Route::delete('/videos/{video}', [AdminVideoController::class, 'destroy'])->name('videos.destroy');
        Route::get('/gallery-images', [AdminGalleryImageController::class, 'index'])->name('gallery.index');
        Route::get('/gallery-images/create', [AdminGalleryImageController::class, 'create'])->name('gallery.create');
        Route::post('/gallery-images', [AdminGalleryImageController::class, 'store'])->name('gallery.store');
        Route::get('/gallery-images/{galleryImage}/edit', [AdminGalleryImageController::class, 'edit'])->name('gallery.edit');
        Route::put('/gallery-images/{galleryImage}', [AdminGalleryImageController::class, 'update'])->name('gallery.update');
        Route::delete('/gallery-images/{galleryImage}', [AdminGalleryImageController::class, 'destroy'])->name('gallery.destroy');
        Route::get('/products', [AdminProductController::class, 'index'])->name('products.index');
        Route::get('/products/create', [AdminProductController::class, 'create'])->name('products.create');
        Route::post('/products', [AdminProductController::class, 'store'])->name('products.store');
        Route::get('/products/{product}/edit', [AdminProductController::class, 'edit'])->name('products.edit');
        Route::put('/products/{product}', [AdminProductController::class, 'update'])->name('products.update');
        Route::delete('/products/{product}', [AdminProductController::class, 'destroy'])->name('products.destroy');
        Route::get('/events', [AdminEventController::class, 'index'])->name('events.index');
        Route::get('/events/create', [AdminEventController::class, 'create'])->name('events.create');
        Route::post('/events', [AdminEventController::class, 'store'])->name('events.store');
        Route::get('/events/{event}/edit', [AdminEventController::class, 'edit'])->name('events.edit');
        Route::put('/events/{event}', [AdminEventController::class, 'update'])->name('events.update');
        Route::delete('/events/{event}', [AdminEventController::class, 'destroy'])->name('events.destroy');
        Route::get('/settings', [AdminThemeSettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [AdminThemeSettingsController::class, 'update'])->name('settings.update');
        Route::get('/band-profiles', [AdminBandProfileController::class, 'index'])->name('band-profiles.index');
        Route::get('/band-profiles/create', [AdminBandProfileController::class, 'create'])->name('band-profiles.create');
        Route::post('/band-profiles', [AdminBandProfileController::class, 'store'])->name('band-profiles.store');
        Route::get('/band-profiles/{bandProfile}/edit', [AdminBandProfileController::class, 'edit'])->name('band-profiles.edit');
        Route::put('/band-profiles/{bandProfile}', [AdminBandProfileController::class, 'update'])->name('band-profiles.update');
        Route::delete('/band-profiles/{bandProfile}', [AdminBandProfileController::class, 'destroy'])->name('band-profiles.destroy');
        Route::get('/songs', [AdminSongController::class, 'index'])->name('songs.index');
        Route::get('/songs/create', [AdminSongController::class, 'create'])->name('songs.create');
        Route::post('/songs', [AdminSongController::class, 'store'])->name('songs.store');
        Route::get('/songs/{song}/edit', [AdminSongController::class, 'edit'])->name('songs.edit');
        Route::put('/songs/{song}', [AdminSongController::class, 'update'])->name('songs.update');
        Route::delete('/songs/{song}', [AdminSongController::class, 'destroy'])->name('songs.destroy');
        Route::get('/posts', [AdminPostController::class, 'index'])->name('posts.index');
        Route::get('/posts/create', [AdminPostController::class, 'create'])->name('posts.create');
        Route::post('/posts', [AdminPostController::class, 'store'])->name('posts.store');
        Route::get('/posts/{post}/edit', [AdminPostController::class, 'edit'])->name('posts.edit');
        Route::put('/posts/{post}', [AdminPostController::class, 'update'])->name('posts.update');
        Route::delete('/posts/{post}', [AdminPostController::class, 'destroy'])->name('posts.destroy');
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
    });
});
