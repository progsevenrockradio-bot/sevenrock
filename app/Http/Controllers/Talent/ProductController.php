<?php

declare(strict_types=1);

namespace App\Http\Controllers\Talent;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\BackblazeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(): View
    {
        $talent = Auth::guard('talent')->user();

        abort_unless($talent, 403);

        return view('talentos.store.index', [
            'talent' => $talent,
            'products' => $talent->products()->latest()->get(),
        ]);
    }

    public function create(): View
    {
        $talent = Auth::guard('talent')->user();
        abort_unless($talent, 403);

        return view('talentos.store.create', [
            'talent' => $talent,
            'product' => new Product([
                'price' => 0,
                'regular_price' => null,
                'category' => 'Talentos',
                'description' => '',
                'is_sale' => false,
                'is_published' => true,
                'is_talent_product' => true,
                'stock' => null,
            ]),
        ]);
    }

    public function store(Request $request, BackblazeService $backblaze): RedirectResponse
    {
        $talent = Auth::guard('talent')->user();
        abort_unless($talent, 403);

        $validated = $this->validated($request);
        $validated['slug'] = $this->uniqueSlug($validated['slug'] ?: Str::slug($validated['title']));
        $validated['talent_id'] = $talent->id;
        $validated['is_talent_product'] = true;
        $validated['image'] = $this->uploadImage($request, $backblaze, $talent->id);

        Product::query()->create($validated);

        return redirect()->route('talentos.store.index')->with('status', 'Producto creado.');
    }

    public function edit(string $id): View
    {
        $talent = Auth::guard('talent')->user();
        abort_unless($talent, 403);

        $product = Product::query()
            ->fromTalent($talent)
            ->whereKey((int) $id)
            ->firstOrFail();

        return view('talentos.store.edit', [
            'talent' => $talent,
            'product' => $product,
        ]);
    }

    public function update(Request $request, string $id, BackblazeService $backblaze): RedirectResponse
    {
        $talent = Auth::guard('talent')->user();
        abort_unless($talent, 403);

        $product = Product::query()
            ->fromTalent($talent)
            ->whereKey((int) $id)
            ->firstOrFail();

        $validated = $this->validated($request, $product->id);
        $validated['slug'] = $this->uniqueSlug($validated['slug'] ?: Str::slug($validated['title']), $product->id);
        $validated['image'] = $this->uploadImage($request, $backblaze, $talent->id, $product->image);
        $validated['talent_id'] = $talent->id;
        $validated['is_talent_product'] = true;

        $product->update($validated);

        return redirect()->route('talentos.store.index')->with('status', 'Producto actualizado.');
    }

    public function destroy(string $id, BackblazeService $backblaze): RedirectResponse
    {
        $talent = Auth::guard('talent')->user();
        abort_unless($talent, 403);

        $product = Product::query()
            ->fromTalent($talent)
            ->whereKey((int) $id)
            ->firstOrFail();

        $this->deleteImage($backblaze, $product->image);
        $product->delete();

        return redirect()->route('talentos.store.index')->with('status', 'Producto eliminado.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'image_file' => ['nullable', 'image', 'max:6144'],
            'external_payment_url' => ['required', 'url', 'max:2048'],
            'external_payment_label' => ['nullable', 'string', 'max:255'],
            'stock' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function uniqueSlug(string $slug, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($slug) ?: 'product';
        $candidate = $baseSlug;
        $counter = 2;

        while (Product::query()
            ->where('slug', $candidate)
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $candidate = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $candidate;
    }

    private function uploadImage(Request $request, BackblazeService $backblaze, int $talentId, ?string $current = null): ?string
    {
        if (! $request->hasFile('image_file')) {
            return $current;
        }

        if (filled($current)) {
            try {
                $backblaze->delete((string) $current);
            } catch (\Throwable) {
                //
            }
        }

        $upload = $backblaze->upload(
            $request->file('image_file'),
            "talents/{$talentId}/store"
        );

        return $upload['key'] ?: $current;
    }

    private function deleteImage(BackblazeService $backblaze, ?string $key): void
    {
        if (! filled($key)) {
            return;
        }

        try {
            $backblaze->delete((string) $key);
        } catch (\Throwable) {
            //
        }
    }
}
