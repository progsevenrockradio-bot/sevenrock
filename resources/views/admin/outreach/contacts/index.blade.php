<x-layouts.admin title="Contactos Outreach">
    <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Contactos</h1>
                <p class="mt-3 max-w-3xl text-sm text-[#7b7b7b]">Importa bandas desde Radio Artists o crea contactos manualmente.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <form action="{{ route('admin.outreach.contacts.import') }}" method="POST">
                    @csrf
                    <button type="submit" class="lucille-button">Importar desde Radio Artists</button>
                </form>
                <a href="{{ route('admin.outreach.index') }}" class="lucille-button">Panel</a>
                <a href="{{ route('admin.outreach.contacts.create') }}" class="lucille-button-solid">Nuevo contacto</a>
            </div>
        </div>

        <form method="GET" class="mt-6 flex flex-wrap gap-3">
            <input name="search" value="{{ $search }}" class="lucille-product-field min-w-[260px] flex-1" placeholder="Buscar por banda, email o contacto">
            <select name="status" class="lucille-product-field min-w-[180px]">
                <option value="">Todos los estados</option>
                @foreach (['pending','contacted','responded','registered','not_interested','invalid'] as $status)
                    <option value="{{ $status }}" @selected($statusFilter === $status)>{{ $status }}</option>
                @endforeach
            </select>
            <button type="submit" class="lucille-button-solid">Filtrar</button>
            <a href="{{ route('admin.outreach.contacts.index') }}" class="lucille-button">Limpiar</a>
        </form>
    </div>

    <section class="mt-8 border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-6">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[960px] text-left text-sm">
                <thead class="text-xs uppercase tracking-[.18em] text-[#7b7b7b]">
                    <tr>
                        <th class="py-3 pr-4">Banda</th>
                        <th class="py-3 pr-4">Email</th>
                        <th class="py-3 pr-4">Status</th>
                        <th class="py-3 pr-4">Ultimo contacto</th>
                        <th class="py-3 pr-4">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($contacts as $contact)
                        <tr class="border-t border-[#242424]">
                            <td class="py-4 pr-4 text-[#dcdcdc]">{{ $contact->displayName() }}</td>
                            <td class="py-4 pr-4 text-[#9f9f9f]">{{ $contact->email ?: 'Sin email' }}</td>
                            <td class="py-4 pr-4 text-[#9f9f9f]">{{ $contact->status }}</td>
                            <td class="py-4 pr-4 text-[#9f9f9f]">{{ $contact->last_contacted_at?->format('Y-m-d H:i') ?? 'Nunca' }}</td>
                            <td class="py-4 pr-4">
                                <a href="{{ route('admin.outreach.contacts.edit', $contact) }}" class="lucille-button">Editar</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-8 text-center text-[#7b7b7b]">No hay contactos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6">{{ $contacts->links() }}</div>
    </section>
</x-layouts.admin>
