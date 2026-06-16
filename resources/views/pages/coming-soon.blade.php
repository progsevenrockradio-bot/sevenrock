<x-layouts.site :title="$countdown->title" :description="$countdown->description">
    <div class="min-h-[80vh] flex items-center justify-center relative overflow-hidden py-20 px-4">
        <!-- Background elements for aesthetic -->
        <div class="absolute inset-0 bg-black/60 z-10"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-[var(--lucille-bg)] via-transparent to-[var(--lucille-bg)] z-10"></div>

        <div class="relative z-20 max-w-3xl w-full text-center space-y-8">
            <span class="inline-block px-4 py-1.5 rounded-full border border-[var(--lucille-accent)]/30 bg-[var(--lucille-accent)]/10 text-[var(--lucille-accent)] text-xs font-display tracking-widest uppercase mb-4">
                Próximamente
            </span>
            
            <h1 class="text-4xl md:text-6xl font-display font-bold uppercase tracking-wider text-white">
                {{ $countdown->title }}
            </h1>
            
            @if($countdown->description)
                <p class="text-lg md:text-xl text-gray-400 max-w-2xl mx-auto leading-relaxed">
                    {{ $countdown->description }}
                </p>
            @endif

            @if($countdown->active_at)
                <!-- Countdown Timer -->
                <div class="mt-12 flex flex-wrap justify-center gap-4 md:gap-8" id="countdown-container" data-date="{{ $countdown->active_at->format('Y-m-d\TH:i:s') }}">
                    <div class="flex flex-col items-center">
                        <div class="w-20 h-20 md:w-28 md:h-28 bg-white/5 backdrop-blur-md border border-white/10 rounded-2xl flex items-center justify-center">
                            <span class="text-3xl md:text-5xl font-display font-bold text-white" id="cd-days">00</span>
                        </div>
                        <span class="mt-3 text-xs uppercase tracking-[0.2em] text-gray-500 font-semibold">Días</span>
                    </div>
                    <div class="flex flex-col items-center">
                        <div class="w-20 h-20 md:w-28 md:h-28 bg-white/5 backdrop-blur-md border border-white/10 rounded-2xl flex items-center justify-center">
                            <span class="text-3xl md:text-5xl font-display font-bold text-white" id="cd-hours">00</span>
                        </div>
                        <span class="mt-3 text-xs uppercase tracking-[0.2em] text-gray-500 font-semibold">Horas</span>
                    </div>
                    <div class="flex flex-col items-center">
                        <div class="w-20 h-20 md:w-28 md:h-28 bg-white/5 backdrop-blur-md border border-white/10 rounded-2xl flex items-center justify-center">
                            <span class="text-3xl md:text-5xl font-display font-bold text-[var(--lucille-accent)]" id="cd-minutes">00</span>
                        </div>
                        <span class="mt-3 text-xs uppercase tracking-[0.2em] text-[var(--lucille-accent)] font-semibold">Minutos</span>
                    </div>
                    <div class="flex flex-col items-center">
                        <div class="w-20 h-20 md:w-28 md:h-28 bg-white/5 backdrop-blur-md border border-[var(--lucille-accent)]/20 rounded-2xl flex items-center justify-center">
                            <span class="text-3xl md:text-5xl font-display font-bold text-white" id="cd-seconds">00</span>
                        </div>
                        <span class="mt-3 text-xs uppercase tracking-[0.2em] text-gray-500 font-semibold">Segundos</span>
                    </div>
                </div>
            @endif

            <div class="mt-12 pt-8 border-t border-white/10">
                <a href="{{ route('home') }}" class="lucille-button inline-block px-8 py-3 rounded-lg">Volver al Inicio</a>
            </div>
        </div>
    </div>

    @if($countdown->active_at)
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('countdown-container');
            if (!container) return;

            const targetDate = new Date(container.dataset.date).getTime();
            
            const elements = {
                days: document.getElementById('cd-days'),
                hours: document.getElementById('cd-hours'),
                minutes: document.getElementById('cd-minutes'),
                seconds: document.getElementById('cd-seconds')
            };

            function updateCountdown() {
                const now = new Date().getTime();
                const distance = targetDate - now;

                if (distance < 0) {
                    clearInterval(interval);
                    // Refresh the page automatically when countdown finishes
                    window.location.reload();
                    return;
                }

                const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);

                elements.days.innerText = days.toString().padStart(2, '0');
                elements.hours.innerText = hours.toString().padStart(2, '0');
                elements.minutes.innerText = minutes.toString().padStart(2, '0');
                elements.seconds.innerText = seconds.toString().padStart(2, '0');
            }

            updateCountdown();
            const interval = setInterval(updateCountdown, 1000);
        });
    </script>
    @endif
</x-layouts.site>
