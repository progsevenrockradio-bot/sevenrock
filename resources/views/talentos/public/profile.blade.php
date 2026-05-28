<x-layouts.site :title="$talent->band_name . ' - Seven Rock Radio'"
    :description="$talent->bio ? Str::limit($talent->bio, 160) : 'Perfil de ' . $talent->band_name . ' en Seven Rock Radio'"
    :og-image="$talent->logoUrl() ?? asset('assets/lucille/logo.png')">
    <section class="mx-auto max-w-7xl px-5 py-16">
        <div class="talent-cover">
            <div class="talent-avatar">
                <img src="{{ $talent->logoUrl() ?? asset('assets/lucille/beatles_t_shirt.jpeg') }}" alt="{{ $talent->band_name }}" loading="lazy">
            </div>
            <h1 class="talent-name">{{ $talent->band_name }}</h1>
            <div class="talent-plan-badge">
                {{ ucfirst($talent->plan) }}
                @if ($talent->is_featured) <span class="ml-2 text-[#ffd24d]">⭐ Destacado</span>@endif
            </div>
        </div>

        <div class="talent-stats">
            <div class="stat">
                <span class="stat-number like-count">{{ $likesCount }}</span>
                <span class="stat-label">Likes</span>
            </div>
            <div class="stat">
                <span class="stat-number">{{ $media->count() }}</span>
                <span class="stat-label">Archivos</span>
            </div>
            <div class="stat">
                <span class="stat-number">{{ $viewsCount }}</span>
                <span class="stat-label">Visitas</span>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-[1fr_.8fr]">
            <div class="space-y-6">
                <div class="border border-white/10 bg-[#10161b] p-6">
                    <h3 class="font-display text-xl uppercase tracking-[.12em] text-white">Biografía</h3>
                    <p class="mt-3 text-sm leading-7 text-[#c9c9c9]">{{ $talent->bio ?: 'Sin biografía aún' }}</p>
                </div>

                @php($socialLinks = $talent->socialLinkMap())
                @if ($socialLinks !== [])
                    <div class="border border-white/10 bg-[#10161b] p-6">
                        <h3 class="font-display text-xl uppercase tracking-[.12em] text-white">Sigue al artista</h3>
                        <div class="mt-4 flex flex-wrap gap-3">
                            @foreach ($socialLinks as $network => $url)
                                <a href="{{ $url }}" target="_blank" rel="noreferrer" class="lucille-button">{{ ucfirst($network) }}</a>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($media->isNotEmpty())
                    <div class="border border-white/10 bg-[#10161b] p-6">
                        <h3 class="font-display text-xl uppercase tracking-[.12em] text-white">Contenido</h3>
                        <div class="mt-5 grid gap-4 md:grid-cols-2">
                            @foreach ($media as $item)
                                <article class="media-card" data-type="{{ $item->type }}">
                                    @if ($item->type === 'photo')
                                        <img src="{{ $item->url }}" alt="{{ $item->title }}" class="w-full object-cover" loading="lazy">
                                    @elseif ($item->type === 'mp3')
                                        <div class="audio-player">
                                            <p class="text-white">{{ $item->title }}</p>
                                            <audio controls src="{{ $item->url }}" class="w-full"></audio>
                                        </div>
                                    @elseif ($item->type === 'video')
                                        <video controls src="{{ $item->url }}" class="w-full"></video>
                                    @else
                                        <a href="{{ $item->url }}" target="_blank" class="doc-link text-white">📄 {{ $item->title ?? $item->filename }}</a>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if (($products ?? collect())->isNotEmpty())
                    <div class="talent-store-section">
                        <h3>🛒 Tienda de {{ $talent->band_name }}</h3>
                        <p class="store-disclaimer">
                            Las ventas son gestionadas directamente por la banda.
                            Seven Rock Radio actúa solo como escaparate.
                        </p>
                        <div class="store-grid">
                            @foreach ($products as $product)
                                <div class="store-card">
                                    @if ($product->image_url)
                                        <img src="{{ $product->image_url }}" alt="{{ $product->title }}" loading="lazy">
                                    @endif
                                    <h4>{{ $product->title }}</h4>
                                    <p class="price">{{ number_format((float) $product->price, 2) }} €</p>
                                    <p class="desc">{{ \Illuminate\Support\Str::limit((string) $product->description, 100) }}</p>
                                    @if ($product->external_payment_url)
                                        <a href="{{ $product->external_payment_url }}" target="_blank" rel="nofollow noopener" class="btn-external-pay">
                                            {{ $product->external_payment_label ?: 'Comprar' }} ↗
                                        </a>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @php($paymentLinks = $talent->paymentLinkMap())
                @if ($paymentLinks !== [])
                    <div class="border border-white/10 bg-[#10161b] p-6">
                        <h3 class="font-display text-xl uppercase tracking-[.12em] text-white">Acepto pagos vía</h3>
                        <div class="mt-4 flex flex-wrap gap-3">
                            @foreach ($paymentLinks as $label => $url)
                                <a href="{{ $url }}" target="_blank" rel="nofollow noopener" class="lucille-button">{{ ucfirst($label) }}</a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="space-y-6">
                <div class="border border-white/10 bg-[#10161b] p-6">
                    <button type="button" class="btn-like" data-band="{{ $talent->band_name }}">
                        👍 Like (<span class="like-count">{{ $likesCount }}</span>)
                    </button>
                    <form method="POST" action="{{ route('talents.comment', ['bandName' => $talent->band_name]) }}" class="comment-section mt-6">
                        @csrf
                        <h4 class="font-display text-xl uppercase tracking-[.12em] text-white">Comentarios</h4>
                        <textarea name="content" placeholder="Deja un comentario..." maxlength="500" required class="mt-4 w-full rounded-[8px] border border-white/10 bg-[#151515] p-4 text-white"></textarea>
                        <button type="submit" class="mt-3 lucille-button-solid">Comentar</button>
                    </form>
                </div>

                <div class="border border-white/10 bg-[#10161b] p-6">
                    <h4 class="font-display text-xl uppercase tracking-[.12em] text-white">Comentarios recientes</h4>
                    <div class="comments-list mt-4 space-y-3">
                        @forelse ($topComments as $comment)
                            <div class="comment">
                                <strong>Anónimo:</strong>
                                <p>{{ $comment->content }}</p>
                                <small>{{ $comment->created_at->diffForHumans() }}</small>
                            </div>
                        @empty
                            <div class="text-sm text-[#8b8b8b]">Todavía no hay comentarios.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </section>

    @push('scripts')
        <script>
            document.querySelector('.btn-like')?.addEventListener('click', function () {
                const band = this.dataset.band;

                fetch(`/talentos/${encodeURIComponent(band)}/like`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                })
                    .then((response) => response.json())
                    .then((data) => {
                        if (typeof data.likes !== 'undefined') {
                            document.querySelectorAll('.like-count').forEach((node) => {
                                node.textContent = data.likes;
                            });
                        }

                        if (data.error) {
                            alert(data.error);
                        }
                    });
            });
        </script>
    @endpush
</x-layouts.site>
