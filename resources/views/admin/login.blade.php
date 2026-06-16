<x-layouts.admin :title="$themeAppearance['admin_texts']['admin_login_title'].' - '.$themeSettings->site_name">
    <div class="mx-auto mt-10 max-w-xl">
        <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8 md:p-10">
            <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">{{ $themeAppearance['admin_texts']['admin_login_title'] }}</h1>
            <p class="mt-3 text-sm text-[#7b7b7b]">{{ $themeAppearance['admin_texts']['admin_login_copy'] }}</p>

            @if (session('status'))
                <div class="mt-6 border border-[#1a4d1a] bg-[rgba(39,195,64,.08)] px-4 py-3 text-sm text-[#b1f3b6]">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mt-6 border border-[#5a1d1a] bg-[rgba(195,39,32,.08)] px-4 py-3 text-sm text-[#f3b6b1]">
                    {{ $errors->first() }}
                </div>
            @endif

            <form action="{{ route('admin.login.store') }}" method="POST" class="mt-8 space-y-6">
                @csrf
                <div>
                    <label class="mb-2 block font-display text-xs uppercase tracking-[.18em] text-[#dcdcdc]">{{ $themeAppearance['admin_texts']['login_email_label'] }}</label>
                    <input type="email" name="email" value="{{ old('email') }}" class="lucille-product-field w-full" required autofocus>
                </div>
                <div>
                    <label class="mb-2 block font-display text-xs uppercase tracking-[.18em] text-[#dcdcdc]">{{ $themeAppearance['admin_texts']['login_password_label'] }}</label>
                    <input type="password" name="password" class="lucille-product-field w-full" required>
                </div>
                <label class="flex items-center gap-2 text-sm text-[#7b7b7b]">
                    <input type="checkbox" name="remember" value="1" class="h-4 w-4 border border-[#2b2b2b] bg-transparent">
                    {{ $themeAppearance['admin_texts']['remember_me'] }}
                </label>
                <div class="flex items-center justify-between gap-4">
                    <button type="submit" class="lucille-button-solid">{{ $themeAppearance['admin_texts']['login_button'] }}</button>
                    <a href="{{ route('admin.password.request') }}" class="text-xs text-[#7b7b7b] transition hover:text-lucille-accent">
                        ¿Olvidaste tu contraseña?
                    </a>
                </div>
            </form>
        </div>
    </div>
</x-layouts.admin>

