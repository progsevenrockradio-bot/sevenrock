<x-layouts.admin title="Plantillas de invitación a bandas">
    <div class="mx-auto max-w-7xl space-y-5 font-sans">
        <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-white/10 pb-4">
            <div>
                <div class="text-[11px] font-semibold uppercase tracking-wider text-[#c32720]">Contactos e invitaciones · Textos con variables</div>
                <h1 class="mt-0.5 font-display text-2xl uppercase tracking-[.08em] text-white">Plantillas de invitación a bandas</h1>
                <p class="mt-1 max-w-3xl text-xs text-gray-400">
                    Mensajes reutilizables con variables dinámicas (<code class="text-[11px] text-gray-300">{band_name}</code>, <code class="text-[11px] text-gray-300">{program_name}</code>) para convocar bandas y solicitar material técnico.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.outreach.index') }}" class="inline-flex h-9 items-center rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white">
                    Resumen
                </a>
                <a href="{{ route('admin.outreach.templates.create') }}" class="inline-flex h-9 items-center rounded-lg bg-[#c32720] px-3.5 text-xs font-semibold text-white hover:bg-[#a8201a]">
                    + Nueva plantilla
                </a>
            </div>
        </header>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-xs text-emerald-200" role="status">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-xs text-red-200" role="alert">
                <ul class="list-disc list-inside space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Bloque separado: Prueba de envío de plantilla --}}
        <section class="rounded-xl border border-white/10 bg-[#101012] p-4">
            <div class="mb-3 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="font-display text-sm uppercase tracking-wider text-white">Envío de prueba de plantilla</h2>
                    <p class="text-[11px] text-gray-400">Envía una muestra renderizada con datos de ejemplo para verificar el diseño antes de lanzar una campaña.</p>
                </div>
            </div>
            <form action="{{ route('admin.outreach.send-test') }}" method="POST" class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_240px_auto] items-end">
                @csrf
                <div>
                    <label for="test-outreach-email" class="mb-1 block text-xs font-medium text-gray-300">Correo destino de prueba</label>
                    <input id="test-outreach-email" name="email" type="email" required placeholder="tu@correo.com" class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                </div>
                <div>
                    <label for="test-outreach-template" class="mb-1 block text-xs font-medium text-gray-300">Plantilla a probar</label>
                    <select id="test-outreach-template" name="template_id" class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                        <option value="">Usar primera plantilla activa</option>
                        @foreach ($templates as $template)
                            <option value="{{ $template->id }}">{{ $template->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="inline-flex h-9 items-center justify-center rounded-lg border border-white/15 bg-white/[0.06] px-4 text-xs font-semibold text-white hover:bg-white/[0.12] focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                    Enviar correo de prueba
                </button>
            </form>
        </section>

        {{-- Tabla de plantillas en escritorio --}}
        <section class="hidden md:block rounded-xl border border-white/10 bg-[#101012] overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="border-b border-white/10 bg-[#151518] text-[11px] font-semibold text-gray-400">
                        <tr>
                            <th class="px-4 py-3">Nombre de plantilla</th>
                            <th class="px-4 py-3">Asunto configurado</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @forelse ($templates as $template)
                            <tr class="hover:bg-white/[0.02] align-middle">
                                <td class="px-4 py-3 font-semibold text-white">{{ $template->name }}</td>
                                <td class="px-4 py-3 text-gray-300">{{ $template->subject }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-md border px-2 py-0.5 text-[11px] font-medium {{ $template->is_active ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300' : 'border-white/10 bg-white/[0.04] text-gray-400' }}">
                                        {{ $template->is_active ? 'Activa' : 'Inactiva' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="inline-flex items-center gap-2">
                                        <a href="{{ route('admin.outreach.templates.edit', $template) }}" class="inline-flex h-8 items-center rounded-lg border border-white/10 bg-white/[0.04] px-2.5 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white">
                                            Editar
                                        </a>
                                        <form action="{{ route('admin.outreach.templates.destroy', $template) }}" method="POST" data-confirm="¿Eliminar esta plantilla de invitación?" data-confirm-title="Eliminar plantilla" data-confirm-action="Eliminar" data-confirm-tone="danger">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inline-flex h-8 items-center rounded-lg border border-red-500/30 bg-red-500/10 px-2.5 text-xs font-medium text-red-300 hover:bg-red-500/20">
                                                Eliminar
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-xs text-gray-400">
                                    No hay plantillas de invitación registradas todavía.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Tarjetas en móvil (390 px) --}}
        <section class="space-y-3 md:hidden">
            @forelse ($templates as $template)
                <article class="rounded-xl border border-white/10 bg-[#101012] p-4 space-y-3">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="text-sm font-semibold text-white">{{ $template->name }}</div>
                            <div class="mt-0.5 text-xs text-gray-400">{{ $template->subject }}</div>
                        </div>
                        <span class="inline-flex items-center rounded-md border px-2 py-0.5 text-[11px] font-medium {{ $template->is_active ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300' : 'border-white/10 bg-white/[0.04] text-gray-400' }}">
                            {{ $template->is_active ? 'Activa' : 'Inactiva' }}
                        </span>
                    </div>
                    <div class="flex items-center gap-2 pt-1">
                        <a href="{{ route('admin.outreach.templates.edit', $template) }}" class="inline-flex h-8 flex-1 items-center justify-center rounded-lg bg-[#c32720] px-3 text-xs font-semibold text-white">
                            Editar
                        </a>
                        <form action="{{ route('admin.outreach.templates.destroy', $template) }}" method="POST" class="flex-1" data-confirm="¿Eliminar esta plantilla?" data-confirm-title="Eliminar plantilla" data-confirm-action="Eliminar" data-confirm-tone="danger">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex h-8 w-full items-center justify-center rounded-lg border border-red-500/30 bg-red-500/10 px-3 text-xs font-medium text-red-300">
                                Eliminar
                            </button>
                        </form>
                    </div>
                </article>
            @empty
                <div class="rounded-xl border border-white/10 bg-[#101012] p-6 text-center text-xs text-gray-400">
                    No hay plantillas todavía.
                </div>
            @endforelse
        </section>

        <div>{{ $templates->links() }}</div>
    </div>
</x-layouts.admin>
