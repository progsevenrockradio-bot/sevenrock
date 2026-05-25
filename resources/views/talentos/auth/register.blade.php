<x-layouts.site :title="'Talentos - Registro'">
    @php $planDefinitions = \App\Support\TalentPlan::definitions(); @endphp

    <section class="mx-auto max-w-6xl px-5 py-10">
        <div class="grid gap-8 lg:grid-cols-[1fr_360px]">
            <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
                <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Registro de Talentos</h1>
                <p class="mt-3 max-w-3xl text-sm text-[#7b7b7b]">Crea tu perfil, elige un plan y empieza a publicar tu contenido.</p>

                <form action="{{ route('talents.register.store') }}" method="POST" class="mt-8 space-y-5">
                    @csrf
                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Nombre de banda</label>
                            <input name="name" value="{{ old('name') }}" class="lucille-product-field w-full">
                        </div>
                        <div>
                            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Email</label>
                            <input type="email" name="email" value="{{ old('email') }}" class="lucille-product-field w-full">
                        </div>
                        <div>
                            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Password</label>
                            <input type="password" name="password" class="lucille-product-field w-full">
                        </div>
                        <div>
                            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Confirmar password</label>
                            <input type="password" name="password_confirmation" class="lucille-product-field w-full">
                        </div>
                    </div>

                    <div>
                        <div class="mb-3 text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Plan</div>
                        <div class="grid gap-3 xl:grid-cols-4">
                            @foreach ($planDefinitions as $key => $plan)
                                <label class="border border-[#2b2b2b] bg-[#151515] p-4 transition hover:border-[#7b7b7b]">
                                    <div class="flex items-start gap-3">
                                        <input type="radio" name="plan" value="{{ $key }}" @checked(old('plan', 'free') === $key) class="mt-1 h-4 w-4">
                                        <div>
                                            <div class="font-display text-sm uppercase tracking-[.12em] text-[#dcdcdc]">{{ $plan['label'] }}</div>
                                            <div class="mt-1 text-lg text-white">{{ $plan['monthly_label'] }}</div>
                                            <div class="mt-2 text-sm text-[#7b7b7b]">{{ $plan['summary'] }}</div>
                                        </div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <button type="submit" class="lucille-button-solid">Crear cuenta</button>
                        <a href="{{ route('talents.login') }}" class="lucille-button">Ya tengo acceso</a>
                    </div>
                </form>
            </div>

            <aside class="border border-[#2b2b2b] bg-[#151515] p-6">
                <h2 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">Comparativa</h2>
                <div class="mt-4 space-y-3 text-sm text-[#7b7b7b]">
                    @foreach ($planDefinitions as $plan)
                        <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-4">
                            <div class="font-display text-sm uppercase tracking-[.12em] text-white">{{ $plan['label'] }} - {{ $plan['monthly_label'] }}</div>
                            <p class="mt-2">{{ $plan['summary'] }}</p>
                        </div>
                    @endforeach
                </div>
            </aside>
        </div>
    </section>
</x-layouts.site>
