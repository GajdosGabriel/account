<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import FormatToolbar from './FormatToolbar.vue';
import { rgbToHex } from '../Composables/richText';
import { t } from '../Composables/useLang';

/**
 * Vizuálna úprava celého HTML layoutu: e-mail sa vykreslí v iframe
 * v režime úprav (designMode) a dá sa v ňom písať, meniť farby písma,
 * pozadie textu aj pozadie blokov (buniek tabuľky).
 *
 * Značky {{premenná}} ostanú ako text. Sekcie {{#x}}…{{/x}} často stoja
 * medzi riadkami tabuľky, kam prehliadač text nepustí a presunul by ho –
 * preto ich počas úprav schováme do HTML komentárov a pri uložení vrátime.
 */
const props = defineProps({
    modelValue: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const frame = ref(null);
const srcdoc = ref('');
let lastEmitted = null;
let observer = null;

const SECTION = /\{\{\s*([#^/])\s*([a-z0-9_]+)\s*\}\}/gi;
const protect = (html) => html.replace(SECTION, (_, kind, name) => `<!--mst:${kind}${name}-->`);
const unprotect = (html) => html.replace(/<!--mst:([#^/])([a-z0-9_]+)-->/gi, '{{$1$2}}');

const doc = () => frame.value?.contentDocument;

const load = (html) => {
    srcdoc.value = protect(html || '');
};

const serialize = () => {
    const d = doc();
    if (!d?.documentElement) return props.modelValue;
    const doctype = /^\s*<!doctype/i.test(props.modelValue) ? '<!doctype html>\n' : '';
    return rgbToHex(unprotect(doctype + d.documentElement.outerHTML));
};

const sync = () => {
    lastEmitted = serialize();
    emit('update:modelValue', lastEmitted);
    fit();
};

const fit = () => {
    const d = doc();
    if (d?.documentElement) frame.value.style.height = `${Math.max(320, d.documentElement.scrollHeight)}px`;
};

const onLoad = () => {
    const d = doc();
    if (!d) return;
    d.designMode = 'on';
    d.addEventListener('input', sync);
    fit();
    observer?.disconnect();
    if (d.body && typeof ResizeObserver !== 'undefined') {
        observer = new ResizeObserver(fit);
        observer.observe(d.body);
    }
};

const run = (command, value = null) => {
    const d = doc();
    if (!d) return;
    frame.value.contentWindow?.focus();
    d.execCommand('styleWithCSS', false, true);
    d.execCommand(command, false, value);
    sync();
};

// Pozadie celého bloku: najbližšia bunka tabuľky (alebo telo) okolo kurzora.
const blockBackground = (color) => {
    const d = doc();
    const node = d?.getSelection()?.anchorNode;
    const element = node?.nodeType === 1 ? node : node?.parentElement;
    const block = element?.closest('td, th') ?? d?.body;
    if (!block) return;

    block.style.backgroundColor = color;
    if (block.hasAttribute('bgcolor')) {
        color ? block.setAttribute('bgcolor', color) : block.removeAttribute('bgcolor');
    }
    sync();
};

watch(() => props.modelValue, (value) => {
    if (value !== lastEmitted) load(value);
}, { immediate: true });

onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <div class="rounded-lg border border-slate-300 bg-white">
        <div class="rounded-t-lg border-b border-slate-200 bg-slate-50 px-2 py-1.5">
            <FormatToolbar :run="run" :block-background="blockBackground" />
        </div>
        <p class="border-b border-slate-100 px-3 py-1.5 text-xs text-slate-500">{{ t('mailing.editor.layout_hint') }}</p>
        <iframe
            ref="frame"
            :srcdoc="srcdoc"
            sandbox="allow-same-origin"
            class="block h-80 w-full rounded-b-lg bg-white"
            :title="t('mailing.editor.view_visual')"
            @load="onLoad"
        ></iframe>
    </div>
</template>
