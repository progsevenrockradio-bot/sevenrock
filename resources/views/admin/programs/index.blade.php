<x-layouts.admin title="Programas con código">
    <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Programas</h1>
                <p class="mt-3 max-w-3xl text-sm text-[#7b7b7b]">Gestiona códigos únicos y envíos de invitación a productores.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('admin.master-programs.index') }}" class="lucille-button">CRUD programas</a>
                <a href="{{ route('admin.programs.invitations') }}" class="lucille-button-solid">Invitaciones</a>
            </div>
        </div>

        <form method="GET" class="mt-6 flex flex-wrap gap-3">
            <input name="search" value="{{ $search }}" class="lucille-product-field min-w-[260px] flex-1" placeholder="Buscar por nombre, código, productor o email">
            <button type="submit" class="lucille-button-solid">Filtrar</button>
            <a href="{{ route('admin.programs.index') }}" class="lucille-button">Limpiar</a>
        </form>
    </div>

    <section class="mt-8 border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-6">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1040px] text-left text-sm">
                <thead class="text-xs uppercase tracking-[.18em] text-[#7b7b7b]">
                    <tr>
                        <th class="py-3 pr-4">Programa</th>
                        <th class="py-3 pr-4">Código</th>
                        <th class="py-3 pr-4">Productor</th>
                        <th class="py-3 pr-4">Email</th>
                        <th class="py-3 pr-4">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($programs as $program)
                        <tr class="border-t border-[#242424] align-top">
                            <td class="py-4 pr-4 text-[#dcdcdc]">{{ $program->name }}</td>
                            <td class="py-4 pr-4 text-[#9f9f9f]">{{ $program->program_code ?: 'Sin código' }}</td>
                            <td class="py-4 pr-4 text-[#9f9f9f]">{{ $program->conductor }}</td>
                            <td class="py-4 pr-4 text-[#9f9f9f]">{{ $program->email_notificacion ?: 'Sin correo' }}</td>
                            <td class="py-4 pr-4">
                                <div class="flex flex-wrap gap-2">
                                    <form action="{{ route('admin.programs.generate-code', $program) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="lucille-button">Asignar/Regenerar código</button>
                                    </form>
                                    <form action="{{ route('admin.programs.send-invitation', $program) }}" method="POST" class="flex items-center gap-2">
                                        @csrf
                                        <select name="template_id" class="lucille-product-field w-[220px]">
                                            @foreach ($templates as $template)
                                                <option value="{{ $template->id }}">{{ $template->name }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="lucille-button-solid">Enviar invitación</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-8 text-center text-[#7b7b7b]">No hay programas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6">{{ $programs->links() }}</div>
    </section>
</x-layouts.admin>
