<x-layouts.admin title="Editar Administrador — Panel">
    <div class="mx-auto max-w-xl space-y-8">

        {{-- Header --}}
        <div class="flex items-center gap-4">
            <a href="{{ route('admin.users.index') }}" class="text-[#7b7b7b] transition hover:text-lucille-accent">← Volver</a>
            <h1 class="font-display text-2xl uppercase tracking-[.12em] text-[#dcdcdc]">Editar Administrador</h1>
        </div>

        {{-- Form --}}
        <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
            @if (session('status'))
                <div class="mb-6 border border-[#1a4d1a] bg-[rgba(39,195,64,.06)] px-4 py-3 text-sm text-[#b1f3b6]">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-6 border border-[#5a1d1a] bg-[rgba(195,39,32,.06)] px-4 py-3 text-sm text-[#f3b6b1]">
                    <ul class="list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-6">
                @csrf
                @method('PUT')

                <div>
                    <label for="name" class="mb-2 block font-display text-xs uppercase tracking-[.18em] text-[#dcdcdc]">
                        Nombre completo <span class="text-lucille-accent">*</span>
                    </label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="{{ old('name', $user->name) }}"
                        class="lucille-product-field w-full @error('name') border-red-500/60 @enderror"
                        required
                        autofocus
                    >
                    @error('name')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="email" class="mb-2 block font-display text-xs uppercase tracking-[.18em] text-[#dcdcdc]">
                        Email <span class="text-lucille-accent">*</span>
                    </label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email', $user->email) }}"
                        class="lucille-product-field w-full @error('email') border-red-500/60 @enderror"
                        required
                    >
                    @error('email')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="border-t border-[#2b2b2b] pt-6">
                    <p class="mb-4 text-xs text-[#5b5b5b] uppercase tracking-[.18em]">Cambiar contraseña (opcional)</p>

                    <div class="space-y-4">
                        <div>
                            <label for="password" class="mb-2 block font-display text-xs uppercase tracking-[.18em] text-[#dcdcdc]">
                                Nueva contraseña
                            </label>
                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="lucille-product-field w-full @error('password') border-red-500/60 @enderror"
                                minlength="8"
                                autocomplete="new-password"
                            >
                            <p class="mt-1 text-xs text-[#5b5b5b]">Dejar vacío para no cambiar la contraseña actual.</p>
                            @error('password')
                                <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="password_confirmation" class="mb-2 block font-display text-xs uppercase tracking-[.18em] text-[#dcdcdc]">
                                Confirmar nueva contraseña
                            </label>
                            <input
                                type="password"
                                id="password_confirmation"
                                name="password_confirmation"
                                class="lucille-product-field w-full"
                                autocomplete="new-password"
                            >
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-4 pt-2">
                    <button type="submit" class="lucille-button-solid">Guardar Cambios</button>
                    <a href="{{ route('admin.users.index') }}" class="text-sm text-[#7b7b7b] transition hover:text-[#dcdcdc]">Cancelar</a>
                </div>
            </form>
        </div>

        {{-- Info --}}
        <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.5)] px-6 py-4 text-xs text-[#5b5b5b]">
            <span class="uppercase tracking-[.18em]">Cuenta creada:</span>
            {{ $user->created_at?->format('d/m/Y \a \l\a\s H:i') }}
        </div>
    </div>
</x-layouts.admin>
