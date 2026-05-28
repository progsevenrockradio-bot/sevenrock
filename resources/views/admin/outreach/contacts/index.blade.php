<x-layouts.admin title="Contactos Outreach">
    <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Contactos</h1>
                <p class="mt-3 max-w-3xl text-sm text-[#7b7b7b]">Importa bandas, filtra por programa y controla qué material ya llegó.</p>
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

        <form method="GET" class="mt-6 grid gap-3 lg:grid-cols-4">
            <input name="search" value="{{ $search }}" class="lucille-product-field" placeholder="Buscar por banda, email o contacto">
            <select name="program_code" class="lucille-product-field">
                <option value="">Todos los programas</option>
                @foreach ($programs as $program)
                    <option value="{{ $program->program_code }}" @selected($programCodeFilter === $program->program_code)>{{ $program->program_code }} - {{ $program->name }}</option>
                @endforeach
            </select>
            <select name="referral_source" class="lucille-product-field">
                <option value="">Origen</option>
                @foreach (['producer' => 'Producer', 'self' => 'Self', 'other' => 'Other'] as $value => $label)
                    <option value="{{ $value }}" @selected($referralSourceFilter === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="status" class="lucille-product-field">
                <option value="">Todos los estados</option>
                @foreach (['pending','contacted','responded','registered','not_interested','invalid'] as $status)
                    <option value="{{ $status }}" @selected($statusFilter === $status)>{{ $status }}</option>
                @endforeach
            </select>
            <div class="lg:col-span-4 flex flex-wrap gap-3">
                <button type="submit" class="lucille-button-solid">Filtrar</button>
                <a href="{{ route('admin.outreach.contacts.index') }}" class="lucille-button">Limpiar</a>
            </div>
        </form>
    </div>

    <section class="mt-8 border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-6">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1320px] text-left text-sm">
                <thead class="text-xs uppercase tracking-[.18em] text-[#7b7b7b]">
                    <tr>
                        <th class="py-3 pr-4">Banda</th>
                        <th class="py-3 pr-4">Programa</th>
                        <th class="py-3 pr-4">Email</th>
                        <th class="py-3 pr-4">Origen</th>
                        <th class="py-3 pr-4">Specs</th>
                        <th class="py-3 pr-4">Status</th>
                        <th class="py-3 pr-4">Ultimo contacto</th>
                        <th class="py-3 pr-4">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($contacts as $contact)
                        <tr class="border-t border-[#242424] align-top">
                            <td class="py-4 pr-4 text-[#dcdcdc]">
                                <a href="{{ route('admin.outreach.contacts.show', $contact) }}" class="hover:text-white">{{ $contact->displayName() }}</a>
                                <div class="mt-1 text-xs text-[#7b7b7b]">{{ $contact->contact_person ?: 'Sin contacto' }}</div>
                            </td>
                            <td class="py-4 pr-4 text-[#9f9f9f]">
                                <div class="font-mono text-xs text-[#dcdcdc]">{{ $contact->program_code ?: 'Sin código' }}</div>
                                <div class="mt-1 text-xs text-[#7b7b7b]">{{ $contact->programLabel() }}</div>
                            </td>
                            <td class="py-4 pr-4 text-[#9f9f9f]">{{ $contact->email ?: 'Sin email' }}</td>
                            <td class="py-4 pr-4 text-[#9f9f9f]">{{ $contact->referral_source ?: 'producer' }}</td>
                            <td class="py-4 pr-4 text-[#9f9f9f]">
                                <div class="text-xs">
                                    <span class="inline-flex items-center border border-[#2b2b2b] px-2 py-1">{{ $contact->image_specs_met ? '✅ IMG' : '⛔ IMG' }}</span>
                                    <span class="ml-1 inline-flex items-center border border-[#2b2b2b] px-2 py-1">{{ $contact->audio_specs_met ? '✅ AUDIO' : '⛔ AUDIO' }}</span>
                                </div>
                                <div class="mt-2 text-xs text-[#7b7b7b]">{{ $contact->specsBadge() }}</div>
                            </td>
                            <td class="py-4 pr-4 text-[#9f9f9f]">{{ $contact->status }}</td>
                            <td class="py-4 pr-4 text-[#9f9f9f]">
                                {{ $contact->last_contacted_at?->format('Y-m-d H:i') ?? 'Nunca' }}
                                <div class="mt-1 text-xs text-[#7b7b7b]">Material: {{ $contact->materials_received_at?->format('Y-m-d H:i') ?? 'Pendiente' }}</div>
                            </td>
                            <td class="py-4 pr-4">
                                <div class="flex flex-wrap gap-2">
                                    <a href="{{ route('admin.outreach.contacts.show', $contact) }}" class="lucille-button">Ver</a>
                                    <a href="{{ route('admin.outreach.contacts.edit', $contact) }}" class="lucille-button">Editar</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="py-8 text-center text-[#7b7b7b]">No hay contactos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6">{{ $contacts->links() }}</div>
    </section>
</x-layouts.admin>
