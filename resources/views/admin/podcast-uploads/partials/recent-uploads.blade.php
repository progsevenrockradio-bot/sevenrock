@php
    $badgeClasses = [
        'verified' => 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300',
        'synced' => 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300',
        'pending' => 'border-amber-500/30 bg-amber-500/10 text-amber-300',
        'processing' => 'border-amber-500/30 bg-amber-500/10 text-amber-300',
        'partial' => 'border-amber-500/30 bg-amber-500/10 text-amber-300',
        'skipped' => 'border-sky-500/30 bg-sky-500/10 text-sky-300',
        'error' => 'border-rose-500/30 bg-rose-500/10 text-rose-300',
        'failed' => 'border-rose-500/30 bg-rose-500/10 text-rose-300',
        'default' => 'border-[#2b2b2b] bg-[#111111] text-[#9d9d9d]',
    ];

    $badgeLabel = static function (?string $status, string $fallback = 'sin estado'): string {
        return match ($status) {
            'verified' => 'Verificado',
            'synced' => 'Sincronizado',
            'pending' => 'Pendiente',
            'processing' => 'Procesando',
            'partial' => 'Parcial',
            'skipped' => 'Borrador creado',
            'error' => 'Error',
            'failed' => 'Falló',
            default => $fallback,
        };
    };

    $badgeClassFor = static function (?string $status) use ($badgeClasses): string {
        return $badgeClasses[$status ?? ''] ?? $badgeClasses['default'];
    };
@endphp

<div class="space-y-4">
    @forelse ($recentUploads as $upload)
        @php
            $radioStatus = $upload->enviado_radioboss ? 'verified' : (string) ($upload->radioboss_status ?? 'pending');
            $archiveStatus = (string) ($upload->archive_org_status ?? 'pending');
            $deliveryStatus = (string) ($upload->delivery_status ?? 'pending');
            $hasActivePipeline = in_array($radioStatus, ['pending', 'processing', 'uploading'], true)
                || in_array($archiveStatus, ['pending', 'processing', 'uploading'], true)
                || in_array($deliveryStatus, ['pending', 'processing', 'uploading'], true);
        @endphp
        <article
            class="border border-[#242424] bg-[#151515] p-4"
            data-status="{{ $deliveryStatus }}"
            data-podcast-state="{{ $deliveryStatus }}"
            data-podcast-refresh-active="{{ $hasActivePipeline ? '1' : '0' }}"
        >
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="font-display text-sm uppercase tracking-[.12em] text-white">{{ $upload->live_title ?: $upload->titulo_programa }}</div>
                    <div class="mt-1 text-sm text-[#9d9d9d]">{{ $upload->masterProgram?->name ?? 'Sin programa maestro' }}</div>
                    <div class="mt-3 flex flex-wrap gap-2 text-[10px] uppercase tracking-[.18em]">
                        <span class="inline-flex items-center rounded border px-2.5 py-1 {{ $badgeClassFor($radioStatus) }}">
                            RB · {{ $badgeLabel($radioStatus) }}
                        </span>
                        <span class="inline-flex items-center rounded border px-2.5 py-1 {{ $badgeClassFor($archiveStatus) }}">
                            Archive · {{ $badgeLabel($archiveStatus) }}
                        </span>
                        <span class="inline-flex items-center rounded border px-2.5 py-1 {{ $badgeClassFor($deliveryStatus) }}">
                            Envío · {{ $badgeLabel($deliveryStatus) }}
                        </span>
                    </div>
                    @if ($upload->status_message)
                        <div class="mt-2 inline-flex max-w-full rounded border border-[#242424] bg-[rgba(255,255,255,.03)] px-2.5 py-1 text-[10px] uppercase tracking-[.16em] text-[#c7c7c7]">
                            {{ \Illuminate\Support\Str::limit((string) $upload->status_message, 96) }}
                        </div>
                    @endif
                    @if ($upload->radioboss_last_error)
                        <div class="mt-2 max-w-xl text-[11px] leading-5 text-[#ff9e9e]">
                            Último error RadioBOSS: {{ \Illuminate\Support\Str::limit((string) $upload->radioboss_last_error, 180) }}
                        </div>
                    @endif
                    @if ($upload->archive_org_last_error)
                        <div class="mt-2 max-w-xl text-[11px] leading-5 text-[#ff9e9e]">
                            Último error Archive.org: {{ \Illuminate\Support\Str::limit((string) $upload->archive_org_last_error, 180) }}
                        </div>
                    @endif
                </div>

                <div class="flex flex-col items-end gap-2">
                    <span class="rounded border border-[#2b2b2b] px-3 py-1 text-[11px] uppercase tracking-[.18em] text-[#9d9d9d]">
                        Ep. {{ $upload->numero_episodio }}
                    </span>
                    <span class="text-[11px] uppercase tracking-[.18em] text-[#7b7b7b]">
                        {{ optional($upload->fecha_emision)->format('d/m/Y') }}
                    </span>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <form action="{{ route('admin.podcast-uploads.retry', $upload) }}" method="POST">
                    @csrf
                    <button type="submit" class="lucille-button">Reprocesar</button>
                </form>
                <a href="{{ route('admin.podcast-uploads.download', $upload) }}" class="lucille-button">Descargar MP3</a>
                <form
                    action="{{ route('admin.podcast-uploads.destroy', $upload->id) }}"
                    method="POST"
                    data-confirm="¿Eliminar este episodio?"
                    data-confirm-title="Eliminar episodio"
                    data-confirm-action="Eliminar"
                    data-confirm-tone="danger"
                >
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="lucille-button">Eliminar</button>
                </form>
            </div>
        </article>
    @empty
        <p class="text-sm text-[#7b7b7b]">Todavía no hay episodios en esta sección.</p>
    @endforelse
</div>
