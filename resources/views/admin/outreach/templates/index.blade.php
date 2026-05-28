<x-layouts.admin title="Plantillas Outreach">
    <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Plantillas</h1>
                <p class="mt-3 max-w-3xl text-sm text-[#7b7b7b]">Crea mensajes reutilizables con placeholders para personalizar la invitación.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('admin.outreach.index') }}" class="lucille-button">Panel</a>
                <a href="{{ route('admin.outreach.templates.create') }}" class="lucille-button-solid">Nueva plantilla</a>
            </div>
        </div>

        <form action="{{ route('admin.outreach.send-test') }}" method="POST" class="mt-6 flex flex-wrap gap-3">
            @csrf
            <input name="email" type="email" class="lucille-product-field min-w-[280px] flex-1" placeholder="Email de prueba">
            <select name="template_id" class="lucille-product-field min-w-[220px]">
                <option value="">Usar primera activa</option>
                @foreach ($templates as $template)
                    <option value="{{ $template->id }}">{{ $template->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="lucille-button-solid">Enviar prueba</button>
        </form>
    </div>

    <section class="mt-8 border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-6">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[860px] text-left text-sm">
                <thead class="text-xs uppercase tracking-[.18em] text-[#7b7b7b]">
                    <tr>
                        <th class="py-3 pr-4">Nombre</th>
                        <th class="py-3 pr-4">Asunto</th>
                        <th class="py-3 pr-4">Estado</th>
                        <th class="py-3 pr-4">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($templates as $template)
                        <tr class="border-t border-[#242424]">
                            <td class="py-4 pr-4 text-[#dcdcdc]">{{ $template->name }}</td>
                            <td class="py-4 pr-4 text-[#9f9f9f]">{{ $template->subject }}</td>
                            <td class="py-4 pr-4 text-[#9f9f9f]">{{ $template->is_active ? 'Activa' : 'Inactiva' }}</td>
                            <td class="py-4 pr-4">
                                <div class="flex flex-wrap gap-2">
                                    <a href="{{ route('admin.outreach.templates.edit', $template) }}" class="lucille-button">Editar</a>
                                    <form action="{{ route('admin.outreach.templates.destroy', $template) }}" method="POST" data-confirm="¿Eliminar esta plantilla?" data-confirm-title="Eliminar plantilla" data-confirm-action="Eliminar" data-confirm-tone="danger">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="lucille-button-solid">Eliminar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-8 text-center text-[#7b7b7b]">No hay plantillas todavía.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6">{{ $templates->links() }}</div>
    </section>
</x-layouts.admin>
