<x-layouts.admin title="Invitaciones de productores">
    <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Invitaciones</h1>
                <p class="mt-3 max-w-3xl text-sm text-[#7b7b7b]">Panel rápido para enviar invitaciones a productores por programa.</p>
            </div>
            <a href="{{ route('admin.programs.index') }}" class="lucille-button-solid">Volver</a>
        </div>
    </div>

    <section class="mt-8 grid gap-6 lg:grid-cols-2">
        @foreach ($programs as $program)
            <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $program->program_code ?: 'Sin código' }}</div>
                        <h2 class="mt-2 font-display text-2xl uppercase tracking-[.08em] text-[#dcdcdc]">{{ $program->name }}</h2>
                        <p class="mt-2 text-sm text-[#9f9f9f]">{{ $program->conductor }}</p>
                    </div>
                    <form action="{{ route('admin.programs.generate-code', $program) }}" method="POST">
                        @csrf
                        <button type="submit" class="lucille-button">Regenerar</button>
                    </form>
                </div>

                <form action="{{ route('admin.programs.send-invitation', $program) }}" method="POST" class="mt-5 flex flex-wrap gap-3">
                    @csrf
                    <select name="template_id" class="lucille-product-field min-w-[240px] flex-1">
                        @foreach ($templates as $template)
                            <option value="{{ $template->id }}">{{ $template->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="lucille-button-solid">Enviar invitación</button>
                </form>
            </div>
        @endforeach
    </section>
</x-layouts.admin>
