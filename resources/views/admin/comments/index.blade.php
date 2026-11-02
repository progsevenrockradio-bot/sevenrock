@php $admin = $themeAppearance['admin_texts']; @endphp
<x-layouts.admin :title="'Comentarios - '.$themeSettings->site_name">
    @if (session('status') || session('success'))
        <div class="mb-6 flex items-center justify-between rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
            <div class="flex items-center gap-2">
                <svg class="h-4 w-4 shrink-0 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span>{{ session('status') ?? session('success') }}</span>
            </div>
            <button type="button" @click="$el.parentElement.remove()" class="text-emerald-400 hover:text-emerald-200 text-xs">✕</button>
        </div>
    @endif

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="font-display text-2xl sm:text-3xl uppercase tracking-wider text-[#dcdcdc]">Comentarios</h1>
            <p class="mt-1 text-sm text-gray-400">
                Moderación de comentarios de oyentes y lectores en noticias y artículos.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-white/10 bg-white/5 text-xs text-gray-300">
                Total: <strong class="text-white">{{ $comments->count() }}</strong>
            </span>
            @if ($pendingCount > 0)
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-amber-500/30 bg-amber-500/10 text-xs font-medium text-amber-300">
                    ⏳ <strong>{{ $pendingCount }}</strong> pendientes
                </span>
            @endif
        </div>
    </div>

    <div class="rounded-xl border border-white/10 bg-[#101012] shadow-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-white/10 bg-white/[0.02] text-xs font-medium uppercase tracking-wider text-gray-400">
                    <tr>
                        <th class="px-5 py-3.5 whitespace-nowrap">Autor</th>
                        <th class="px-5 py-3.5">Comentario</th>
                        <th class="px-5 py-3.5">Artículo</th>
                        <th class="px-5 py-3.5 whitespace-nowrap">Fecha</th>
                        <th class="px-5 py-3.5 whitespace-nowrap">Estado</th>
                        <th class="px-5 py-3.5 text-right whitespace-nowrap">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5 text-gray-300">
                    @forelse ($comments as $comment)
                        <tr class="hover:bg-white/[0.02] transition-colors">
                            <td class="px-5 py-4 whitespace-nowrap align-top">
                                <div class="font-medium text-white text-sm">
                                    {{ $comment->author_name ?: 'Anónimo' }}
                                </div>
                                @if ($comment->author_email)
                                    <div class="text-xs text-gray-400 mt-0.5">{{ $comment->author_email }}</div>
                                @endif
                            </td>
                            <td class="px-5 py-4 max-w-sm align-top">
                                <div class="text-xs text-gray-300 leading-relaxed line-clamp-3">
                                    {{ Str::limit($comment->content, 140) }}
                                </div>
                            </td>
                            <td class="px-5 py-4 align-top">
                                <a href="{{ route('posts.single', ['year' => $comment->post?->year ?: '2026', 'month' => $comment->post?->month ?: '01', 'day' => $comment->post?->day ?: '01', 'slug' => $comment->post?->slug ?: '']) }}"
                                   class="text-xs text-gray-300 hover:text-white hover:underline transition-colors block line-clamp-2" target="_blank">
                                    {{ $comment->post?->title ?: '—' }}
                                </a>
                            </td>
                            <td class="px-5 py-4 whitespace-nowrap text-xs text-gray-400 align-top">
                                {{ $comment->created_at?->format('d M Y H:i') }}
                            </td>
                            <td class="px-5 py-4 whitespace-nowrap align-top">
                                @if ($comment->approved)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-medium border border-emerald-500/20 bg-emerald-500/10 text-emerald-400">
                                        Aprobado
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-medium border border-amber-500/20 bg-amber-500/10 text-amber-300">
                                        Pendiente
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right whitespace-nowrap align-top">
                                <div class="inline-flex items-center justify-end gap-1.5">
                                    @if (!$comment->approved)
                                        <form action="{{ route('admin.comments.approve', $comment) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="inline-flex items-center justify-center h-8 px-2.5 rounded-lg border border-emerald-500/30 bg-emerald-500/10 text-emerald-300 hover:bg-emerald-500/20 text-xs font-medium transition-all">
                                                Aprobar
                                            </button>
                                        </form>
                                    @else
                                        <form action="{{ route('admin.comments.unapprove', $comment) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="inline-flex items-center justify-center h-8 px-2.5 rounded-lg border border-amber-500/30 bg-amber-500/10 text-amber-300 hover:bg-amber-500/20 text-xs font-medium transition-all">
                                                Desaprobar
                                            </button>
                                        </form>
                                    @endif

                                    <a href="{{ route('admin.comments.edit', $comment) }}" class="inline-flex items-center justify-center h-8 px-2.5 rounded-lg border border-white/10 bg-white/5 hover:bg-white/10 text-gray-200 text-xs font-medium transition-all">
                                        Editar
                                    </a>

                                    <form
                                        action="{{ route('admin.comments.destroy', $comment) }}"
                                        method="POST"
                                        class="inline"
                                        data-confirm="¿Eliminar este comentario de {{ $comment->author_name ?: 'Anónimo' }} definitivamente?"
                                        data-confirm-title="Eliminar comentario"
                                        data-confirm-action="Eliminar"
                                        data-confirm-tone="danger"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex items-center justify-center h-8 px-2.5 rounded-lg border border-rose-900/40 bg-rose-950/20 hover:bg-rose-900/30 text-rose-300 text-xs font-medium transition-all">
                                            Eliminar
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-12 text-center text-gray-500">No hay comentarios todavía.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.admin>
