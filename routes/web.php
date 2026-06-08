<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\AlbumController as AdminAlbumController;
use App\Http\Controllers\Admin\EventController as AdminEventController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\BandProfileController as AdminBandProfileController;
use App\Http\Controllers\Admin\GalleryImageController as AdminGalleryImageController;
use App\Http\Controllers\Admin\MasterProgramController as AdminMasterProgramController;
use App\Http\Controllers\Admin\OutreachController as AdminOutreachController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\VideoController as AdminVideoController;
use App\Http\Controllers\Admin\TalentAdminController as AdminTalentAdminController;
use App\Http\Controllers\Admin\ThemeSettingsController as AdminThemeSettingsController;
use App\Http\Controllers\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Admin\PostTaxonomyController as AdminPostTaxonomyController;
use App\Http\Controllers\Admin\PostController as AdminPostController;
use App\Http\Controllers\Admin\CommentController as AdminCommentController;
use App\Http\Controllers\Admin\SongController as AdminSongController;
use App\Http\Controllers\Admin\PodcastUploadController as AdminPodcastUploadController;
use App\Http\Controllers\Admin\ProgramCodeController as AdminProgramCodeController;
use App\Http\Controllers\LegacyWordPressUploadController;
use App\Http\Controllers\PostReactionController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\Talent\DashboardController as TalentDashboardController;
use App\Http\Controllers\Talent\AuthController as TalentAuthController;
use App\Http\Controllers\Talent\MediaController as TalentMediaController;
use App\Http\Controllers\Talent\ProfileController as TalentProfileController;
use App\Http\Controllers\Talent\ProductController as TalentProductController;
use App\Http\Controllers\Talent\SubscriptionController as TalentSubscriptionController;
use App\Http\Controllers\Talent\NotificationController as TalentNotificationController;
use App\Http\Controllers\Talent\AlbumController as TalentAlbumController;
use App\Http\Controllers\Talent\PublicProfileController as TalentPublicProfileController;

Route::get('/', [SiteController::class, 'home'])->name('home');
Route::get('/events', [SiteController::class, 'events'])->name('events');
Route::redirect('/eventos', '/events');
Route::get('/events/upcoming', [SiteController::class, 'upcomingEvents'])->name('events.upcoming');
Route::get('/events/past', [SiteController::class, 'pastEvents'])->name('events.past');
Route::get('/events/all', [SiteController::class, 'allEvents'])->name('events.all');
Route::get('/js_events/{slug}', [SiteController::class, 'eventSingle'])->name('events.single');
Route::get('/discography', [SiteController::class, 'discography'])->name('discography');
Route::redirect('/discografia', '/discography');
Route::get('/js_albums/{slug}', [SiteController::class, 'albumSingle'])->name('albums.single');
Route::get('/videos', [SiteController::class, 'videos'])->name('videos');
Route::get('/js_videos/{slug}', [SiteController::class, 'videoSingle'])->name('videos.single');
Route::get('/gallery', [SiteController::class, 'gallery'])->name('gallery');
Route::redirect('/galeria', '/gallery');
Route::get('/js_photo_albums/5', [SiteController::class, 'photoAlbum'])->name('gallery.green-day');
Route::get('/blog', [SiteController::class, 'blog'])->name('blog');
Route::get('/blog-standard', [SiteController::class, 'blogStandard'])->name('blog.standard');
Route::get('/blog/category/{slug}', [SiteController::class, 'blogCategory'])->name('blog.category');
Route::get('/blog/tag/{slug}', [SiteController::class, 'blogTag'])->name('blog.tag');
Route::get('/blog/archives/{year}/{month?}', [SiteController::class, 'blogDateArchive'])
    ->where([
        'year' => '\d{4}',
        'month' => '\d{2}',
    ])
    ->name('blog.archives');
Route::get('/legacy-wp-uploads/{path}', [LegacyWordPressUploadController::class, 'show'])
    ->where('path', '.*')
    ->name('legacy-wp-uploads.show');
Route::get('/{year}/{month}/{day}/{slug}', [SiteController::class, 'singlePost'])
    ->where(['year' => '\d{4}', 'month' => '\d{2}', 'day' => '\d{2}'])
    ->name('posts.single');
Route::get('/shop', [SiteController::class, 'shop'])->name('shop');
Route::get('/product/{slug}', [SiteController::class, 'productSingle'])->name('products.single');
Route::get('/contact', [SiteController::class, 'contact'])->name('contact');
Route::post('/contact', [SiteController::class, 'contactSend'])->middleware(['throttle:contact-form', \App\Http\Middleware\PreventSpamWithHoneypot::class])->name('contact.send');
Route::post('/home-contact', [SiteController::class, 'homeContactSend'])->middleware(['throttle:contact-form', \App\Http\Middleware\PreventSpamWithHoneypot::class])->name('home.contact.send');
Route::get('/player/popup', [PlayerController::class, 'show'])->name('player.popup');
Route::get('/search', [SearchController::class, 'index'])->middleware('throttle:public-search')->name('search');
Route::post('/posts/{post}/comments', [CommentController::class, 'store'])->middleware(['throttle:comment-submit', \App\Http\Middleware\PreventSpamWithHoneypot::class])->name('posts.comments.store');
Route::post('/posts/{post}/like', [PostReactionController::class, 'toggle'])->middleware('throttle:60,1')->name('posts.like');

Route::get("/programas", [SiteController::class, "programs"])->name("programs");
Route::get("/programas/{identifier}", [SiteController::class, "programDetail"])->name("programs.detail");

// Rutas de restablecimiento de contraseña para el panel de administración.
// Se conservan por compatibilidad con enlaces existentes.
Route::get('/admin/forgot-password', [AdminAuthController::class, 'showLinkRequestForm'])->name('admin.password.request');
Route::post('/admin/forgot-password', [AdminAuthController::class, 'sendResetLinkEmail'])->name('admin.password.email');
Route::get('/admin/reset-password/{token}', [AdminAuthController::class, 'showResetForm'])->name('admin.password.reset');
Route::post('/admin/reset-password', [AdminAuthController::class, 'reset'])->name('admin.password.update');

Route::prefix('admin')->name('admin.')->middleware('guest')->group(function (): void {
    Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AdminAuthController::class, 'login'])->middleware('throttle:login')->name('login.store');
});

Route::middleware(['auth', 'admin'])->group(function (): void {
    Route::get('/admin/confirm-password', [AdminAuthController::class, 'showConfirmForm'])->name('password.confirm');
    Route::post('/admin/confirm-password', [AdminAuthController::class, 'confirm'])->middleware('throttle:6,1');
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin', 'audit', 'throttle:admin-actions'])->group(function (): void {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::controller(AdminPostController::class)->prefix('posts')->name('posts.')->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/media', 'uploadMedia')->name('media.store');
        Route::post('/', 'store')->name('store');
        Route::get('/{post}/edit', 'edit')->name('edit');
        Route::put('/{post}', 'update')->name('update');
        Route::delete('/{post}', 'destroy')->name('destroy');
    });

    Route::controller(AdminCommentController::class)->prefix('comments')->name('comments.')->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/{comment}/edit', 'edit')->name('edit');
        Route::put('/{comment}', 'update')->name('update');
        Route::post('/{comment}/approve', 'approve')->name('approve');
        Route::post('/{comment}/unapprove', 'unapprove')->name('unapprove');
        Route::delete('/{comment}', 'destroy')->name('destroy');
    });

    Route::controller(AdminEventController::class)->prefix('events')->name('events.')->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/single', 'preview')->name('single');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{event}/edit', 'edit')->name('edit');
        Route::put('/{event}', 'update')->name('update');
        Route::delete('/{event}', 'destroy')->name('destroy');
    });

    Route::controller(AdminThemeSettingsController::class)->prefix('settings')->name('settings.')->middleware('role:Super Admin')->group(function (): void {
        Route::get('/', 'edit')->middleware('password.confirm')->name('edit');
        Route::get('/manual', 'manual')->withoutMiddleware('role:Super Admin')->name('manual');
        Route::get('/manual/pdf', 'manualPdf')->withoutMiddleware('role:Super Admin')->name('manual.pdf');
        Route::put('/', 'update')->middleware('password.confirm')->name('update');
    });

    Route::controller(AdminAuditLogController::class)->prefix('audit-logs')->name('audit-logs.')->middleware('role:Super Admin')->group(function (): void {
        Route::get('/', 'index')->name('index');
    });

    Route::controller(AdminPostTaxonomyController::class)->prefix('taxonomies')->name('taxonomies.')->group(function (): void {
        Route::get('/{taxonomy}/edit', 'edit')->name('edit');
        Route::post('/', 'store')->name('store');
        Route::put('/{taxonomy}', 'update')->name('update');
        Route::delete('/{taxonomy}', 'destroy')->name('destroy');
    });

    Route::controller(AdminMasterProgramController::class)->prefix('master-programs')->name('master-programs.')->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{masterProgram}/edit', 'edit')->name('edit');
        Route::put('/{masterProgram}', 'update')->name('update');
        Route::delete('/{masterProgram}', 'destroy')->name('destroy');
    });

    Route::prefix('programs')->name('programs.')->controller(AdminProgramCodeController::class)->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/invitations', 'invitations')->name('invitations');
        Route::post('/{program}/generate-code', 'generateCode')->name('generate-code');
        Route::post('/{program}/send-invitation', 'sendInvitation')->name('send-invitation');
    });

    Route::controller(AdminPodcastUploadController::class)->prefix('podcast-uploads')->name('podcast-uploads.')->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/manual', 'manual')->name('manual');
        Route::get('/manual/pdf', 'manualPdf')->name('manual.pdf');
        Route::get('/publicados', 'published')->name('published');
        Route::get('/publicados/imprimir', 'publishedPrint')->name('published.print');
        Route::get('/recent', 'recentUploadsFragment')->name('recent');
        Route::post('/', 'store')->name('store');
        Route::post('/{radioProgram}/retry', 'retry')->name('retry');
        Route::get('/{radioProgram}/download', 'download')->name('download');
        Route::delete('/{id}', 'destroy')->name('destroy');
    });

    Route::controller(AdminSongController::class)->prefix('songs')->name('songs.')->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{song}/edit', 'edit')->name('edit');
        Route::put('/{song}', 'update')->name('update');
        Route::delete('/{song}', 'destroy')->name('destroy');
    });

    Route::controller(AdminAlbumController::class)->prefix('albums')->name('albums.')->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{album}/edit', 'edit')->name('edit');
        Route::put('/{album}', 'update')->name('update');
        Route::delete('/{album}', 'destroy')->name('destroy');
    });

    Route::controller(AdminVideoController::class)->prefix('videos')->name('videos.')->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{video}/edit', 'edit')->name('edit');
        Route::put('/{video}', 'update')->name('update');
        Route::delete('/{video}', 'destroy')->name('destroy');
    });

    Route::controller(AdminGalleryImageController::class)->prefix('gallery')->name('gallery.')->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{galleryImage}/edit', 'edit')->name('edit');
        Route::put('/{galleryImage}', 'update')->name('update');
        Route::delete('/{galleryImage}', 'destroy')->name('destroy');
    });

    Route::controller(AdminBandProfileController::class)->prefix('radio-artists')->name('radio-artists.')->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/search', 'search')->name('search');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::post('/{bandProfile}/auto-generate', 'autoGenerate')->name('auto-generate');
        Route::get('/{bandProfile}/edit', 'edit')->name('edit');
        Route::put('/{bandProfile}', 'update')->name('update');
        Route::delete('/{bandProfile}', 'destroy')->name('destroy');
    });

    Route::prefix('talents')->name('talents.')->group(function (): void {
        Route::get('/', [AdminTalentAdminController::class, 'index'])->name('index');
        Route::patch('/{talent}/toggle-featured', [AdminTalentAdminController::class, 'toggleFeatured'])->name('toggle-featured');
        Route::get('/media', [AdminTalentAdminController::class, 'media'])->name('media');
        Route::delete('/media/{media}', [AdminTalentAdminController::class, 'deleteMedia'])->name('media.destroy');
        Route::get('/{talent}/edit', [AdminTalentAdminController::class, 'edit'])->name('edit');
        Route::put('/{talent}', [AdminTalentAdminController::class, 'update'])->name('update');
        Route::post('/{talent}/suspend', [AdminTalentAdminController::class, 'suspend'])->name('suspend');
        Route::post('/{talent}/activate', [AdminTalentAdminController::class, 'activate'])->name('activate');
    });

    Route::controller(AdminOutreachController::class)->prefix('outreach')->name('outreach.')->group(function (): void {
        Route::get('/', 'index')->name('index');

        Route::get('/templates', 'templates')->name('templates.index');
        Route::get('/templates/create', 'templatesCreate')->name('templates.create');
        Route::post('/templates', 'templatesStore')->name('templates.store');
        Route::post('/templates/preview', 'templatePreview')->name('templates.preview');
        Route::get('/templates/{template}/edit', 'templatesEdit')->name('templates.edit');
        Route::put('/templates/{template}', 'templatesUpdate')->name('templates.update');
        Route::delete('/templates/{template}', 'templatesDestroy')->name('templates.destroy');
        Route::post('/send-test', 'sendTest')->name('send-test');

        Route::get('/contacts', 'contacts')->name('contacts.index');
        Route::get('/contacts/create', 'contactsCreate')->name('contacts.create');
        Route::post('/contacts/import', 'contactsImport')->name('contacts.import');
        Route::post('/contacts', 'contactsStore')->name('contacts.store');
        Route::get('/contacts/{contact}', 'contactsShow')->name('contacts.show');
        Route::get('/contacts/{contact}/edit', 'contactsEdit')->name('contacts.edit');
        Route::put('/contacts/{contact}', 'contactsUpdate')->name('contacts.update');

        Route::get('/campaigns', 'campaigns')->name('campaigns.index');
        Route::get('/campaigns/create', 'campaignsCreate')->name('campaigns.create');
        Route::post('/campaigns', 'campaignsStore')->name('campaigns.store');
        Route::get('/campaigns/{campaign}', 'campaignsShow')->name('campaigns.show');
    });

    Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
});

Route::prefix('talentos')->name('talents.')->group(function (): void {
    Route::get('/', [TalentPublicProfileController::class, 'index'])->middleware('throttle:public-search')->name('explore');

    Route::post('/webhook/{gateway}', [TalentSubscriptionController::class, 'webhook'])->name('payment.webhook');

    Route::middleware('guest:talent')->group(function (): void {
        Route::get('/register', [TalentAuthController::class, 'showRegisterForm'])->name('register');
        Route::post('/register', [TalentAuthController::class, 'register'])->name('register.store')->middleware(['throttle:5,1', \App\Http\Middleware\PreventSpamWithHoneypot::class]);
        Route::get('/login', [TalentAuthController::class, 'showLoginForm'])->name('login');
        Route::post('/login', [TalentAuthController::class, 'login'])->name('login.store')->middleware('throttle:login');
    });

    Route::middleware(['talent'])->group(function (): void {
        Route::get('/dashboard', [TalentDashboardController::class, 'index'])->name('dashboard');
        Route::prefix('store')->name('store.')->group(function (): void {
            Route::get('/', [TalentProductController::class, 'index'])->name('index');
            Route::get('/create', [TalentProductController::class, 'create'])->name('create');
            Route::post('/', [TalentProductController::class, 'store'])->name('store');
            Route::get('/{id}/edit', [TalentProductController::class, 'edit'])->name('edit');
            Route::post('/{id}', [TalentProductController::class, 'update'])->name('update');
            Route::delete('/{id}', [TalentProductController::class, 'destroy'])->name('destroy');
        });
        Route::get('/subscriptions/plans', [TalentSubscriptionController::class, 'selectPlan'])->name('subscriptions.plans');
        Route::post('/subscriptions/checkout', [TalentSubscriptionController::class, 'checkout'])->name('subscriptions.checkout');
        Route::get('/subscriptions/success', [TalentSubscriptionController::class, 'success'])->name('payment.success');
        Route::get('/subscriptions/cancel', [TalentSubscriptionController::class, 'cancel'])->name('payment.cancel');
        Route::get('/profile', [TalentProfileController::class, 'edit'])->name('profile');
        Route::put('/profile', [TalentProfileController::class, 'update'])->name('profile.update');
        Route::get('/notifications', [TalentNotificationController::class, 'edit'])->name('notifications.edit');
        Route::put('/notifications', [TalentNotificationController::class, 'update'])->name('notifications.update');
        Route::get('/media', [TalentMediaController::class, 'index'])->name('media.index');
        Route::post('/media/upload', [TalentMediaController::class, 'upload'])->name('media.upload');
        Route::post('/media', [TalentMediaController::class, 'store'])->name('media.store');
        Route::delete('/media/{id}', [TalentMediaController::class, 'destroy'])->name('media.destroy');
        Route::prefix('albums')->name('albums.')->group(function (): void {
            Route::get('/', [TalentAlbumController::class, 'index'])->name('index');
            Route::get('/create', [TalentAlbumController::class, 'create'])->name('create');
            Route::post('/', [TalentAlbumController::class, 'store'])->name('store');
            Route::get('/{id}/edit', [TalentAlbumController::class, 'edit'])->name('edit');
            Route::post('/{id}', [TalentAlbumController::class, 'update'])->name('update');
            Route::delete('/{id}', [TalentAlbumController::class, 'destroy'])->name('destroy');
        });
        Route::post('/logout', [TalentAuthController::class, 'logout'])->name('logout');
    });

    Route::get('/{bandName}', [TalentPublicProfileController::class, 'show'])->name('show');
    Route::post('/{bandName}/like', [TalentPublicProfileController::class, 'like'])->name('like')->middleware('throttle:10,1');
    Route::post('/{bandName}/comment', [TalentPublicProfileController::class, 'comment'])->name('comment')->middleware([\App\Http\Middleware\PreventSpamWithHoneypot::class, 'throttle:5,1']);
});
