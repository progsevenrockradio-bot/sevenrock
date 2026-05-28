@php $admin = $themeAppearance['admin_texts']; @endphp
<x-layouts.admin :title="'Comentarios - '.$themeSettings->site_name">
    @if (session('status'))
        <div class="mb-6 border border-[#1e4d2b] bg-[rgba(16,64,30,.2)] px-4 py-3 text-sm text-[#b8e6c3]">
            {{ session('status') }}
        </div>
    @endif

    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Comentarios</h1>
            <p class="mt-2 text-[#7b7b7b]">
                {{ $comments->count() }} comentarios en total
                @if ($pendingCount > 0)
                    · <span class="text-[#e6b800]">{{ $pendingCount }} pendientes de aprobar</span>
                @endif
            </p>
        </div>
    </div>

    <div class="overflow-hidden border border-[#2b2b2b] bg-[rgba(16,16,18,.88)]">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-[#2b2b2b] text-[#dcdcdc]">
                <tr>
                    <th class="px-5 py-4">Autor</th>
                    <th class="px-5 py-4">Comentario</th>
                    <th class="px-5 py-4">Post</th>
                    <th class="px-5 py-4">Fecha</th>
                    <th class="px-5 py-4">Estado</th>
                    <th class="px-5 py-4">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#2b2b2b] text-[#7b7b7b]">
                @forelse ($comments as $comment)
                    <tr class="hover:bg-[rgba(255,255,255,.02)]">
                        <td class="px-5 py-4">
                            <div class="font-display text-[15px] uppercase tracking-[.08em] text-[#dcdcdc]">
                                {{ $comment->author_name ?: 'Anónimo' }}
                            </div>
                            @if ($comment->author_email)
                                <div class="mt-1 text-xs text-[#7b7b7b]">{{ $comment->author_email }}</div>
                            @endif
                        </td>
                        <td class="px-5 py-4 max-w-xs">
                            <div class="line-clamp-3 text-[13px] leading-relaxed text-[#c3c3c3]">
                                {{ Str::limit($comment->content, 120) }}
                            </div>
                        </td>
                        <td class="px-5 py-4">
                            <a href="{{ route('posts.single', ['year' => $comment->post?->year ?: '2026', 'month' => $comment->post?->month ?: '01', 'day' => $comment->post?->day ?: '01', 'slug' => $comment->post?->slug ?: '']) }}"
                               class="text-[#dcdcdc] hover:underline" target="_blank">
                                {{ Str::limit($comment->post?->title ?: '—', 30) }}
                            </a>
                        </td>
                        <td class="px-5 py-4 whitespace-nowrap">{{ $comment->created_at?->format('d M Y H:i') }}</td>
                        <td class="px-5 py-4">
                            @if ($comment->approved)
                                <span class="rounded border border-[#1e4d2b] bg-[rgba(16,64,30,.2)] px-2 py-1 text-xs text-[#b8e6c3]">Aprobado</span>
                            @else
                                <span class="rounded border border-[#5c4d1e] bg-[rgba(64,56,16,.2)] px-2 py-1 text-xs text-[#e6d8a8]">Pendiente</span>
                            @endif
                        </td>
                        <td class="px-5 py-4">
                            <div class="flex flex-wrap gap-2">
                                @if (!$comment->approved)
                                    <form action="{{ route('admin.comments.approve', $comment) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="lucille-button" style="color:#b8e6c3">Aprobar</button>
                                    </form>
                                @else
                                    <form action="{{ route('admin.comments.unapprove', $comment) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="lucille-button" style="color:#e6d8a8">Desaprobar</button>
                                    </form>
                                @endif
                                <a href="{{ route('admin.comments.edit', $comment) }}" class="lucille-button">Editar</a>
                                <form
                                    action="{{ route('admin.comments.destroy', $comment) }}"
                                    method="POST"
                                    data-confirm="¿Eliminar este comentario de {{ $comment->author_name ?: 'Anónimo' }}?"
                                    data-confirm-title="Eliminar comentario"
                                    data-confirm-action="Eliminar"
                                    data-confirm-tone="danger"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="lucille-button-solid">Eliminar</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-10 text-center text-[#7b7b7b]">No hay comentarios todavía.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.admin>
