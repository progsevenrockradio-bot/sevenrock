<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Album;
use App\Models\GalleryImage;
use App\Models\Product;
use App\Models\Post;
use App\Models\ThemeSetting;
use App\Models\Video;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('admin.dashboard', [
            'settings' => ThemeSetting::current(),
            'stats' => [
                'users' => User::query()->count(),
                'admin_users' => User::query()->where('is_admin', true)->count(),
                'albums' => Album::query()->count(),
                'videos' => Video::query()->count(),
                'gallery_images' => GalleryImage::query()->count(),
                'posts' => Post::query()->count(),
                'products' => Product::query()->count(),
            ],
        ]);
    }
}
