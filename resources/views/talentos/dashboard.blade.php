<x-layouts.site :title="'Talentos - Dashboard'">
    <section class="mx-auto max-w-6xl px-5 pt-10">
        @if (session('status'))
            <div class="mb-6 border border-[#1e4d2b] bg-[rgba(16,64,30,.2)] px-4 py-3 text-sm text-[#b8e6c3]">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex flex-wrap items-end justify-between gap-4 border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
            <div>
                <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">{{ $talent->band_name }}</h1>
                <p class="mt-2 text-sm text-[#7b7b7b]">{{ $talent->bio ?: 'No bio yet.' }}</p>
            </div>
            <div class="text-right text-sm text-[#7b7b7b]">
                <div class="font-display text-xs uppercase tracking-[.18em] text-[#dcdcdc]">{{ ucfirst($talent->plan) }} plan</div>
                <div>{{ ucfirst($talent->subscription_status) }}</div>
                <div>{{ $subscription?->end_date?->format('d M Y') ?? 'No renewal date' }}</div>
            </div>
        </div>

        <div class="mt-6 grid gap-4 md:grid-cols-4">
            <div class="border border-[#2b2b2b] bg-[#151515] p-5">
                <div class="text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Fotos usadas</div>
                <div class="mt-2 font-display text-3xl text-[#dcdcdc]">{{ $used['photo'] }}</div>
            </div>
            <div class="border border-[#2b2b2b] bg-[#151515] p-5">
                <div class="text-xs uppercase tracking-[.18em] text-[#7b7b7b]">MP3 usados</div>
                <div class="mt-2 font-display text-3xl text-[#dcdcdc]">{{ $used['mp3'] }}</div>
            </div>
            <div class="border border-[#2b2b2b] bg-[#151515] p-5">
                <div class="text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Documentos</div>
                <div class="mt-2 font-display text-3xl text-[#dcdcdc]">{{ $used['document'] }}</div>
            </div>
            <div class="border border-[#2b2b2b] bg-[#151515] p-5">
                <div class="text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Interacciones</div>
                <div class="mt-2 font-display text-3xl text-[#dcdcdc]">{{ $used['interacts'] }}</div>
            </div>
        </div>

        <div class="mt-8 grid gap-6 lg:grid-cols-[1.2fr_.8fr]">
            <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-6">
                <div class="flex items-center justify-between">
                    <h2 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">Media reciente</h2>
                    <a href="{{ route('talents.media.index') }}" class="lucille-button">Manage media</a>
                </div>
                <div class="mt-5 space-y-3">
                    @forelse ($media as $item)
                        <div class="border border-[#2b2b2b] bg-[#151515] p-4 text-sm text-[#7b7b7b]">
                            <div class="font-display text-sm uppercase tracking-[.12em] text-[#dcdcdc]">{{ $item->title ?: $item->filename }}</div>
                            <div class="mt-1">{{ ucfirst($item->type) }} · {{ number_format($item->size / 1024, 1) }} KB</div>
                        </div>
                    @empty
                        <div class="text-sm text-[#7b7b7b]">No media uploaded yet.</div>
                    @endforelse
                </div>
            </div>

            <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-6">
                <h2 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">Plan limits</h2>
                <div class="mt-4 space-y-3 text-sm text-[#7b7b7b]">
                    @foreach ($plan['limits'] as $type => $limit)
                        <div class="flex items-center justify-between border-b border-[#2b2b2b] pb-2">
                            <span>{{ ucfirst($type) }}</span>
                            <span class="text-[#dcdcdc]">{{ $limit === 0 ? '0' : $limit }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="mt-5 flex flex-wrap gap-3">
                    <a href="{{ route('talents.profile') }}" class="lucille-button-solid">Edit profile</a>
                    <a href="{{ route('talents.media.index') }}" class="lucille-button">Media library</a>
                </div>
            </div>
        </div>
    </section>
</x-layouts.site>
