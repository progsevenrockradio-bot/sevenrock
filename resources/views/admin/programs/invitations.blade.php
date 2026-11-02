<x-layouts.admin title="Envío rápido de invitaciones">
    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="font-display text-2xl font-bold uppercase tracking-wider text-white">Envío rápido de invitaciones</h1>
                <p class="mt-1 text-sm text-zinc-400">Tarjetas rápidas para regenerar códigos y enviar invitaciones individuales a productores por programa.</p>
            </div>
            <a href="{{ route('admin.programs.index') }}" class="inline-flex h-9 items-center gap-2 rounded-lg border border-white/10 bg-white/5 px-3.5 text-xs font-medium text-zinc-200 transition hover:bg-white/10 hover:text-white">
                ← Volver a invitaciones
            </a>
        </div>

        <section class="grid gap-4 md:grid-cols-2">
            @foreach ($programs as $program)
                <div class="flex flex-col justify-between rounded-xl border border-white/10 bg-[#101012] p-5 transition hover:border-white/15">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <span class="inline-flex items-center rounded-md border border-white/10 bg-white/5 px-2 py-0.5 font-mono text-xs text-zinc-300">
                                {{ $program->program_code ?: 'Sin código' }}
                            </span>
                            <h2 class="mt-2 font-display text-lg font-bold uppercase tracking-wider text-white truncate">{{ $program->name }}</h2>
                            <p class="mt-0.5 text-xs text-zinc-400">{{ $program->conductor ?: 'Sin conductor asignado' }}</p>
                        </div>
                        <form action="{{ route('admin.programs.generate-code', $program) }}" method="POST">
                            @csrf
                            <button type="submit" class="inline-flex h-8 items-center rounded-lg border border-white/10 bg-white/5 px-3 text-xs font-medium text-zinc-200 transition hover:border-white/20 hover:bg-white/10 hover:text-white">
                                Regenerar código
                            </button>
                        </form>
                    </div>

                    <form action="{{ route('admin.programs.send-invitation', $program) }}" method="POST" class="mt-4 flex flex-wrap items-center gap-2 border-t border-white/10 pt-4">
                        @csrf
                        <select name="template_id" class="h-9 min-w-[200px] flex-1 rounded-lg border border-white/10 bg-[#161619] px-3 text-xs text-zinc-200 focus:border-[#c32720] focus:outline-none">
                            @forelse ($templates as $template)
                                <option value="{{ $template->id }}">{{ $template->name }}</option>
                            @empty
                                <option value="">Plantilla base</option>
                            @endforelse
                        </select>
                        <button type="submit" class="inline-flex h-9 items-center rounded-lg bg-[#c32720] px-4 text-xs font-semibold text-white transition hover:bg-[#d62d26]">
                            Enviar invitación
                        </button>
                    </form>
                </div>
            @endforeach
        </section>
    </div>
</x-layouts.admin>
