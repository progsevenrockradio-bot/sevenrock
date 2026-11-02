<x-layouts.site title="Seven Rock Radio - Contacto" description="Contacta con Seven Rock Radio. Envianos tus mensajes, sugerencias o colaboraciones. Somos tu radio rock online.">
    @php
        $contact = $themeAppearance['contact'];
        $ui = $themeAppearance['ui_texts'];
    @endphp

    <x-sections.page-heading title="Contacto" subtitle="Escríbenos" overlay="rgba(0,0,0,0)" />

    <section class="py-10 md:py-16">
        <div class="lucille-content-box max-w-[1200px] mx-auto px-4 sm:px-6">
            <div class="grid gap-12 lg:grid-cols-2">
                <div class="md:pr-[15px]">
                    <h3 class="mb-8 font-display text-[18px] uppercase tracking-[.08em] text-[#dcdcdc] font-normal">{{ $contact['form_title'] }}</h3>

                    @if (session('success'))
                        <div class="mb-6 rounded-sm border border-emerald-500/30 bg-emerald-500/10 p-4 text-sm text-emerald-400">
                            {{ session('success') }}
                        </div>
                        <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                if (window.SevenRockAnalytics) {
                                    window.SevenRockAnalytics.track('contact_success', {
                                        form_name: 'contact_page',
                                        subject_category: 'general'
                                    });
                                }
                            });
                        </script>
                    @endif

                    @if (session('error'))
                        <div class="mb-6 rounded-sm border border-red-500/30 bg-red-500/10 p-4 text-sm text-red-400">
                            {{ session('error') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route("contact.send") }}" class="space-y-6" x-data="{ subject: 'general', dropdownOpen: false, selectedLabel: 'Consulta general / Otro' }">
                        @csrf
                        <div class="hidden" style="display:none !important" aria-hidden="true">
                            <input type="text" name="user_website" tabindex="-1" autocomplete="off">
                        </div>
                        <div>
                            <label for="contact-page-name" class="block font-display text-[11px] uppercase tracking-[.14em] text-[#dcdcdc] mb-1.5">{{ $ui['your_name'] ?? 'Tu nombre *' }}</label>
                            <input type="text" id="contact-page-name" name="name" placeholder="{{ $ui['your_name'] }}" class="lucille-form-field w-full" required>
                        </div>
                        <div>
                            <label for="contact-page-email" class="block font-display text-[11px] uppercase tracking-[.14em] text-[#dcdcdc] mb-1.5">{{ $ui['email_address'] ?? 'Correo electrónico *' }}</label>
                            <input type="email" id="contact-page-email" name="email" placeholder="{{ $ui['email_address'] }}" class="lucille-form-field w-full" required>
                        </div>
                        <div>
                            <label for="contact-page-phone" class="block font-display text-[11px] uppercase tracking-[.14em] text-[#dcdcdc] mb-1.5">{{ $ui['phone'] ?? 'Teléfono' }}</label>
                            <input type="tel" id="contact-page-phone" name="phone" placeholder="{{ $ui['phone'] }}" class="lucille-form-field w-full">
                        </div>
                        
                        <div class="relative w-full">
                            <label id="contact-page-subject-label" class="block font-display text-[11px] uppercase tracking-[.14em] text-[#dcdcdc] mb-1.5">Asunto / Motivo</label>
                            <input type="hidden" name="subject" :value="subject">
                            
                            <button type="button" aria-labelledby="contact-page-subject-label" @click="dropdownOpen = !dropdownOpen" @click.away="dropdownOpen = false" 
                                class="lucille-form-field w-full flex items-center justify-between text-left" 
                                style="color: #dcdcdc; background-color: #1a1a1e; cursor: pointer; height: 46px; padding: 0 14px; border: 1px solid rgba(255,255,255,0.08); font-size: 14px;">
                                <span x-text="selectedLabel"></span>
                                <svg class="w-4 h-4 ml-2 transition-transform duration-200" :class="dropdownOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="14" height="14">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            
                            <ul x-show="dropdownOpen" x-cloak x-transition.opacity 
                                class="absolute left-0 z-30 mt-1 w-full border border-white/10 bg-[#141416] rounded-sm py-1 shadow-2xl" 
                                style="max-height: 250px; overflow-y: auto; list-style: none; padding: 0; margin: 0;">
                                <li>
                                    <button type="button" @click="subject = 'general'; selectedLabel = 'Consulta general / Otro'; dropdownOpen = false" 
                                        class="w-full text-left px-4 py-3 text-sm transition-colors duration-150"
                                        :class="subject === 'general' ? 'bg-[var(--lucille-accent)] text-white' : 'text-[#dcdcdc] hover:bg-white/5'">
                                        Consulta general / Otro
                                    </button>
                                </li>
                                <li>
                                    <button type="button" @click="subject = 'join_radio'; selectedLabel = 'Quiero pertenecer a la radio (Banda / Artista)'; dropdownOpen = false" 
                                        class="w-full text-left px-4 py-3 text-sm transition-colors duration-150"
                                        :class="subject === 'join_radio' ? 'bg-[var(--lucille-accent)] text-white' : 'text-[#dcdcdc] hover:bg-white/5'">
                                        Quiero pertenecer a la radio (Banda / Artista)
                                    </button>
                                </li>
                            </ul>
                        </div>

                        <div x-show="subject === 'join_radio'" x-cloak x-transition.opacity>
                            <label for="contact-page-band-name" class="block font-display text-[11px] uppercase tracking-[.14em] text-[#dcdcdc] mb-1.5">Nombre de la banda o artista *</label>
                            <input type="text" id="contact-page-band-name" name="band_name" placeholder="Nombre de la banda o artista" class="lucille-form-field w-full" :required="subject === 'join_radio'">
                        </div>

                        <div>
                            <label for="contact-page-message" class="block font-display text-[11px] uppercase tracking-[.14em] text-[#dcdcdc] mb-1.5">{{ $ui['write_comment'] ?? 'Tu mensaje *' }}</label>
                            <textarea id="contact-page-message" name="message" placeholder="{{ $ui['write_comment'] }}" rows="7" class="lucille-form-field min-h-[180px] w-full" required></textarea>
                        </div>
                        <div class="pt-2">
                            <button type="submit" class="lucille-button-solid">{{ $ui['send_email'] }}</button>
                        </div>
                    </form>
                </div>

                <div class="md:pl-[15px]">
                    <h3 class="mb-8 font-display text-[18px] uppercase tracking-[.08em] text-[#dcdcdc] font-normal">{{ $contact['info_title'] }}</h3>
                    <div class="space-y-5 text-[15px] leading-7 text-[#7b7b7b]">
                        <p>{{ $contact['address'] }}</p>
                        <p><a href="mailto:{{ $contact['email'] }}" class="transition hover:text-lucille-accent">{{ $contact['email'] }}</a></p>
                        <p>{{ $contact['phone_primary'] }}</p>
                        <p>{{ $contact['phone_secondary'] }}</p>
                    </div>

                    <div class="mt-10 max-w-[500px] text-[14px] leading-[26px] text-[#7b7b7b]">
                        <p>{{ $contact['description'] }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-layouts.site>