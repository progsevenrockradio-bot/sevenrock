@props([
    'name',
    'label',
    'value' => '',
    'rows' => 14,
    'help' => null,
    'placeholder' => null,
])

@php
    $inputId = 'json-editor-'.str_replace(['[', ']', '.', ' '], ['-', '-', '-', '-'], $name);
@endphp

<div
    x-data="{
        content: @js(old($name, $value)),
        error: null,
        scrollTop: 0,
        scrollLeft: 0,
        highlighted: '',
        init() {
            this.updatePreview();
        },
        updatePreview() {
            this.validate();
            this.highlighted = this.highlight(this.content ?? '');
        },
        validate() {
            const raw = (this.content ?? '').trim();
            if (raw === '') {
                this.error = null;
                return;
            }

            try {
                JSON.parse(raw);
                this.error = null;
            } catch (exception) {
                this.error = 'JSON inválido: revisa comas, llaves y comillas.';
            }
        },
        format() {
            try {
                const parsed = JSON.parse(this.content || '{}');
                this.content = JSON.stringify(parsed, null, 2);
                this.updatePreview();
            } catch (exception) {
                this.error = 'JSON inválido: no se puede formatear.';
            }
        },
        escapeHtml(value) {
            return (value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        },
        highlight(value) {
            const pattern = /(\"([^\"\\\\]|\\\\.)*\"|\b-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?\b|\btrue\b|\bfalse\b|\bnull\b|[{}\[\],:])/g;
            let lastIndex = 0;
            let html = '';

            value.replace(pattern, (match, _group, _inner, offset) => {
                html += this.escapeHtml(value.slice(lastIndex, offset));
                if (/^\"/.test(match)) {
                    html += `<span class="text-[#89ddff]">${this.escapeHtml(match)}</span>`;
                } else if (/^(true|false)$/.test(match)) {
                    html += `<span class="text-[#f78c6c]">${this.escapeHtml(match)}</span>`;
                } else if (/^null$/.test(match)) {
                    html += `<span class="text-[#ffcb6b]">${this.escapeHtml(match)}</span>`;
                } else if (/^-?\d/.test(match)) {
                    html += `<span class="text-[#f78c6c]">${this.escapeHtml(match)}</span>`;
                } else {
                    html += `<span class="text-[#7fdbca]">${this.escapeHtml(match)}</span>`;
                }

                lastIndex = offset + match.length;
                return match;
            });

            html += this.escapeHtml(value.slice(lastIndex));

            return html || '<span class="text-[#6b7280]">{}</span>';
        },
        syncScroll() {
            this.scrollTop = this.$refs.input.scrollTop;
            this.scrollLeft = this.$refs.input.scrollLeft;
        },
    }"
    x-init="init()"
    class="space-y-3"
>
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]" for="{{ $inputId }}">{{ $label }}</label>
        @if ($help)
            <p class="mb-2 text-xs leading-5 text-[#7b7b7b]">{{ $help }}</p>
        @endif
    </div>

    <div class="relative overflow-hidden border bg-[#0b0b0c]" :class="error ? 'border-[#7a2b2b]' : 'border-[#2b2b2b]'">
        <pre
            aria-hidden="true"
            class="pointer-events-none absolute inset-0 overflow-hidden whitespace-pre font-mono text-[12px] leading-6 text-[#d8d8d8]"
        >
            <code
                x-html="highlighted"
                :style="`transform: translate(${-scrollLeft}px, ${-scrollTop}px); display: block; padding: 1rem;`"
                class="block min-h-full"
            ></code>
        </pre>

        <textarea
            id="{{ $inputId }}"
            name="{{ $name }}"
            x-ref="input"
            x-model="content"
            @input="updatePreview()"
            @scroll="syncScroll()"
            rows="{{ $rows }}"
            wrap="off"
            spellcheck="false"
            autocapitalize="off"
            autocomplete="off"
            autocorrect="off"
            placeholder="{{ $placeholder ?? 'Pega o escribe JSON válido' }}"
            class="relative z-10 block w-full resize-y border-0 bg-transparent p-4 font-mono text-[12px] leading-6 text-transparent caret-[#f0f0f0] outline-none placeholder:text-[#555] focus:ring-0"
        ></textarea>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 text-xs">
        <p :class="error ? 'text-[#ff9e9e]' : 'text-[#b8e6c3]'" x-text="error ?? 'JSON válido'"></p>
        <button type="button" class="lucille-button" @click="format()">Formatear JSON</button>
    </div>

    @error($name)
        <p class="text-xs text-[#ff9e9e]">{{ $message }}</p>
    @enderror
</div>
