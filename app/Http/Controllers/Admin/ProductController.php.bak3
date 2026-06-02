<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\FileUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(): View
    {
        return view('admin.products.index', [
            'products' => Product::query()->ordered()->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.products.create', [
            'product' => new Product([
                'price' => 30,
                'regular_price' => null,
                'category' => 'T-SHIRTS',
                'description' => '',
                'is_sale' => false,
                'is_published' => true,
                'sort_order' => 0,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $data['slug'] ?: Str::slug($data['title']);
        $data['image'] = $this->resolveImage($request, null, $data['image'] ?? null);

        Product::query()->create($data);

        return redirect()->route('admin.products.index')->with('status', 'Product created.');
    }

    public function edit(Product $product): View
    {
        return view('admin.products.edit', compact('product'));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $this->validated($request, $product->id);
        $data['slug'] = $data['slug'] ?: Str::slug($data['title']);
        $data['image'] = $this->resolveImage($request, $product->image, $data['image'] ?? null);

        $product->update($data);

        return redirect()->route('admin.products.index')->with('status', 'Product updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->deleteUploaded($product->image);
        $product->delete();

        return redirect()->route('admin.products.index')->with('status', 'Product deleted.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('products', 'slug')->ignore($ignoreId),
            ],
            'image' => ['nullable', 'string', 'max:2048', 'required_without:image_file'],
            'image_file' => ['nullable', 'image', 'max:6144'],
            'price' => ['required', 'numeric', 'min:0'],
            'regular_price' => ['nullable', 'numeric', 'min:0', 'gte:price'],
            'category' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_sale' => ['nullable', 'boolean'],
            'is_published' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['is_sale'] = $request->boolean('is_sale', false);
        $validated['is_published'] = $request->boolean('is_published', true);
        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);
        unset($validated['image_file']);

        return $validated;
    }

    private function resolveImage(Request $request, ?string $current, ?string $input): string
    {
        if ($request->hasFile('image_file')) {
            $this->deleteUploaded($current);

            return app(FileUploadService::class)->upload($request->file('image_file'), 'catalog/products')['key'];
        }

        return trim((string) $input) !== '' ? trim((string) $input) : (string) $current;
    }

    private function deleteUploaded(?string $path): void
    {
        if (! $path || str_starts_with($path, 'assets/') || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return;
        }

        app(FileUploadService::class)->delete($path);
    }
}
