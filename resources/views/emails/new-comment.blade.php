<x-mail::message>
# Nuevo comentario en el blog

**{{ $comment->author_name ?: "Anónimo" }}** ha escrito un comentario en el post **"{{ $comment->post?->title ?: "—" }}"**.

---

> {{ $comment->content }}

---

<x-mail::button :url="route('admin.comments.index')">
Ir al panel de comentarios
</x-mail::button>

<x-mail::button :url="route('admin.comments.edit', $comment)" color="success">
Revisar / Aprobar comentario
</x-mail::button>

<x-mail::subcopy>
Recibes este correo porque tienes acceso de administrador en Seven Rock Radio.
</x-mail::subcopy>
</x-mail::message>
