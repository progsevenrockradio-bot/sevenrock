<x-layouts.admin title="Buzón de Moderación">
    <div class="mb-8">
        <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Buzón de Moderación</h1>
        <p class="mt-2 text-[#9a9a9a]">Revisa y aprueba el contenido generado por usuarios.</p>
    </div>

    @if (session('success'))
        <div class="mb-6 border border-[#1e4d2b] bg-[rgba(16,64,30,.2)] px-4 py-3 text-sm text-[#b8e6c3]">
            {{ session('success') }}
        </div>
    @endif

    <div class="mb-6 flex gap-4">
        <a href="{{ route('admin.moderation.index', ['status' => 'pending']) }}" class="lucille-button {{ $status === 'pending' ? 'lucille-button-solid' : '' }}">
            Pendientes
        </a>
        <a href="{{ route('admin.moderation.index', ['status' => 'approved']) }}" class="lucille-button {{ $status === 'approved' ? 'lucille-button-solid' : '' }}">
            Aprobados
        </a>
        <a href="{{ route('admin.moderation.index', ['status' => 'rejected']) }}" class="lucille-button {{ $status === 'rejected' ? 'lucille-button-solid' : '' }}">
            Denegados
        </a>
    </div>

    @if($items->isEmpty())
        <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8 text-center text-[#7b7b7b]">
            No hay elementos {{ $status === 'pending' ? 'pendientes de moderación' : ($status === 'approved' ? 'aprobados' : 'denegados') }}.
        </div>
    @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach($items as $item)
                <div class="flex flex-col border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-5">
                    <div class="mb-3 flex items-center justify-between">
                        <span class="inline-flex rounded-full bg-[#1a1a1c] border border-[#2b2b2b] px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-[#dcdcdc]">
                            {{ str_replace('_', ' ', $item->type) }}
                        </span>
                        <span class="text-xs text-[#7b7b7b]">{{ $item->created_at->diffForHumans() }}</span>
                    </div>
                    
                    <h3 class="mb-2 font-display text-lg tracking-[.12em] text-[#dcdcdc] line-clamp-1">
                        {{ $item->title }}
                    </h3>
                    
                    <p class="mb-4 text-sm text-[#9a9a9a] line-clamp-2">
                        {{ $item->summary }}
                    </p>

                    <div class="mb-4 text-xs text-[#7b7b7b]">
                        <div><strong class="text-[#dcdcdc]">De:</strong> {{ $item->submitter_name }}</div>
                        @if($item->submitter_email)
                            <div><strong class="text-[#dcdcdc]">Correo:</strong> {{ $item->submitter_email }}</div>
                        @endif
                    </div>

                    <div class="mt-auto pt-4 border-t border-[#2b2b2b] flex items-center justify-between">
                        <a href="{{ route('admin.moderation.show', $item) }}" class="lucille-button text-xs px-3 py-1">
                            Ver detalles
                        </a>
                        
                        @if($item->status === 'pending')
                            <div class="flex gap-2">
                                <form action="{{ route('admin.moderation.approve', $item) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="lucille-button-solid text-xs px-3 py-1 bg-[#1e4d2b] border-[#1e4d2b] hover:bg-[#163820]">
                                        ✅
                                    </button>
                                </form>
                                <form action="{{ route('admin.moderation.reject', $item) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="lucille-button-solid text-xs px-3 py-1 bg-[#7a2b2b] border-[#7a2b2b] hover:bg-[#5c2020]">
                                        ❌
                                    </button>
                                </form>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-8">
            {{ $items->links() }}
        </div>
    @endif
</x-layouts.admin>
