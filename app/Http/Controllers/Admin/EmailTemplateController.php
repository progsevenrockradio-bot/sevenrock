<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class EmailTemplateController extends Controller
{
    private string $emailsPath;

    public function __construct()
    {
        $this->emailsPath = resource_path('views/emails');
    }

    public function index()
    {
        if (!File::exists($this->emailsPath)) {
            File::makeDirectory($this->emailsPath, 0755, true);
        }

        $files = File::allFiles($this->emailsPath);
        $templates = [];

        foreach ($files as $file) {
            if ($file->getExtension() === 'php' && str_ends_with($file->getFilename(), '.blade.php')) {
                $relativePath = $file->getRelativePathname();
                $templates[] = [
                    'name' => str_replace('.blade.php', '', $relativePath),
                    'path' => $relativePath,
                    'encoded_path' => base64_encode($relativePath),
                    'size' => $file->getSize(),
                    'last_modified' => \Carbon\Carbon::createFromTimestamp($file->getMTime()),
                ];
            }
        }

        // Sort templates alphabetically
        usort($templates, fn($a, $b) => strcmp($a['name'], $b['name']));

        return view('admin.email-templates.index', compact('templates'));
    }

    public function edit(string $encodedPath)
    {
        $relativePath = base64_decode($encodedPath);
        $fullPath = $this->emailsPath . '/' . $relativePath;

        if (!File::exists($fullPath) || !str_ends_with($relativePath, '.blade.php')) {
            abort(404, 'Plantilla no encontrada');
        }

        $content = File::get($fullPath);

        return view('admin.email-templates.edit', compact('content', 'relativePath', 'encodedPath'));
    }

    public function update(Request $request, string $encodedPath)
    {
        $request->validate([
            'content' => 'required|string',
        ]);

        $relativePath = base64_decode($encodedPath);
        $fullPath = $this->emailsPath . '/' . $relativePath;

        if (!File::exists($fullPath) || !str_ends_with($relativePath, '.blade.php')) {
            abort(404, 'Plantilla no encontrada');
        }

        File::put($fullPath, $request->input('content'));

        return redirect()->route('admin.email-templates.index')->with('status', 'Plantilla actualizada correctamente.');
    }

    public function sendTest(Request $request)
    {
        $request->validate([
            'test_email' => 'required|email',
        ]);

        try {
            Mail::to($request->input('test_email'))->send(new \App\Mail\TestEmail());
            return redirect()->route('admin.email-templates.index')->with('status', 'Correo de prueba enviado a ' . $request->input('test_email') . ' exitosamente.');
        } catch (\Exception $e) {
            return redirect()->route('admin.email-templates.index')->withErrors(['error' => 'Error al enviar: ' . $e->getMessage()]);
        }
    }
}
