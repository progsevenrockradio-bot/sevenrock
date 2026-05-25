<?php

declare(strict_types=1);

namespace App\Http\Controllers\Talent;

use App\Http\Controllers\Controller;
use App\Models\Talent;
use Illuminate\View\View;

class PublicController extends Controller
{
    public function index(): View
    {
        return view('talentos.explore', [
            'talents' => Talent::query()
                ->withCount('media')
                ->latest('id')
                ->paginate(12),
        ]);
    }
}
