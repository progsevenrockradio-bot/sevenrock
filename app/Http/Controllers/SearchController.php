<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Album;
use App\Models\Event;
use App\Models\Post;
use App\Models\Product;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class SearchController extends Controller
{
    public function index(Request $request): View
    {
        $query = $request->input('q');
        $results = collect();

        if ($query) {
            $posts = Post::query()
                ->where('title', 'like', "%{$query}%")
                ->orWhere('content', 'like', "%{$query}%")
                ->get();
            $results = $results->merge($posts->map(fn ($item) => ['type' => 'Post', 'data' => $item]));

            $albums = Album::query()->where('title', 'like', "%{$query}%")->get();
            $results = $results->merge($albums->map(fn ($item) => ['type' => 'Album', 'data' => $item]));

            $events = Event::query()->where('title', 'like', "%{$query}%")->get();
            $results = $results->merge($events->map(fn ($item) => ['type' => 'Event', 'data' => $item]));

            $videos = Video::query()->where('title', 'like', "%{$query}%")->get();
            $results = $results->merge($videos->map(fn ($item) => ['type' => 'Video', 'data' => $item]));

            $products = Product::query()->where('name', 'like', "%{$query}%")->get();
            $results = $results->merge($products->map(fn ($item) => ['type' => 'Product', 'data' => $item]));
        }

        return view('pages.search-results', compact('query', 'results'));
    }
}
