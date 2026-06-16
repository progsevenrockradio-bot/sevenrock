<x-layouts.admin title="Nueva Contraseña — Panel Admin">
    <div class="mx-auto mt-10 max-w-xl">
        <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8 md:p-10">
            <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Nueva Contraseña</h1>
            <p class="mt-3 text-sm text-[#7b7b7b]">
                Ingresa tu nueva contraseña para restablecer el acceso al panel.
            </p>

            @if ($errors->any())
                <div class="mt-6 border border-[#5a1d1a] bg-[rgba(195,39,32,.08)] px-4 py-3 text-sm text-[#f3b6b1]">
                    <ul class="list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('admin.password.update') }}" method="POST" class="mt-8 space-y-6">
                @csrf

                {{-- Token oculto --}}
                <input type="hidden" name="token" value="{{ $token }}">

                <div>
                    <label for="email" class="mb-2 block font-display text-xs uppercase tracking-[.18em] text-[#dcdcdc]">
                        Email
                    </label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email', $email ?? '') }}"
                        class="lucille-product-field w-full @error('email') border-red-500/60 @enderror"
                        required
                        autofocus
                    >
                    @error('email')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="mb-2 block font-display text-xs uppercase tracking-[.18em] text-[#dcdcdc]">
                        Nueva contraseña
                    </label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="lucille-product-field w-full @error('password') border-red-500/60 @enderror"
                        required
                        minlength="8"
                        autocomplete="new-password"
                    >
                    <p class="mt-1 text-xs text-[#5b5b5b]">Mínimo 8 caracteres.</p>
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
                        required
                        autocomplete="new-password"
                    >
                </div>

                <div class="flex items-center justify-between gap-4">
                    <button type="submit" class="lucille-button-solid">Restablecer contraseña</button>
                    <a href="{{ route('admin.login') }}" class="text-xs text-[#7b7b7b] transition hover:text-lucille-accent">
                        ← Volver al login
                    </a>
                </div>
            </form>
        </div>
    </div>
</x-layouts.admin>
