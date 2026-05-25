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
                    <div class="font-display text-xs uppercase tracking-[.2em] text-[#7b7b7b]">Radio artists</div>
                    <div class="mt-2 font-display text-3xl text-[#dcdcdc]">{{ $stats['radio_artists'] }}</div>
                </div>
                <div class="border border-[#2b2b2b] bg-[#151515] p-5">
                    <div class="font-display text-xs uppercase tracking-[.2em] text-[#7b7b7b]">Songs</div>
                    <div class="mt-2 font-display text-3xl text-[#dcdcdc]">{{ $stats['songs'] }}</div>
                </div>
                <div class="border border-[#2b2b2b] bg-[#151515] p-5">
                    <div class="font-display text-xs uppercase tracking-[.2em] text-[#7b7b7b]">Master programs</div>
                    <div class="mt-2 font-display text-3xl text-[#dcdcdc]">{{ $stats['master_programs'] }}</div>
                </div>
                <div class="border border-[#2b2b2b] bg-[#151515] p-5">
                    <div class="font-display text-xs uppercase tracking-[.2em] text-[#7b7b7b]">Radio programs</div>
                    <div class="mt-2 font-display text-3xl text-[#dcdcdc]">{{ $stats['radio_programs'] }}</div>
                </div>
                <div class="border border-[#2b2b2b] bg-[#151515] p-5">
                    <div class="font-display text-xs uppercase tracking-[.2em] text-[#7b7b7b]">{{ $admin['posts_label'] }}</div>
                    <div class="mt-2 font-display text-3xl text-[#dcdcdc]">{{ $stats['posts'] }}</div>
                </div>
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

            <div class="mt-8 border border-[#2b2b2b] bg-[#151515] p-4">
                <div class="font-display text-xs uppercase tracking-[.2em] text-[#7b7b7b]">Atajos</div>
                <div class="mt-4 flex flex-wrap gap-3">
                    <a href="{{ route('admin.settings.edit') }}" class="lucille-button-solid">{{ $admin['theme_settings'] }}</a>
                    <a href="{{ route('admin.master-programs.index') }}" class="lucille-button">Master programs</a>
                    <a href="{{ route('admin.radio-artists.index') }}" class="lucille-button">Radio artists</a>
                    <a href="{{ route('admin.songs.index') }}" class="lucille-button">Songs</a>
                    <a href="{{ route('admin.podcast-uploads.index') }}" class="lucille-button">Podcast uploads</a>
                    <a href="{{ route('admin.posts.index') }}" class="lucille-button">{{ $admin['posts_heading'] }}</a>
                    <a href="#taxonomias" class="lucille-button">Taxonomías</a>
                    <a href="{{ route('home') }}" class="lucille-button">{{ $admin['open_site'] }}</a>
                </div>
            </div>
        </aside>
    </div>

    <section id="taxonomias" class="mt-8 border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="font-display text-2xl uppercase tracking-[.12em] text-[#dcdcdc]">Taxonomías</h2>
                <p class="mt-2 max-w-3xl text-sm text-[#7b7b7b]">
                    Centraliza categorías y etiquetas aquí para reutilizarlas en posts sin escribirlas a mano cada vez.
                </p>
            </div>
            <div class="text-sm text-[#7b7b7b]">
                <span class="text-[#dcdcdc]">Categories:</span> {{ $stats['categories'] }} ·
                <span class="text-[#dcdcdc]">Tags:</span> {{ $stats['tags'] }}
            </div>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            @foreach ([
                ['label' => 'Categories', 'type' => 'category', 'items' => $taxonomies['categories'], 'placeholder' => 'Music, Discussion'],
                ['label' => 'Tags', 'type' => 'tag', 'items' => $taxonomies['tags'], 'placeholder' => 'news, live, music'],
            ] as $group)
                <div class="border border-[#2b2b2b] bg-[#151515] p-5">
                    <div class="flex items-center justify-between gap-4">
                        <h3 class="font-display text-sm uppercase tracking-[.12em] text-[#dcdcdc]">{{ $group['label'] }}</h3>
                        <span class="text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $group['items']->count() }} items</span>
                    </div>

                    <form action="{{ route('admin.taxonomies.store') }}" method="POST" class="mt-4 flex flex-wrap gap-2">
                        @csrf
                        <input type="hidden" name="type" value="{{ $group['type'] }}">
                        <input
                            name="name"
                            class="lucille-product-field min-w-0 flex-1"
                            placeholder="{{ $group['placeholder'] }}"
                        >
                        <button type="submit" class="lucille-button-solid">Add</button>
                    </form>

                    <div class="mt-4 flex flex-wrap gap-2">
                        @forelse ($group['items'] as $taxonomy)
                            <span class="inline-flex items-center gap-2 border border-[#2b2b2b] bg-[rgba(255,255,255,.02)] px-3 py-2 text-xs uppercase tracking-[.12em] text-[#dcdcdc]">
                                {{ $taxonomy->name }}
                                <a href="{{ route('admin.taxonomies.edit', $taxonomy) }}" class="text-[#7b7b7b] transition hover:text-[#dcdcdc]">edit</a>
                                <form
                                    action="{{ route('admin.taxonomies.destroy', $taxonomy) }}"
                                    method="POST"
                                    data-confirm="{{ 'Delete ' . $taxonomy->name . '?' }}"
                                    data-confirm-title="Delete taxonomy"
                                    data-confirm-action="Delete"
                                    data-confirm-tone="danger"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-[#7b7b7b] transition hover:text-[#c32720]">×</button>
                                </form>
                            </span>
                        @empty
                            <p class="text-sm text-[#7b7b7b]">No items yet.</p>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </section>
</x-layouts.admin>
