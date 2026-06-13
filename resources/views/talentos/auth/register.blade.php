<x-layouts.site :title="'Talentos - Registro'">
    @php $planDefinitions = \App\Support\TalentPlan::definitions(); @endphp

    <section class="mx-auto max-w-[1180px] px-5 pt-32 pb-16">
        <div class="grid gap-8 lg:grid-cols-[1fr_360px]" x-data="{ selectedPlan: '{{ request()->query('plan', old('plan', 'free')) }}' }">
            
            {{-- Formulario Principal --}}
            <div class="border border-white/10 bg-white/[0.02] backdrop-blur-md rounded-[16px] p-8 shadow-xl">
                <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Registro de Talentos</h1>
                <p class="mt-3 max-w-3xl text-sm text-[#7b7b7b]">Crea tu perfil, elige un plan y empieza a publicar tu contenido en el Muro del Rock.</p>

                {{-- Alert de errores de validación generales --}}
                @if ($errors->any())
                    <div class="mt-6 border border-red-500/20 bg-red-500/5 rounded-[12px] p-4 text-xs text-red-400">
                        <strong class="font-display uppercase tracking-[.08em] block mb-2">Se encontraron errores en el formulario:</strong>
                        <ul class="list-disc pl-4 space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('talents.register.store') }}" method="POST" class="mt-8 space-y-6">
                    @csrf
                    
                    {{-- Honeypot Spam Protection --}}
                    <div class="hidden" style="display:none !important" aria-hidden="true">
                        <input type="text" name="user_website" tabindex="-1" autocomplete="off">
                    </div>

                    {{-- Form Fields Grid --}}
                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Nombre de banda</label>
                            <input name="name" value="{{ old('name') }}" class="lucille-product-field w-full rounded-[8px] @error('name') border-red-500/50 @enderror">
                            @error('name')
                                <span class="mt-1.5 block text-[10px] text-red-400 uppercase tracking-wider font-mono">{{ $message }}</span>
                            @enderror
                        </div>
                        <div>
                            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Email</label>
                            <input type="email" name="email" value="{{ old('email') }}" class="lucille-product-field w-full rounded-[8px] @error('email') border-red-500/50 @enderror">
                            @error('email')
                                <span class="mt-1.5 block text-[10px] text-red-400 uppercase tracking-wider font-mono">{{ $message }}</span>
                            @enderror
                        </div>
                        <div>
                            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Password</label>
                            <input type="password" name="password" class="lucille-product-field w-full rounded-[8px] @error('password') border-red-500/50 @enderror">
                            @error('password')
                                <span class="mt-1.5 block text-[10px] text-red-400 uppercase tracking-wider font-mono">{{ $message }}</span>
                            @enderror
                        </div>
                        <div>
                            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Confirmar password</label>
                            <input type="password" name="password_confirmation" class="lucille-product-field w-full rounded-[8px]">
                        </div>
                    </div>

                    {{-- Plan Selector Cards --}}
                    <div>
                        <div class="mb-3 text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Selecciona tu Plan</div>
                        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            @foreach ($planDefinitions as $key => $plan)
                                <label 
                                    class="relative border p-5 rounded-[12px] cursor-pointer transition-all duration-300 flex flex-col justify-between group"
                                    :class="selectedPlan === '{{ $key }}' ? 'border-[var(--lucille-accent)]/50 bg-white/[0.03] shadow-[0_0_15px_rgba(195,39,32,0.08)]' : 'border-white/10 bg-white/[0.01] hover:bg-white/[0.03] hover:border-white/20'"
                                >
                                    <div class="flex items-start gap-3">
                                        <input 
                                            type="radio" 
                                            name="plan" 
                                            value="{{ $key }}" 
                                            x-model="selectedPlan"
                                            class="mt-1 h-4 w-4 accent-lucille-accent cursor-pointer"
                                        >
                                        <div class="min-w-0">
                                            <div class="font-display text-sm uppercase tracking-[.12em] text-[#dcdcdc] group-hover:text-white transition-colors">{{ $plan['label'] }}</div>
                                            <div class="mt-1.5 text-lg font-bold text-white tracking-tight">{{ $plan['monthly_label'] }}</div>
                                            <div class="mt-2 text-xs text-[#7b7b7b] leading-relaxed">{{ $plan['summary'] }}</div>
                                        </div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                        @error('plan')
                            <span class="mt-1.5 block text-[10px] text-red-400 uppercase tracking-wider font-mono">{{ $message }}</span>
                        @enderror
                    </div>

                    {{-- Form Actions --}}
                    <div class="flex flex-wrap gap-4 pt-2">
                        <button type="submit" class="lucille-button-solid rounded-[8px] px-8 py-3">Crear cuenta</button>
                        <a href="{{ route('talents.login') }}" class="lucille-button rounded-[8px] px-6 py-3">Ya tengo acceso</a>
                    </div>
                </form>
            </div>

            {{-- Columna Lateral de Comparativa --}}
            <aside class="border border-white/10 bg-white/[0.02] backdrop-blur-md rounded-[16px] p-6 shadow-xl h-fit">
                <h2 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc] border-b border-white/5 pb-3">Comparativa</h2>
                <div class="mt-4 space-y-4 text-sm text-[#7b7b7b]">
                    @foreach ($planDefinitions as $key => $plan)
                        <div 
                            class="border rounded-[12px] p-4 transition-all duration-300"
                            :class="selectedPlan === '{{ $key }}' ? 'border-[var(--lucille-accent)]/30 bg-white/[0.02]' : 'border-white/5 bg-transparent'"
                        >
                            <div class="font-display text-xs uppercase tracking-[.12em] text-white flex items-center justify-between">
                                <span>{{ $plan['label'] }}</span>
                                <span class="text-lucille-accent font-semibold">{{ $plan['monthly_label'] }}</span>
                            </div>
                            <p class="mt-2 text-xs leading-relaxed text-gray-400">{{ $plan['summary'] }}</p>
                        </div>
                    @endforeach
                </div>
            </aside>
        </div>
    </section>
</x-layouts.site>
