<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GalleryImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class GalleryImageController extends Controller
{
    public function index(): View
    {
        return view('admin.gallery.index', [
            'images' => GalleryImage::query()->ordered()->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.gallery.create', [
            'image' => new GalleryImage([
                'sort_order' => (int) (GalleryImage::query()->max('sort_order') ?? 0) + 1,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['image'] = $this->resolveImage($request, null, $data['image'] ?? null);

        GalleryImage::query()->create($data);

        return redirect()->route('admin.gallery.index')->with('status', 'Gallery image created.');
    }

    public function edit(GalleryImage $galleryImage): View
    {
        return view('admin.gallery.edit', [
            'image' => $galleryImage,
        ]);
    }

    public function update(Request $request, GalleryImage $galleryImage): RedirectResponse
    {
        $data = $this->validated($request);
        $data['image'] = $this->resolveImage($request, $galleryImage->image, $data['image'] ?? null);

        $galleryImage->update($data);

        return redirect()->route('admin.gallery.index')->with('status', 'Gallery image updated.');
    }

    public function destroy(GalleryImage $galleryImage): RedirectResponse
    {
        $this->deleteUploaded($galleryImage->image);
        $galleryImage->delete();

        return redirect()->route('admin.gallery.index')->with('status', 'Gallery image deleted.');
    }

    private function validated(Request $request): array
    {
        return tap($request->validate([
            'image' => ['nullable', 'string', 'max:2048', 'required_without:image_file'],
            'image_file' => ['nullable', 'image', 'max:6144'],
            'caption' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]), function (array &$validated): void {
            unset($validated['image_file']);
        });
    }

    private function resolveImage(Request $request, ?string $current, ?string $input): string
    {
        if ($request->hasFile('image_file')) {
            $this->deleteUploaded($current);

            return $request->file('image_file')->store('catalog/gallery', 'public');
        }

        return trim((string) $input) !== '' ? trim((string) $input) : (string) $current;
    }

    private function deleteUploaded(?string $path): void
    {
        if (! $path || str_starts_with($path, 'assets/') || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return;
        }

        Storage::disk('public')->delete($path);
    }
}
