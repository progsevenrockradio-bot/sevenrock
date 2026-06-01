@props(['title' => 'Seven Rock Radio Admin'])

@php
    $theme = $themeSettings ?? \App\Models\ThemeSetting::current();
    $themeAppearance = $themeAppearance ?? \App\Support\ThemeAppearance::resolved();
    $admin = $themeAppearance['admin_texts'] ?? [];
    $adminHomeUrl = auth()->check() ? route('admin.dashboard') : route('admin.login');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="{{ $theme->google_fonts_url }}" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body
    class="antialiased"
    style="
        --lucille-accent: {{ $theme->accent_color }};
        --lucille-nav: {{ $theme->nav_color }};
        --lucille-surface: {{ $theme->surface_color }};
        --lucille-body: {{ $theme->body_color }};
        --lucille-heading: {{ $theme->heading_color }};
        --lucille-line: {{ $theme->line_color }};
        --lucille-body-font: '{{ $theme->body_font }}';
        --lucille-heading-font: '{{ $theme->heading_font }}';
        --lucille-brand-font: '{{ $theme->brand_mark_font }}';
        --lucille-bg-image: url('{{ $theme->background_url }}');
    "
    >
    <div class="lucille-fixed-bg" aria-hidden="true"></div>

    <div id="admin-confirm-modal" class="fixed inset-0 z-[200] hidden" aria-hidden="true">
        <button
            id="admin-confirm-backdrop"
            type="button"
            class="absolute inset-0 cursor-default bg-[rgba(0,0,0,.78)] backdrop-blur-md"
            aria-label="Cerrar confirmación"
        ></button>

        <section
            role="dialog"
            aria-modal="true"
            aria-labelledby="admin-confirm-title"
            class="absolute left-1/2 top-1/2 w-[min(92vw,24rem)] -translate-x-1/2 -translate-y-1/2 overflow-hidden border border-[rgba(220,220,220,.16)] bg-[rgba(12,12,13,.96)] shadow-[0_30px_90px_rgba(0,0,0,.72)]"
        >
            <div id="admin-confirm-tone" class="h-1 w-full bg-[#c32720]"></div>

            <div class="relative p-5 sm:p-6">
                <div class="flex items-start gap-3">
                    <div
                        id="admin-confirm-icon"
                        class="mt-1 flex h-10 w-10 shrink-0 items-center justify-center border text-[10px] font-bold uppercase tracking-[.22em] border-[#5c2a2a] bg-[rgba(195,39,32,.12)] text-[#ffd0d0]"
                    >
                        !
                    </div>

                    <div class="min-w-0 flex-1">
                        <p id="admin-confirm-kicker" class="font-display text-[9px] uppercase tracking-[.28em] text-[#8a8a8a]">Acción destructiva</p>
                        <h2 id="admin-confirm-title" class="mt-1 font-display text-[1.45rem] uppercase tracking-[.06em] text-[#f2f2f2]"></h2>
                        <div class="mt-2 inline-flex items-center gap-2 border border-[rgba(220,220,220,.16)] bg-[rgba(255,255,255,.03)] px-2.5 py-1 text-[9px] uppercase tracking-[.22em] text-[#9d9d9d]">
                            <span>Método</span>
                            <span id="admin-confirm-method"></span>
                        </div>
                        <p id="admin-confirm-message" class="mt-2 text-[13px] leading-6 text-[#c3c3c3]"></p>
                    </div>
                </div>

                <div class="mt-5 flex flex-wrap justify-end gap-2">
                    <button type="button" id="admin-confirm-cancel" class="lucille-button">Cancelar</button>
                    <button type="button" id="admin-confirm-accept" class="lucille-button-solid">Confirmar</button>
                </div>
            </div>
        </section>
    </div>

    @php
        $brandDisplayMode = $theme->brand_display_mode ?? 'mark';
    @endphp

    <header class="mx-auto flex max-w-6xl items-center justify-between px-6 py-6">
        <a href="{{ $adminHomeUrl }}" class="flex items-center gap-3">
            @if ($brandDisplayMode === 'logo')
                <img src="{{ $theme->logo_url }}" alt="{{ $theme->site_name }}" loading="lazy" class="h-10 w-auto">
            @else
                <span class="lucille-brand-mark text-[1.9rem]">{{ $theme->brand_mark ?: $theme->site_name }}</span>
            @endif
            <span class="rounded border border-[#2b2b2b] px-3 py-1 font-display text-xs uppercase tracking-[.18em] text-[#dcdcdc]">
                {{ $admin['admin_suffix'] ?? 'Admin' }}
            </span>
        </a>

        <div class="flex items-center gap-3">
            <a href="{{ route('home') }}" class="lucille-button">{{ $admin['view_site'] }}</a>
            @auth
                <form action="{{ route('admin.logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="lucille-button-solid">{{ $admin['logout'] }}</button>
                </form>
            @endauth
        </div>
    </header>

    @auth
        <div class="mx-auto max-w-6xl px-6 pb-4">
            <nav class="lucille-admin-dropdowns" aria-label="Admin sections">
                <details class="lucille-admin-dropdown">
                    <summary class="lucille-admin-dropdown-summary">
                        <span>Site</span>
                        <span class="lucille-admin-dropdown-caret" aria-hidden="true">▾</span>
                    </summary>
                    <div class="lucille-admin-dropdown-panel">
                        <a href="{{ route('admin.dashboard') }}" class="lucille-admin-link">{{ $admin['dashboard_heading'] ?? 'Dashboard' }}</a>
                        <a href="{{ route('admin.posts.index') }}" class="lucille-admin-link">{{ $admin['posts_heading'] ?? 'Posts' }}</a>
                        <a href="{{ route('admin.events.index') }}" class="lucille-admin-link">{{ $admin['events_heading'] ?? 'Events' }}</a>
                        <a href="{{ route('admin.events.single') }}" class="lucille-admin-link">Single Event</a>
                        <a href="{{ route('admin.audit-logs.index') }}" class="lucille-admin-link">Audit trail</a>
                        <a href="{{ route('admin.comments.index') }}" class="lucille-admin-link">Comentarios</a>
                    </div>
                </details>

                <details class="lucille-admin-dropdown">
                    <summary class="lucille-admin-dropdown-summary">
                        <span>Programas</span>
                        <span class="lucille-admin-dropdown-caret" aria-hidden="true">▾</span>
                    </summary>
                    <div class="lucille-admin-dropdown-panel">
                        <a href="{{ route('admin.master-programs.index') }}" class="lucille-admin-link">{{ $admin['master_programs_heading'] ?? 'Master Programs' }}</a>
                        <a href="{{ route('admin.podcast-uploads.index') }}" class="lucille-admin-link">{{ $admin['podcast_uploads_heading'] ?? 'Podcast Uploads' }}</a>
                        <a href="{{ route('admin.songs.index') }}" class="lucille-admin-link">{{ $admin['songs_heading'] ?? 'Songs' }}</a>
                    </div>
                </details>

                <details class="lucille-admin-dropdown">
                    <summary class="lucille-admin-dropdown-summary">
                        <span>Bandas</span>
                        <span class="lucille-admin-dropdown-caret" aria-hidden="true">▾</span>
                    </summary>
                    <div class="lucille-admin-dropdown-panel">
                        <a href="{{ route('admin.albums.index') }}" class="lucille-admin-link">{{ $admin['albums_heading'] ?? 'Albums' }}</a>
                        <a href="{{ route('admin.videos.index') }}" class="lucille-admin-link">{{ $admin['videos_heading'] ?? 'Videos' }}</a>
                        <a href="{{ route('admin.gallery.index') }}" class="lucille-admin-link">{{ $admin['gallery_heading'] ?? 'Gallery' }}</a>
                        <a href="{{ route('admin.radio-artists.index') }}" class="lucille-admin-link">Radio Artists</a>
                    </div>
                </details>

                <details class="lucille-admin-dropdown">
                    <summary class="lucille-admin-dropdown-summary">
                        <span>Talents</span>
                        <span class="lucille-admin-dropdown-caret" aria-hidden="true">▾</span>
                    </summary>
                    <div class="lucille-admin-dropdown-panel">
                        <a href="{{ route('admin.talents.index') }}" class="lucille-admin-link">Talents</a>
                        <a href="{{ route('admin.talents.media') }}" class="lucille-admin-link">Media</a>
                    </div>
                </details>

                <details class="lucille-admin-dropdown">
                    <summary class="lucille-admin-dropdown-summary">
                        <span>Convocatoria</span>
                        <span class="lucille-admin-dropdown-caret" aria-hidden="true">▾</span>
                    </summary>
                    <div class="lucille-admin-dropdown-panel">
                        <a href="{{ route('admin.programs.index') }}" class="lucille-admin-link">🎙️ Programas</a>
                        <a href="{{ route('admin.outreach.index') }}" class="lucille-admin-link">Outreach</a>
                    </div>
                </details>
            </nav>
        </div>
    @endauth

    <main class="mx-auto max-w-6xl px-6 pb-16">
        {{ $slot }}
    </main>

    <script>
        (() => {
            const modal = document.getElementById('admin-confirm-modal');
            if (!modal) {
                return;
            }

            const backdrop = document.getElementById('admin-confirm-backdrop');
            const titleEl = document.getElementById('admin-confirm-title');
            const messageEl = document.getElementById('admin-confirm-message');
            const methodEl = document.getElementById('admin-confirm-method');
            const kickerEl = document.getElementById('admin-confirm-kicker');
            const toneBar = document.getElementById('admin-confirm-tone');
            const icon = document.getElementById('admin-confirm-icon');
            const cancelBtn = document.getElementById('admin-confirm-cancel');
            const acceptBtn = document.getElementById('admin-confirm-accept');

            let activeForm = null;
            let activeSubmitter = null;

            const resetTone = () => {
                toneBar.className = 'h-1 w-full bg-[#c32720]';
                icon.className = 'mt-1 flex h-12 w-12 shrink-0 items-center justify-center border text-sm font-bold uppercase tracking-[.22em] border-[#5c2a2a] bg-[rgba(195,39,32,.12)] text-[#ffd0d0]';
                kickerEl.textContent = 'Acción destructiva';
            };

            const setTone = (tone) => {
                if (tone === 'soft') {
                    toneBar.className = 'h-1 w-full bg-[var(--color-lucille-accent)]';
                    icon.className = 'mt-1 flex h-12 w-12 shrink-0 items-center justify-center border text-sm font-bold uppercase tracking-[.22em] border-[rgba(220,220,220,.16)] bg-[rgba(255,255,255,.03)] text-[#dcdcdc]';
                    kickerEl.textContent = 'Confirmación';
                    return;
                }

                resetTone();
            };

            const openModal = (form, submitter) => {
                activeForm = form;
                activeSubmitter = submitter ?? null;

                const message = (form.dataset.confirm ?? '').trim();
                const title = (form.dataset.confirmTitle ?? '').trim() || 'Confirmar acción';
                const confirmLabel = (form.dataset.confirmAction ?? '').trim() || 'Confirmar';
                const cancelLabel = (form.dataset.confirmCancel ?? '').trim() || 'Cancelar';
                const tone = (form.dataset.confirmTone ?? 'danger').trim();
                const method = (form.querySelector('input[name="_method"]')?.value || form.method || 'POST').toUpperCase();

                titleEl.textContent = title;
                messageEl.textContent = message;
                methodEl.textContent = method;
                cancelBtn.textContent = cancelLabel;
                acceptBtn.textContent = confirmLabel;
                setTone(tone);

                modal.classList.remove('hidden');
                modal.classList.add('flex');
                document.body.classList.add('overflow-hidden');
                cancelBtn.focus({ preventScroll: true });
            };

            const closeModal = () => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                document.body.classList.remove('overflow-hidden');
                activeForm = null;
                activeSubmitter = null;
            };

            const submitActiveForm = () => {
                if (!activeForm) {
                    closeModal();
                    return;
                }

                const form = activeForm;
                const submitter = activeSubmitter;

                closeModal();
                form.dataset.confirmBypass = '1';

                window.setTimeout(() => {
                    delete form.dataset.confirmBypass;
                }, 0);

                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit(submitter ?? undefined);
                    return;
                }

                form.submit();
            };

            document.addEventListener('submit', (event) => {
                const form = event.target;
                if (! (form instanceof HTMLFormElement)) {
                    return;
                }

                if (form.dataset.confirmBypass === '1') {
                    return;
                }

                const message = (form.dataset.confirm ?? '').trim();
                if (message === '') {
                    return;
                }

                event.preventDefault();
                event.stopImmediatePropagation();
                openModal(form, event.submitter ?? null);
            }, true);

            backdrop?.addEventListener('click', closeModal);
            cancelBtn?.addEventListener('click', closeModal);
            acceptBtn?.addEventListener('click', submitActiveForm);
            window.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                    closeModal();
                }
            });

            document.addEventListener('click', function(e) {
                const summary = e.target.closest('.lucille-admin-dropdown summary');
                if (!summary) return;
                const clicked = summary.closest('.lucille-admin-dropdown');
                document.querySelectorAll('.lucille-admin-dropdown').forEach(details => {
                    if (details !== clicked && details.open) details.open = false;
                });
            });

            resetTone();
            closeModal();
        })();
    </script>
</body>
</html>
