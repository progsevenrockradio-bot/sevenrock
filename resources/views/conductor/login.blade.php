<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Portal de Conductores · Seven Rock Radio</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#151515] text-[#dcdcdc] font-sans flex items-center justify-center p-6">
    <div class="w-full max-w-md border border-[#2b2b2b] bg-[#101012] p-8 shadow-[0_28px_72px_rgba(0,0,0,.58)]">
        <div class="border-b border-[#2b2b2b] pb-4 mb-6">
            <p class="text-xs uppercase tracking-[.22em] text-[#c32720]">Seven Rock Radio</p>
            <h1 class="mt-1 font-display text-2xl uppercase tracking-[.12em] text-[#dcdcdc]">Portal de Conductores</h1>
            <p class="mt-2 text-xs text-[#7b7b7b]">Acceso exclusivo para entrega de episodios de tus programas autorizados.</p>
        </div>

        <form action="{{ route('conductor.login.store') }}" method="POST" class="space-y-5">
            @csrf

            <div>
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Correo electrónico</label>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus class="lucille-product-field w-full">
                @error('email')<p class="mt-2 text-sm text-red-400">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Contraseña</label>
                <input type="password" name="password" required class="lucille-product-field w-full">
                @error('password')<p class="mt-2 text-sm text-red-400">{{ $message }}</p>@enderror
            </div>

            <button type="submit" class="lucille-button-solid w-full justify-center">Iniciar sesión</button>
        </form>
    </div>
</body>
</html>

