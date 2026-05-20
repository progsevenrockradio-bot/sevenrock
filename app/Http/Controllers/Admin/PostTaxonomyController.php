<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PostTaxonomy;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class PostTaxonomyController extends Controller
{
    public function edit(PostTaxonomy $taxonomy): View
    {
        return view('admin.taxonomies.edit', [
            'taxonomy' => $taxonomy,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in([PostTaxonomy::TYPE_CATEGORY, PostTaxonomy::TYPE_TAG])],
            'name' => ['required', 'string', 'max:80'],
        ]);

        $name = trim($validated['name']);

        PostTaxonomy::query()->updateOrCreate(
            [
                'type' => $validated['type'],
                'slug' => Str::slug($name),
            ],
            [
                'name' => $name,
            ]
        );

        return back()->with('status', 'Taxonomía guardada.');
    }

    public function update(Request $request, PostTaxonomy $taxonomy): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in([PostTaxonomy::TYPE_CATEGORY, PostTaxonomy::TYPE_TAG])],
            'name' => ['required', 'string', 'max:80'],
        ]);

        $name = trim($validated['name']);

        $taxonomy->update([
            'type' => $validated['type'],
            'name' => $name,
            'slug' => Str::slug($name),
        ]);

        return redirect()->to(route('admin.dashboard').'#taxonomias')
            ->with('status', 'Taxonomía actualizada.');
    }

    public function destroy(PostTaxonomy $taxonomy): RedirectResponse
    {
        $taxonomy->delete();

        return back()->with('status', 'Taxonomía eliminada.');
    }
}
