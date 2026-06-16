<x-layouts.admin title="Administradores — Panel">
    <div class="mx-auto max-w-4xl space-y-8">

        {{-- Header --}}
        <div class="flex items-center justify-between">
            <div>
                <h1 class="font-display text-2xl uppercase tracking-[.12em] text-[#dcdcdc]">Administradores</h1>
                <p class="mt-1 text-sm text-[#7b7b7b]">Gestiona los usuarios con acceso al panel de administración.</p>
            </div>
            <a href="{{ route('admin.users.create') }}" class="lucille-button-solid">
                + Nuevo Administrador
            </a>
        </div>

        {{-- Status --}}
        @if (session('status'))
            <div class="border border-[#1a4d1a] bg-[rgba(39,195,64,.06)] px-4 py-3 text-sm text-[#b1f3b6]">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="border border-[#5a1d1a] bg-[rgba(195,39,32,.06)] px-4 py-3 text-sm text-[#f3b6b1]">
                {{ $errors->first() }}
            </div>
        @endif

        {{-- Table --}}
        <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)]">
            <table class="w-full text-sm">
                <thead class="border-b border-[#2b2b2b]">
                    <tr>
                        <th class="px-6 py-4 text-left font-display text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Nombre</th>
                        <th class="px-6 py-4 text-left font-display text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Email</th>
                        <th class="px-6 py-4 text-left font-display text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Roles</th>
                        <th class="px-6 py-4 text-left font-display text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Creado</th>
                        <th class="px-6 py-4 text-right font-display text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#1e1e1e]">
                    @forelse ($users as $user)
                        <tr class="transition hover:bg-[rgba(255,255,255,.02)]">
                            <td class="px-6 py-4 text-[#dcdcdc]">{{ $user->name }}</td>
                            <td class="px-6 py-4 text-[#9b9b9b]">{{ $user->email }}</td>
                            <td class="px-6 py-4">
                                @forelse ($user->getRoleNames() as $role)
                                    <span class="inline-block rounded-full border border-lucille-accent/30 bg-lucille-accent/10 px-2 py-0.5 text-xs text-lucille-accent">{{ $role }}</span>
                                @empty
                                    <span class="text-xs text-[#4b4b4b]">Sin roles</span>
                                @endforelse
                            </td>
                            <td class="px-6 py-4 text-xs text-[#7b7b7b]">{{ $user->created_at?->format('d/m/Y') }}</td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('admin.users.edit', $user) }}" class="text-xs text-[#7b7b7b] transition hover:text-lucille-accent">Editar</a>
                                    @if ($users->count() > 1)
                                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}"
                                              onsubmit="return confirm('¿Eliminar al administrador {{ addslashes($user->name) }}? Esta acción no se puede deshacer.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs text-[#7b7b7b] transition hover:text-red-400">Eliminar</button>
                                        </form>
                                    @else
                                        <span class="text-xs text-[#3b3b3b]" title="No se puede eliminar el único administrador">Eliminar</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-sm text-[#5b5b5b]">
                                No hay administradores registrados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if ($users->hasPages())
                <div class="border-t border-[#2b2b2b] px-6 py-4">
                    {{ $users->links() }}
                </div>
            @endif
        </div>
    </div>
</x-layouts.admin>
