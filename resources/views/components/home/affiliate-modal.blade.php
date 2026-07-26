<template x-teleport="body">
    <div
        x-data="{ showAffiliateModal: false }"
        @open-affiliate-modal.window="showAffiliateModal = true"
        x-show="showAffiliateModal"
        x-transition.opacity.duration.300ms
        x-cloak
        class="fixed inset-0 z-[120] flex items-center justify-center bg-black/80 px-4 py-8 backdrop-blur-sm"
        @keydown.escape.window="showAffiliateModal = false"
        @click.self="showAffiliateModal = false"
    >
        <div class="mx-auto w-full max-w-[650px] border border-[#2b2b2b] bg-[#111] p-6 shadow-[0_24px_80px_rgba(0,0,0,.65)] max-h-[90vh] overflow-y-auto custom-scrollbar">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="home-badge">Patrocinador</div>
                    <h4 class="mt-3 font-display text-[24px] uppercase leading-[1.1] tracking-[.12em] text-white">🚀 Inicia tu Radio por Internet con <span class="text-lucille-accent">RadioBOSS.fm</span></h4>
                </div>
                <button
                    type="button"
                    class="h-10 w-10 shrink-0 border border-[#2b2b2b] text-[#dcdcdc] transition-colors hover:bg-white/5 flex items-center justify-center text-xl"
                    @click="showAffiliateModal = false"
                    aria-label="Cerrar"
                >
                    &times;
                </button>
            </div>

            <div class="mt-5 space-y-4 border-t border-[#2b2b2b] pt-5 text-[14px] leading-relaxed text-[#d8d8d8]">
                <p>
                    Montar una estación de radio online nunca ha sido tan sencillo. Con <strong class="text-white">RadioBOSS.fm</strong>, obtienes la infraestructura completa necesaria para transmitir tu señal al mundo con la máxima calidad y confiabilidad.
                </p>

                <div class="py-2">
                    <h5 class="font-display text-[13px] uppercase tracking-[.15em] text-lucille-accent mb-3">¿Por qué elegir RadioBOSS.fm para tu proyecto?</h5>
                    <ul class="space-y-3">
                        <li class="flex items-start gap-2">
                            <span class="text-lucille-accent mt-1">✓</span>
                            <span><strong class="text-white">AutoDJ 24/7:</strong> Sube tus canciones a la nube y deja que el servidor transmita por ti sin interrumpir la emisión.</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-lucille-accent mt-1">✓</span>
                            <span><strong class="text-white">Compatibilidad Universal:</strong> Tus oyentes podrán escucharte desde cualquier navegador, teléfono móvil o reproductor multimedia.</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-lucille-accent mt-1">✓</span>
                            <span><strong class="text-white">Estadísticas en vivo:</strong> Analiza cuánta gente te escucha y desde qué países te sintonizan.</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-lucille-accent mt-1">✓</span>
                            <span><strong class="text-white">Configuración rápida:</strong> Tendrás tu servicio listo para emitir en cuestión de minutos.</span>
                        </li>
                    </ul>
                </div>

                <p class="text-[#9aa7b1] text-[13px]">
                    Si quieres dar el salto profesional en el mundo del streaming de audio, aprovecha la oferta de lanzamiento y configura tu servidor ahora.
                </p>

                <div class="mt-6 flex flex-col sm:flex-row gap-3 pt-3">
                    <a
                        class="lucille-button-solid flex-1 text-center justify-center"
                        href="https://www.radioboss.fm/whmcs/aff.php?aff=194"
                        target="_blank"
                        rel="noopener"
                    >
                        🔘 Contratar servicio de Streaming en RadioBOSS.fm
                    </a>
                    <button
                        type="button"
                        class="lucille-button-solid bg-transparent border border-[#2b2b2b] text-white hover:bg-[#2b2b2b] sm:w-auto text-center justify-center"
                        @click="showAffiliateModal = false"
                    >
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
