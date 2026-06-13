<x-layouts.admin :title="'Plantillas de Correo - '.(optional($themeSettings)->site_name ?? config('app.name'))">
    <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Plantillas de Correo</h1>
                <p class="mt-2 text-sm text-[#7b7b7b]">Edita el contenido y diseño de los correos automáticos del sistema.</p>
            </div>
        </div>

        @if (session('status'))
            <div class="mt-6 border border-[#2b2b2b] bg-[#1a2f1c] p-4 text-sm text-[#a8e6b1]">
                {{ session('status') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="mt-6 border border-[#7a2b2b] bg-[rgba(195,39,32,.1)] p-4 text-sm text-[#ff9e9e]">
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="mt-8 border border-[#242424] bg-[#111] p-6">
            <h2 class="font-display text-lg uppercase tracking-[.12em] text-[#dcdcdc] mb-4">Prueba de Servidor SMTP</h2>
            <form action="{{ route('admin.email-templates.test') }}" method="POST" class="flex flex-wrap items-end gap-4">
                @csrf
                <div class="flex-1 min-w-[300px]">
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Correo destino de prueba</label>
                    <input type="email" name="test_email" required class="lucille-product-field w-full" placeholder="tu@correo.com">
                </div>
                <button type="submit" class="lucille-button-solid">Enviar Correo de Prueba</button>
            </form>
            <p class="mt-3 text-xs text-[#555]">Esto enviará un correo simple para verificar que las credenciales SMTP funcionan correctamente.</p>
        </div>

        <div class="mt-8 overflow-x-auto border border-[#242424]">
            <table class="min-w-full divide-y divide-[#242424] text-left text-sm">
                <thead class="bg-[#131313] text-[#7b7b7b]">
                    <tr>
                        <th class="px-4 py-3 font-display uppercase tracking-[.18em]">Nombre de la Plantilla</th>
                        <th class="px-4 py-3 font-display uppercase tracking-[.18em]">Ruta</th>
                        <th class="px-4 py-3 font-display uppercase tracking-[.18em]">Tamaño</th>
                        <th class="px-4 py-3 font-display uppercase tracking-[.18em]">Última Modificación</th>
                        <th class="px-4 py-3 font-display uppercase tracking-[.18em] text-right">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#242424]">
                    @foreach ($templates as $template)
                        <tr class="align-middle hover:bg-[#151515] transition-colors">
                            <td class="px-4 py-4 font-display uppercase tracking-[.08em] text-[#dcdcdc]">
                                {{ str_replace('/', ' / ', $template['name']) }}
                            </td>
                            <td class="px-4 py-4 text-[#7b7b7b] font-mono text-xs">
                                {{ $template['path'] }}
                            </td>
                            <td class="px-4 py-4 text-[#7b7b7b] text-xs">
                                {{ round($template['size'] / 1024, 2) }} KB
                            </td>
                            <td class="px-4 py-4 text-[#7b7b7b] text-xs">
                                {{ $template['last_modified']->diffForHumans() }}
                            </td>
                            <td class="px-4 py-4 text-right">
                                <a href="{{ route('admin.email-templates.edit', $template['encoded_path']) }}" class="lucille-button">
                                    Editar código
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.admin>
