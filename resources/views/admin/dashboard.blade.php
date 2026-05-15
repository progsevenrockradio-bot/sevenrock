<x-layouts.admin :title="($themeAppearance['admin_texts']['dashboard_title'] ?? 'Dashboard').' - '.$themeSettings->site_name">
    @php $admin = $themeAppearance['admin_texts']; @endphp
    <div class="grid gap-6 lg:grid-cols-[1.2fr_.8fr]">
        <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
            <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">{{ $admin['dashboard_title'] }}</h1>
            <p class="mt-3 max-w-2xl text-[#7b7b7b]">{{ $admin['dashboard_copy'] }}</p>

            <div class="mt-8 grid gap-4 sm:grid-cols-2">
                <div class="border border-[#2b2b2b] bg-[#151515] p-5">
                    <div class="font-display text-xs uppercase tracking-[.2em] text-[#7b7b7b]">{{ $admin['users_label'] }}</div>
                    <div class="mt-2 font-display text-3xl text-[#dcdcdc]">{{ $stats['users'] }}</div>
                </div>
                <div class="border border-[#2b2b2b] bg-[#151515] p-5">
                    <div class="font-display text-xs uppercase tracking-[.2em] text-[#7b7b7b]">{{ $admin['admins_label'] }}</div>
                    <div class="mt-2 font-display text-3xl text-[#dcdcdc]">{{ $stats['admin_users'] }}</div>
                </div>
                <div class="border border-[#2b2b2b] bg-[#151515] p-5">
                    <div class="font-display text-xs uppercase tracking-[.2em] text-[#7b7b7b]">{{ $admin['albums_label'] }}</div>
                    <div class="mt-2 font-display text-3xl text-[#dcdcdc]">{{ $stats['albums'] }}</div>
                </div>
                <div class="border border-[#2b2b2b] bg-[#151515] p-5">
                    <div class="font-display text-xs uppercase tracking-[.2em] text-[#7b7b7b]">{{ $admin['videos_gallery_label'] }}</div>
                    <div class="mt-2 font-display text-3xl text-[#dcdcdc]">{{ $stats['videos'] }} / {{ $stats['gallery_images'] }}</div>
                </div>
                <div class="border border-[#2b2b2b] bg-[#151515] p-5">
                    <div class="font-display text-xs uppercase tracking-[.2em] text-[#7b7b7b]">{{ $admin['posts_label'] }}</div>
                    <div class="mt-2 font-display text-3xl text-[#dcdcdc]">{{ $stats['posts'] }}</div>
                </div>
                <div class="border border-[#2b2b2b] bg-[#151515] p-5">
                    <div class="font-display text-xs uppercase tracking-[.2em] text-[#7b7b7b]">{{ $admin['products_label'] }}</div>
                    <div class="mt-2 font-display text-3xl text-[#dcdcdc]">{{ $stats['products'] }}</div>
                </div>
            </div>

            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('admin.settings.edit') }}" class="lucille-button-solid">{{ $admin['theme_settings'] }}</a>
                <a href="{{ route('admin.albums.index') }}" class="lucille-button">{{ $admin['albums_label'] }}</a>
                <a href="{{ route('admin.videos.index') }}" class="lucille-button">{{ $admin['videos_heading'] }}</a>
                <a href="{{ route('admin.gallery.index') }}" class="lucille-button">{{ $admin['gallery_heading'] }}</a>
                <a href="{{ route('admin.events.index') }}" class="lucille-button">{{ $admin['events_heading'] }}</a>
                <a href="{{ route('admin.posts.index') }}" class="lucille-button">{{ $admin['posts_heading'] }}</a>
                <a href="{{ route('admin.products.index') }}" class="lucille-button">{{ $admin['products_heading'] }}</a>
                <a href="{{ route('home') }}" class="lucille-button">{{ $admin['open_site'] }}</a>
            </div>
        </section>

        <aside class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
            <h2 class="font-display text-2xl uppercase tracking-[.12em] text-[#dcdcdc]">{{ $admin['current_theme'] }}</h2>
            <div class="mt-6 space-y-4 text-sm text-[#7b7b7b]">
                <p><span class="text-[#dcdcdc]">{{ $admin['dashboard_logo_label'] }}:</span> {{ $settings->logo_path ?? 'Default asset' }}</p>
                <p><span class="text-[#dcdcdc]">{{ $admin['dashboard_background_label'] }}:</span> {{ $settings->background_path ?? 'Default asset' }}</p>
                <p><span class="text-[#dcdcdc]">{{ $admin['dashboard_fonts_label'] }}:</span> {{ $settings->body_font }} / {{ $settings->heading_font }}</p>
                <p><span class="text-[#dcdcdc]">{{ $admin['dashboard_accent_label'] }}:</span> {{ $settings->accent_color }}</p>
            </div>
        </aside>
    </div>
</x-layouts.admin>
