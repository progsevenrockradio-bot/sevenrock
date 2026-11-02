<x-layouts.admin title="Gestión de usuarios - {{ $themeSettings->site_name ?? 'Seven Rock Radio' }}">
    <div class="space-y-5 font-sans">
        {{-- Cabecera compacta --}}
        <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-white/10 pb-4">
            <div>
                <div class="flex items-center gap-2 text-[11px] font-medium uppercase tracking-wider text-gray-400">
                    <span>Sistema</span>
                    <span class="text-white/20">/</span>
                    <span class="text-[#c32720]">Gestión de usuarios</span>
                </div>
                <h1 class="mt-1 font-display text-2xl font-bold uppercase tracking-wide text-[#dcdcdc]">Gestión de usuarios administradores</h1>
                <p class="mt-1 text-xs text-gray-400 max-w-3xl">
                    Administra las cuentas con acceso al panel de control, sus credenciales y su nivel de permisos operativos.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.users.create') }}"
                   class="inline-flex h-9 items-center gap-2 rounded-lg bg-[#c32720] px-4 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-[#a61f19] focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span>Nuevo administrador</span>
                </a>
            </div>
        </header>

        {{-- Mensajes de estado --}}
        @if (session('status'))
            <div class="flex items-center gap-2.5 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-xs text-emerald-200" role="status">
                <svg class="h-4 w-4 shrink-0 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-xs text-red-200" role="alert">
                <ul class="list-disc list-inside space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Resumen de roles y protecciones de seguridad --}}
        <section aria-label="Niveles de acceso y protecciones" class="rounded-xl border border-white/10 bg-[#101012] p-4">
            <div class="grid gap-3 md:grid-cols-3 text-xs">
                <div class="rounded-lg border border-white/5 bg-[#151518] p-3">
                    <span class="font-semibold text-[#dcdcdc]">Rol Super Admin</span>
                    <p class="mt-1 text-[11px] text-gray-400 leading-relaxed">
                        Acceso completo a todo el panel, incluyendo Configuración general, Bitácora de auditoría, Visor de logs, Plantillas de correo y Gestión de usuarios.
                    </p>
                </div>
                <div class="rounded-lg border border-white/5 bg-[#151518] p-3">
                    <span class="font-semibold text-[#dcdcdc]">Administrador estándar (`is_admin`)</span>
                    <p class="mt-1 text-[11px] text-gray-400 leading-relaxed">
                        Acceso a la operación editorial, programación, bandas y comunicación diaria sin acceso a las rutas restringidas de sistema.
                    </p>
                </div>
                <div class="rounded-lg border border-white/5 bg-[#151518] p-3">
                    <span class="font-semibold text-amber-300">Protección contra bloqueo</span>
                    <p class="mt-1 text-[11px] text-gray-400 leading-relaxed">
                        El sistema impide eliminar al último administrador registrado para garantizar que siempre exista al menos una cuenta con acceso al panel.
                    </p>
                </div>
            </div>
        </section>

        {{-- Tabla compacta en escritorio --}}
        <section class="hidden md:block rounded-xl border border-white/10 bg-[#101012] overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-white/10 bg-white/[0.02] text-[11px] font-semibold uppercase tracking-wider text-gray-400">
                            <th class="py-3 px-4">Usuario</th>
                            <th class="py-3 px-4">Correo electrónico</th>
                            <th class="py-3 px-4">Roles y alcance</th>
                            <th class="py-3 px-4">Fecha de alta</th>
                            <th class="py-3 px-4 text-right">Acciones disponibles</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5 text-xs">
                        @forelse ($users as $user)
                            @php
                                $isCurrentUser = auth()->id() === $user->id;
                                $roleNames = method_exists($user, 'getRoleNames') ? $user->getRoleNames() : collect();
                                $canDelete = $users->count() > 1;
                            @endphp
                            <tr class="hover:bg-white/[0.02] transition-colors">
                                <td class="py-3 px-4 align-middle">
                                    <div class="flex items-center gap-2">
                                        <span class="font-semibold text-[#dcdcdc]">{{ $user->name }}</span>
                                        @if ($isCurrentUser)
                                            <span class="inline-flex items-center rounded-md border border-[#c32720]/40 bg-[#c32720]/15 px-2 py-0.5 text-[10px] font-semibold text-red-200">
                                                Tu cuenta actual
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="py-3 px-4 align-middle font-mono text-xs text-gray-300">
                                    {{ $user->email }}
                                </td>
                                <td class="py-3 px-4 align-middle">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        @forelse ($roleNames as $roleName)
                                            <span class="inline-flex items-center rounded-md border border-amber-500/30 bg-amber-500/10 px-2 py-0.5 text-[11px] font-medium text-amber-300">
                                                {{ $roleName }}
                                            </span>
                                        @empty
                                            <span class="inline-flex items-center rounded-md border border-sky-500/30 bg-sky-500/10 px-2 py-0.5 text-[11px] font-medium text-sky-300">
                                                Administrador de panel
                                            </span>
                                        @endforelse
                                        @if ($user->is_admin)
                                            <span class="rounded border border-white/10 bg-white/5 px-2 py-0.5 text-[10px] text-gray-400">
                                                Acceso admin activo
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="py-3 px-4 align-middle whitespace-nowrap font-mono text-gray-400">
                                    {{ $user->created_at?->format('d/m/Y') ?? '—' }}
                                </td>
                                <td class="py-3 px-4 align-middle text-right whitespace-nowrap">
                                    <div class="inline-flex items-center justify-end gap-2">
                                        <a href="{{ route('admin.users.edit', $user) }}"
                                           class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-white/10 bg-white/5 px-3 text-xs font-medium text-gray-200 transition-colors hover:bg-white/10 hover:text-white focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                                            <span>Editar datos / clave</span>
                                        </a>
                                        <span class="h-4 w-px bg-white/10" aria-hidden="true"></span>
                                        @if ($canDelete)
                                            <form action="{{ route('admin.users.destroy', $user) }}" method="POST"
                                                  onsubmit="return confirm('¿Seguro que deseas eliminar la cuenta de administrador de {{ addslashes($user->name) }}?');"
                                                  class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="inline-flex h-8 items-center gap-1 rounded-lg border border-red-500/25 bg-red-500/10 px-2.5 text-xs font-medium text-red-300 transition-colors hover:bg-red-500/20 hover:text-red-200 focus:outline-none focus:ring-1 focus:ring-red-500">
                                                    <span>Eliminar</span>
                                                </button>
                                            </form>
                                        @else
                                            <span class="inline-flex h-8 items-center rounded-lg border border-white/5 bg-white/[0.02] px-2.5 text-[11px] text-gray-500 cursor-not-allowed"
                                                  title="No se puede eliminar al último administrador del sistema">
                                                Último admin protegido
                                            </span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 px-4 text-center text-xs text-gray-400">
                                    No se encontraron administradores registrados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Tarjetas en móvil (390 px) --}}
        <section class="md:hidden space-y-3" aria-label="Lista de usuarios en móvil">
            @forelse ($users as $user)
                @php
                    $isCurrentUser = auth()->id() === $user->id;
                    $roleNames = method_exists($user, 'getRoleNames') ? $user->getRoleNames() : collect();
                    $canDelete = $users->count() > 1;
                @endphp
                <article class="rounded-xl border border-white/10 bg-[#101012] p-4 space-y-3">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <h3 class="text-sm font-semibold text-[#dcdcdc] truncate">{{ $user->name }}</h3>
                            <p class="font-mono text-xs text-gray-400 break-all">{{ $user->email }}</p>
                        </div>
                        @if ($isCurrentUser)
                            <span class="shrink-0 rounded-md border border-[#c32720]/40 bg-[#c32720]/15 px-2 py-0.5 text-[10px] font-semibold text-red-200">
                                Tu cuenta
                            </span>
                        @endif
                    </div>

                    <div class="flex flex-wrap items-center gap-1.5">
                        @forelse ($roleNames as $roleName)
                            <span class="rounded-md border border-amber-500/30 bg-amber-500/10 px-2 py-0.5 text-[11px] font-medium text-amber-300">
                                {{ $roleName }}
                            </span>
                        @empty
                            <span class="rounded-md border border-sky-500/30 bg-sky-500/10 px-2 py-0.5 text-[11px] font-medium text-sky-300">
                                Administrador de panel
                            </span>
                        @endforelse
                        <span class="text-[11px] text-gray-500 ml-auto font-mono">
                            Alta: {{ $user->created_at?->format('d/m/Y') ?? '—' }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between gap-2 pt-2 border-t border-white/5">
                        <a href="{{ route('admin.users.edit', $user) }}"
                           class="inline-flex h-8 items-center justify-center rounded-lg border border-white/10 bg-white/5 px-3 text-xs font-medium text-gray-200 hover:bg-white/10">
                            Editar datos / clave
                        </a>
                        @if ($canDelete)
                            <form action="{{ route('admin.users.destroy', $user) }}" method="POST"
                                  onsubmit="return confirm('¿Seguro que deseas eliminar este administrador?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="inline-flex h-8 items-center justify-center rounded-lg border border-red-500/25 bg-red-500/10 px-3 text-xs font-medium text-red-300 hover:bg-red-500/20">
                                    Eliminar
                                </button>
                            </form>
                        @else
                            <span class="text-[11px] text-gray-500">Último admin protegido</span>
                        @endif
                    </div>
                </article>
            @empty
                <div class="rounded-xl border border-white/10 bg-[#101012] p-6 text-center text-xs text-gray-400">
                    No se encontraron administradores registrados.
                </div>
            @endforelse
        </section>

        @if ($users->hasPages())
            <div class="pt-2">
                {{ $users->links() }}
            </div>
        @endif
    </div>
</x-layouts.admin>
