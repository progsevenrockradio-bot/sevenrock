<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PageCountdown;
use Illuminate\Http\Request;

class PageCountdownController extends Controller
{
    public function index()
    {
        $countdowns = PageCountdown::orderBy('id', 'desc')->get();
        return view('admin.page-countdowns.index', compact('countdowns'));
    }

    public function create()
    {
        return view('admin.page-countdowns.create');
    }

    public function store(Request $request)
    {
        // Limpiar la ruta por si pegan la URL completa
        $routePath = $request->input('route_path');
        if (str_starts_with($routePath, 'http')) {
            $parsed = parse_url($routePath);
            $routePath = ltrim($parsed['path'] ?? '', '/');
            $request->merge(['route_path' => $routePath]);
        }

        $validated = $request->validate([
            'route_path' => 'required|string|unique:page_countdowns,route_path',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'active_at' => 'nullable|date',
            'is_enabled' => 'boolean',
        ]);

        $validated['is_enabled'] = $request->has('is_enabled');
        PageCountdown::create($validated);

        return redirect()->route('admin.page-countdowns.index')
                         ->with('success', 'Página en espera creada exitosamente.');
    }

    public function edit(PageCountdown $pageCountdown)
    {
        return view('admin.page-countdowns.edit', compact('pageCountdown'));
    }

    public function update(Request $request, PageCountdown $pageCountdown)
    {
        // Limpiar la ruta por si pegan la URL completa
        $routePath = $request->input('route_path');
        if (str_starts_with($routePath, 'http')) {
            $parsed = parse_url($routePath);
            $routePath = ltrim($parsed['path'] ?? '', '/');
            $request->merge(['route_path' => $routePath]);
        }

        $validated = $request->validate([
            'route_path' => 'required|string|unique:page_countdowns,route_path,' . $pageCountdown->id,
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'active_at' => 'nullable|date',
            'is_enabled' => 'boolean',
        ]);

        $validated['is_enabled'] = $request->has('is_enabled');
        $pageCountdown->update($validated);

        return redirect()->route('admin.page-countdowns.index')
                         ->with('success', 'Configuración actualizada exitosamente.');
    }

    public function destroy(PageCountdown $pageCountdown)
    {
        $pageCountdown->delete();
        return redirect()->route('admin.page-countdowns.index')
                         ->with('success', 'Página en espera eliminada.');
    }
}
