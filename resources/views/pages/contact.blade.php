<x-layouts.site title="Seven Rock Radio - Contacto" description="Contacta con Seven Rock Radio. Envianos tus mensajes, sugerencias o colaboraciones. Somos tu radio rock online.">
    @php
        $contact = $themeAppearance['contact'];
        $ui = $themeAppearance['ui_texts'];
    @endphp

    <x-sections.page-heading title="Contacto" overlay="rgba(0,0,0,0)" :image="$themeAppearance['background_url']" />

    <section>
        <div class="lucille-content-box">
            <div class="grid gap-12 lg:grid-cols-2">
                <div class="md:pr-[15px]">
                    <h3 class="mb-10 mt-[30px] font-display text-[16px] font-light tracking-[.04em] text-[#dcdcdc]">{{ $contact['form_title'] }}</h3>
                    <form method="POST" action="{{ route("contact.send") }}" class="space-y-6">
                        @csrf
                        <div class="hidden" style="display:none !important" aria-hidden="true">
                            <input type="text" name="user_website" tabindex="-1" autocomplete="off">
                        </div>
                        <div class="grid gap-6 md:grid-cols-3">
                            <input type="text" name="name" placeholder="{{ $ui['your_name'] }}" class="lucille-form-field w-full" required>
                            <input type="email" name="email" placeholder="{{ $ui['email_address'] }}" class="lucille-form-field w-full" required>
                            <input type="tel" name="phone" placeholder="{{ $ui['phone'] }}" class="lucille-form-field w-full">
                        </div>
                        <textarea name="message" placeholder="{{ $ui['write_comment'] }}" rows="10" class="lucille-form-field min-h-[220px] w-full" required></textarea>
                        <div class="pt-2">
                            <button type="submit" class="lucille-button-solid">{{ $ui['send_email'] }}</button>
                        </div>
                    </form>
                </div>

                <div class="md:pl-[15px]">
                    <h3 class="mb-10 mt-[30px] font-display text-[16px] font-light tracking-[.04em] text-[#dcdcdc]">{{ $contact['info_title'] }}</h3>
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