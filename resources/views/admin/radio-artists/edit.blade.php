<x-layouts.admin :title="'Editar perfil: ' . $bandProfile->name">
    <div class="mx-auto max-w-5xl space-y-5 font-sans">
        <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-white/10 pb-4">
            <div>
                <div class="text-[11px] font-semibold uppercase tracking-wider text-[#c32720]">Bandas y comunidad · Catálogo editorial del reproductor</div>
                <h1 class="mt-0.5 font-display text-2xl uppercase tracking-[.08em] text-white">Editar perfil: {{ $bandProfile->name }}</h1>
                <p class="mt-1 text-xs text-gray-400">Actualiza la biografía completa, el resumen editorial y los metadatos utilizados por el reproductor.</p>
            </div>
            <a href="{{ route('admin.radio-artists.index') }}" class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                <span>Volver a perfiles</span>
            </a>
        </header>

        <form action="{{ route('admin.radio-artists.update', $bandProfile) }}" method="POST" class="space-y-5">
            @csrf
            @method('PUT')
            @include('admin.radio-artists._form')
        </form>
    </div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const btn = document.getElementById('autoGenerateBtn');
    if (!btn) return;

    btn.addEventListener('click', function(e) {
        e.preventDefault();

        const artistName = document.querySelector('[name="name"]')?.value || '';
        if (!artistName.trim()) {
            alert('Primero escribe o guarda el nombre del artista.');
            return;
        }

        btn.disabled = true;
        btn.textContent = 'Generando desde fuentes...';
        btn.style.opacity = '0.6';

        const url = btn.getAttribute('data-url');

        fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.data) {
                const d = data.data;
                if (d.summary) {
                    const summaryField = document.querySelector('[name="editorial_summary"]');
                    if (summaryField) summaryField.value = d.summary;
                    const bioField = document.querySelector('[name="biography"]');
                    if (bioField) bioField.value = d.summary;
                }
                if (d.country) {
                    const f = document.querySelector('[name="country"]');
                    if (f) f.value = d.country;
                }
                if (d.genre) {
                    const f = document.querySelector('[name="genre"]');
                    if (f) f.value = d.genre;
                }
                if (d.members_count) {
                    const f = document.querySelector('[name="members_count"]');
                    if (f) f.value = d.members_count;
                }
                if (d.status) {
                    const f = document.querySelector('[name="status"]');
                    if (f) f.value = d.status;
                }
                if (d.facts && d.facts.length) {
                    const f = document.querySelector('[name="featured_facts_text"]');
                    if (f) f.value = d.facts.join('\n');
                }
                if (d.labels) {
                    const f = document.querySelector('[name="labels"]');
                    if (f) f.value = d.labels;
                }
                if (d.formed_label) {
                    const f = document.querySelector('[name="founded_date"]');
                    if (f && d.formed_year) {
                        f.value = d.formed_year + '-01-01';
                    }
                }
                alert('Información generada correctamente. Revisa los campos y guarda los cambios.');
            } else {
                alert(data.message || 'No se encontró información en las fuentes consultadas.');
            }
        })
        .catch(err => {
            alert('Error: ' + err.message);
        })
        .finally(() => {
            btn.disabled = false;
            btn.textContent = 'Autocompletar desde fuentes externas';
            btn.style.opacity = '1';
        });
    });
});
</script>
@endpush
</x-layouts.admin>
