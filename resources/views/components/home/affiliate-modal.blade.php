<div 
    id="affiliate-modal" 
    role="dialog" 
    aria-modal="true" 
    aria-labelledby="affiliate-modal-title" 
    class="fixed inset-0 z-[120] hidden items-center justify-center px-4 py-8" 
    style="background-color: rgba(0, 0, 0, 0.85); backdrop-filter: blur(4px);"
>
    <div class="relative mx-auto w-full max-w-xl border border-[#2b2b2b] bg-[#111] p-6 md:p-8 shadow-[0_24px_80px_rgba(0,0,0,.65)] max-h-[90vh] overflow-y-auto custom-scrollbar">
        <div class="flex items-start justify-between gap-4">
            <div>
                <div class="home-badge">Patrocinador</div>
                <h3 id="affiliate-modal-title" class="mt-3 font-display text-[24px] uppercase leading-[1.1] tracking-[.12em] text-white">🚀 Inicia tu Radio por Internet con <span class="text-[var(--lucille-accent-readable,#ff5b52)]">RadioBOSS.fm</span></h3>
            </div>
            <button
                type="button"
                id="affiliate-modal-close-btn"
                class="h-10 w-10 shrink-0 border border-[#2b2b2b] text-[#dcdcdc] transition-colors hover:bg-white/5 flex items-center justify-center text-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-lucille-accent"
                onclick="closeAffiliateModal()"
                aria-label="Cerrar ventana de patrocinador"
            >
                &times;
            </button>
        </div>

        <div class="mt-5 space-y-4 border-t border-[#2b2b2b] pt-5 text-[14px] leading-relaxed text-[#d8d8d8]">
            <p>
                Montar una estación de radio online nunca ha sido tan sencillo. Con <strong class="text-white">RadioBOSS.fm</strong>, obtienes la infraestructura completa necesaria para transmitir tu señal al mundo con la máxima calidad y confiabilidad.
            </p>

            <div class="py-2">
                <p class="font-display text-[13px] uppercase tracking-[.15em] text-[var(--lucille-accent-readable,#ff5b52)] mb-3 font-bold">¿Por qué elegir RadioBOSS.fm para tu proyecto?</p>
                <ul class="space-y-3">
                    <li class="flex items-start gap-2">
                        <span class="text-[var(--lucille-accent-readable,#ff5b52)] mt-1 font-bold">✓</span>
                        <span><strong class="text-white">AutoDJ 24/7:</strong> Sube tus canciones a la nube y deja que el servidor transmita por ti sin interrumpir la emisión.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="text-[var(--lucille-accent-readable,#ff5b52)] mt-1 font-bold">✓</span>
                        <span><strong class="text-white">Compatibilidad Universal:</strong> Tus oyentes podrán escucharte desde cualquier navegador, teléfono móvil o reproductor multimedia.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="text-[var(--lucille-accent-readable,#ff5b52)] mt-1 font-bold">✓</span>
                        <span><strong class="text-white">Estadísticas en vivo:</strong> Analiza cuánta gente te escucha y desde qué países te sintonizan.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="text-[var(--lucille-accent-readable,#ff5b52)] mt-1 font-bold">✓</span>
                        <span><strong class="text-white">Configuración rápida:</strong> Tendrás tu servicio listo para emitir en cuestión de minutos.</span>
                    </li>
                </ul>
            </div>

            <p class="text-[#9aa7b1] text-[13px]">
                Si quieres dar el salto profesional en el mundo del streaming de audio, aprovecha la oferta de lanzamiento y configura tu servidor ahora.
            </p>

            <div class="mt-6 flex flex-col sm:flex-row gap-3 pt-3">
                <a
                    class="lucille-button-solid flex-1 text-center justify-center focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-lucille-accent"
                    href="https://www.radioboss.fm/whmcs/aff.php?aff=194"
                    target="_blank"
                    rel="noopener"
                >
                    🔘 Contratar servicio de Streaming en RadioBOSS.fm
                </a>
                <button
                    type="button"
                    class="lucille-button-solid bg-transparent border border-[#2b2b2b] text-white hover:bg-[#2b2b2b] sm:w-auto text-center justify-center focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-lucille-accent"
                    onclick="closeAffiliateModal()"
                >
                    Cerrar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    (function() {
        const modal = document.getElementById('affiliate-modal');
        window._lastAffiliateTrigger = null;

        const setBackgroundInert = (isInert) => {
            if (!modal) return;
            if (isInert && modal.parentElement !== document.body) {
                document.body.appendChild(modal);
            }
            const siblings = Array.from(document.body.children).filter(el => 
                el !== modal && el.tagName !== 'SCRIPT' && el.tagName !== 'STYLE' && el.id !== 'affiliate-modal'
            );
            siblings.forEach(el => {
                if (isInert) el.setAttribute('inert', '');
                else el.removeAttribute('inert');
            });
        };

        window.openAffiliateModal = function(triggerEl) {
            window._lastAffiliateTrigger = triggerEl || document.activeElement;
            if (modal) {
                modal.style.display = 'flex';
                setBackgroundInert(true);
                setTimeout(() => {
                    const closeBtn = document.getElementById('affiliate-modal-close-btn');
                    if (closeBtn) closeBtn.focus();
                }, 50);
            }
        };

        window.closeAffiliateModal = function() {
            if (modal) {
                modal.style.display = 'none';
                setBackgroundInert(false);
                if (window._lastAffiliateTrigger && typeof window._lastAffiliateTrigger.focus === 'function') {
                    window._lastAffiliateTrigger.focus();
                }
            }
        };

        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeAffiliateModal();
                }
            });
        }

        document.addEventListener('keydown', function(e) {
            if (!modal || modal.style.display !== 'flex') return;

            if (e.key === 'Escape') {
                closeAffiliateModal();
                return;
            }

            if (e.key === 'Tab') {
                const focusables = modal.querySelectorAll('button:not([disabled]), [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
                if (!focusables.length) return;
                const first = focusables[0];
                const last = focusables[focusables.length - 1];

                if (e.shiftKey && document.activeElement === first) {
                    e.preventDefault();
                    last.focus();
                } else if (!e.shiftKey && document.activeElement === last) {
                    e.preventDefault();
                    first.focus();
                }
            }
        });
    })();
</script>