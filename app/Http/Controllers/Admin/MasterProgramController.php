<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MasterProgram;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class MasterProgramController extends Controller
{
    public function index(): View
    {
        $masterPrograms = MasterProgram::adminListing();

        $dayTabs = [
            'LUNES' => 'Lunes',
            'MARTES' => 'Martes',
            'MIERCOLES' => 'Miércoles',
            'JUEVES' => 'Jueves',
            'VIERNES' => 'Viernes',
            'SABADO' => 'Sábado',
            'DOMINGO' => 'Domingo',
        ];

        $programsByDay = collect($dayTabs)
            ->mapWithKeys(function (string $label, string $day) use ($masterPrograms): array {
                return [
                    $day => $masterPrograms->where('dia_transmision', $day)->values(),
                ];
            });

        return view('admin.master-programs.index', [
            'masterPrograms' => $masterPrograms,
            'dayTabs' => $dayTabs,
            'programsByDay' => $programsByDay,
            'activeDay' => $this->currentDayKey(),
        ]);
    }

    public function create(): View
    {
        $masterProgram = new MasterProgram([
                'timezone' => 'America/Caracas',
                'duracion_minutos' => 120,
                'activo' => true,
                'vistas_archive' => 0,
                'escuchas_locales' => 0,
                'vistas_totales' => 0,
            ]);

        return view('admin.master-programs.create', [
            'masterProgram' => $masterProgram,
            'defaultNewsIdsText' => '',
            'liveNewsIdsText' => '',
            'previewNewsIdsText' => '',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $masterProgram = MasterProgram::query()->create($this->validated($request));

        return redirect()
            ->route('admin.master-programs.edit', $masterProgram)
            ->with('status', 'Programa maestro creado.');
    }

    public function edit(MasterProgram $masterProgram): View
    {
        return view('admin.master-programs.edit', [
            'masterProgram' => $masterProgram,
            'defaultNewsIdsText' => $this->idListToText($masterProgram->default_news_ids),
            'liveNewsIdsText' => $this->idListToText($masterProgram->live_news_ids),
            'previewNewsIdsText' => $this->idListToText($masterProgram->preview_news_ids),
        ]);
    }

    public function update(Request $request, MasterProgram $masterProgram): RedirectResponse
    {
        $masterProgram->update($this->validated($request, $masterProgram->id));

        return redirect()
            ->route('admin.master-programs.edit', $masterProgram)
            ->with('status', 'Programa maestro actualizado.');
    }

    public function destroy(MasterProgram $masterProgram): RedirectResponse
    {
        $masterProgram->delete();

        return redirect()
            ->route('admin.master-programs.index')
            ->with('status', 'Programa maestro eliminado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'conductor' => ['required', 'string', 'max:255'],
            'dia_transmision' => ['required', Rule::in(['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO', 'DOMINGO'])],
            'hora_transmision' => ['nullable', 'string', 'max:8'],
            'timezone' => ['required', 'string', 'max:255'],
            'duracion_minutos' => ['required', 'integer', 'min:1', 'max:1440'],
            'genero' => ['required', 'string', 'max:255'],
            'caratula_url' => ['nullable', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'live_title' => ['nullable', 'string', 'max:255'],
            'live_description' => ['nullable', 'string'],
            'live_image_url' => ['nullable', 'string', 'max:255'],
            'live_starts_at' => ['nullable', 'date'],
            'live_ends_at' => ['nullable', 'date'],
            'default_news_ids_text' => ['nullable', 'string'],
            'live_news_ids_text' => ['nullable', 'string'],
            'preview_news_ids_text' => ['nullable', 'string'],
            'comentario_predeterminado' => ['nullable', 'string', 'max:255'],
            'red_social1_url' => ['nullable', 'string', 'max:255'],
            'red_social2_url' => ['nullable', 'string', 'max:255'],
            'activo' => ['nullable', 'boolean'],
            'archive_identifier' => ['nullable', 'string', 'max:255'],
            'vistas_archive' => ['nullable', 'integer', 'min:0'],
            'escuchas_locales' => ['nullable', 'integer', 'min:0'],
            'vistas_totales' => ['nullable', 'integer', 'min:0'],
            'stats_updated_at' => ['nullable', 'date'],
            'ruta_ftp' => ['nullable', 'string', 'max:255'],
            'email_notificacion' => ['nullable', 'email', 'max:255'],
            'email_copia_notificacion' => ['nullable', 'email', 'max:255'],
        ]);

        $validated['hora_transmision'] = $this->normalizeTime((string) ($validated['hora_transmision'] ?? ''));
        $validated['live_starts_at'] = $this->normalizeDateTime((string) ($validated['live_starts_at'] ?? ''));
        $validated['live_ends_at'] = $this->normalizeDateTime((string) ($validated['live_ends_at'] ?? ''));
        $validated['stats_updated_at'] = $this->normalizeDateTime((string) ($validated['stats_updated_at'] ?? ''));
        $validated['default_news_ids'] = $this->parseIdList((string) ($validated['default_news_ids_text'] ?? ''));
        $validated['live_news_ids'] = $this->parseIdList((string) ($validated['live_news_ids_text'] ?? ''));
        $validated['preview_news_ids'] = $this->parseIdList((string) ($validated['preview_news_ids_text'] ?? ''));
        $validated['activo'] = $request->boolean('activo', true);
        $validated['caratula_url'] = trim((string) ($validated['caratula_url'] ?? '')) ?: null;
        $validated['descripcion'] = trim((string) ($validated['descripcion'] ?? '')) ?: null;
        $validated['live_title'] = trim((string) ($validated['live_title'] ?? '')) ?: null;
        $validated['live_description'] = trim((string) ($validated['live_description'] ?? '')) ?: null;
        $validated['live_image_url'] = trim((string) ($validated['live_image_url'] ?? '')) ?: null;
        $validated['comentario_predeterminado'] = trim((string) ($validated['comentario_predeterminado'] ?? '')) ?: null;
        $validated['red_social1_url'] = trim((string) ($validated['red_social1_url'] ?? '')) ?: null;
        $validated['red_social2_url'] = trim((string) ($validated['red_social2_url'] ?? '')) ?: null;
        $validated['archive_identifier'] = trim((string) ($validated['archive_identifier'] ?? '')) ?: null;
        $validated['ruta_ftp'] = trim((string) ($validated['ruta_ftp'] ?? '')) ?: null;
        $validated['email_notificacion'] = trim((string) ($validated['email_notificacion'] ?? '')) ?: null;
        $validated['email_copia_notificacion'] = trim((string) ($validated['email_copia_notificacion'] ?? '')) ?: null;

        unset($validated['default_news_ids_text'], $validated['live_news_ids_text'], $validated['preview_news_ids_text']);

        return $validated;
    }

    private function normalizeTime(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $candidate = substr($value, 0, 5);

        try {
            return Carbon::createFromFormat('H:i', $candidate)->format('H:i:s');
        } catch (\Throwable) {
            return $candidate . ':00';
        }
    }

    private function normalizeDateTime(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        return Carbon::parse($value)->toDateTimeString();
    }

    private function currentDayKey(): string
    {
        return match (Carbon::now(config('app.timezone'))->dayOfWeekIso) {
            1 => 'LUNES',
            2 => 'MARTES',
            3 => 'MIERCOLES',
            4 => 'JUEVES',
            5 => 'VIERNES',
            6 => 'SABADO',
            7 => 'DOMINGO',
            default => 'LUNES',
        };
    }

    /**
     * @return array<int, int>|null
     */
    private function parseIdList(string $value): ?array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($value)) ?: [];
        $ids = [];

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '' || ! is_numeric($line)) {
                continue;
            }

            $ids[] = (int) $line;
        }

        $ids = array_values(array_unique($ids));

        return $ids !== [] ? $ids : null;
    }

    /**
     * @param array<int, int>|null $values
     */
    private function idListToText(?array $values): string
    {
        if ($values === null || $values === []) {
            return '';
        }

        return implode("\n", array_map(static fn ($value): string => (string) $value, $values));
    }
}
