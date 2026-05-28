<x-layouts.admin :title="'Edit Radio Artist - '.$themeSettings->site_name">
    <div class="mb-6">
        <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Edit Radio Artist</h1>
        <p class="mt-2 text-[#7b7b7b]">Update the local profile used before external lookup.</p>
    </div>

    <form action="{{ route('admin.radio-artists.update', $bandProfile) }}" method="POST" class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-6">
        @csrf
        @method('PUT')
        @include('admin.radio-artists._form')
    </form>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const btn = document.getElementById('autoGenerateBtn');
    if (!btn) return;

    btn.addEventListener('click', function(e) {
        e.preventDefault();

        const artistName = document.querySelector('[name="name"]')?.value || '';
        if (!artistName.trim()) {
            alert('Primero guarda el nombre del artista.');
            return;
        }

        btn.disabled = true;
        btn.textContent = '✍️ Generando...';
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
                    // Try to fill founded_date from formed_year
                    const f = document.querySelector('[name="founded_date"]');
                    if (f && d.formed_year) {
                        f.value = d.formed_year + '-01-01';
                    }
                }
                alert('✅ Información generada de ' + Object.keys(d).length + ' campos. Revisa y guarda.');
            } else {
                alert('❌ ' + (data.message || 'No se encontró información'));
            }
        })
        .catch(err => {
            alert('❌ Error: ' + err.message);
        })
        .finally(() => {
            btn.disabled = false;
            btn.textContent = '✨ Auto-generar desde fuentes';
            btn.style.opacity = '1';
        });
    });
});
</script>
@endpush
</x-layouts.admin>
