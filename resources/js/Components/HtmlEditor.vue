<script setup>
import { onMounted, ref, watch } from 'vue';
import { t } from '../Composables/useLang';

/**
 * Jednoduchý HTML editor bez závislostí (prevzatý z emailingu zastáv).
 *
 * Výstup prejde bielou listinou značiek – rovnakou, akú na serveri drží
 * App\Services\Mailing\HtmlSanitizer. Starší čistý text (odseky oddelené
 * prázdnym riadkom) sa pri načítaní prevedie na odseky.
 */
const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    minHeight: { type: String, default: '12rem' },
    id: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const ALLOWED_TAGS = ['P', 'BR', 'STRONG', 'B', 'EM', 'I', 'U', 'H2', 'H3', 'UL', 'OL', 'LI', 'A'];
const ALLOWED_HREF = /^(https?:\/\/|mailto:|\/)/i;

const sanitize = (html) => {
    if (!html) return '';

    const template = document.createElement('template');
    template.innerHTML = String(html);

    const walk = (node) => {
        [...node.children].forEach((child) => {
            walk(child);

            if (!ALLOWED_TAGS.includes(child.tagName)) {
                // Editor nový riadok niekedy obalí do <div>; bez zalomenia by sa zlial s predošlým.
                if (child.tagName === 'DIV' && child.previousSibling && child.previousSibling.nodeName !== 'BR') {
                    child.before(document.createElement('br'));
                }
                child.replaceWith(...child.childNodes);
                return;
            }

            [...child.attributes].forEach((attribute) => {
                if (child.tagName === 'A' && attribute.name.toLowerCase() === 'href' && ALLOWED_HREF.test(attribute.value)) return;
                child.removeAttribute(attribute.name);
            });
        });
    };

    walk(template.content);

    return template.innerHTML.trim();
};

const escape = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

// Starší text bez značiek: odseky oddelené prázdnym riadkom.
const toHtml = (value) => (/<(p|br|h2|h3|ul|ol|li|strong|b|em|i|u|a)\b/i.test(value ?? '')
    ? value
    : (value ?? '').split(/\r?\n\s*\r?\n/).filter((p) => p.trim())
        .map((p) => `<p>${escape(p.trim()).replace(/\r?\n/g, '<br>')}</p>`).join(''));

const editor = ref(null);
const isEmpty = ref(true);

// execCommand je síce označený za zastaraný, ale ako jediný funguje
// vo všetkých prehliadačoch bez ďalšej závislosti.
const exec = (command, value = null) => {
    editor.value?.focus();
    document.execCommand(command, false, value);
    onInput();
};

const tools = [
    { label: 'B', title: 'bold', command: 'bold', class: 'font-bold' },
    { label: 'I', title: 'italic', command: 'italic', class: 'italic' },
    { label: 'U', title: 'underline', command: 'underline', class: 'underline' },
    { label: 'H2', title: 'heading', command: 'formatBlock', value: '<h2>' },
    { label: 'H3', title: 'subheading', command: 'formatBlock', value: '<h3>' },
    { label: '¶', title: 'paragraph', command: 'formatBlock', value: '<p>' },
    { label: '•', title: 'bullets', command: 'insertUnorderedList' },
    { label: '1.', title: 'numbered', command: 'insertOrderedList' },
];

const onLink = () => {
    const url = window.prompt(t('mailing.editor.link_prompt'));
    if (!url) return;

    // javascript: v odkaze by bol XSS – povolíme len bežné schémy.
    if (!ALLOWED_HREF.test(url)) {
        window.alert(t('mailing.editor.link_invalid'));
        return;
    }

    exec('createLink', url);
};

const onClearFormat = () => {
    exec('removeFormat');
    exec('unlink');
};

const onInput = () => {
    const html = sanitize(editor.value?.innerHTML ?? '');
    isEmpty.value = html.replace(/<[^>]*>/g, '').trim() === '';
    emit('update:modelValue', isEmpty.value ? '' : html);
};

// Vložený text zbavíme formátovania zdroja (Word, weby) – inak sa do e-mailu
// dostanú cudzie štýly a triedy.
const onPaste = (event) => {
    event.preventDefault();
    document.execCommand('insertText', false, event.clipboardData?.getData('text/plain') ?? '');
    onInput();
};

const render = (value) => {
    if (!editor.value) return;
    const clean = sanitize(toHtml(value));
    if (editor.value.innerHTML !== clean) editor.value.innerHTML = clean;
    isEmpty.value = clean.replace(/<[^>]*>/g, '').trim() === '';
};

onMounted(() => render(props.modelValue));

// Pri písaní sa hodnota zhoduje, takže sa innerHTML neprepisuje a kurzor
// neuteká na začiatok. Zvonka (iná šablóna) sa obsah prekreslí.
watch(() => props.modelValue, (value) => {
    if (document.activeElement === editor.value) return;
    render(value);
});
</script>

<template>
    <div class="overflow-hidden rounded-lg border border-slate-300 bg-white focus-within:border-brand-500 focus-within:ring-1 focus-within:ring-brand-500">
        <div class="flex flex-wrap items-center gap-1 border-b border-slate-200 bg-slate-50 px-2 py-1.5">
            <button
                v-for="tool in tools"
                :key="tool.label"
                type="button"
                :title="t(`mailing.editor.${tool.title}`)"
                :aria-label="t(`mailing.editor.${tool.title}`)"
                class="rounded px-2 py-1 text-xs text-slate-700 transition hover:bg-slate-200"
                :class="tool.class"
                @click="exec(tool.command, tool.value ?? null)"
            >{{ tool.label }}</button>
            <button type="button" class="rounded px-2 py-1 text-xs text-slate-700 transition hover:bg-slate-200" @click="onLink">
                🔗 {{ t('mailing.editor.link') }}
            </button>
            <button type="button" class="rounded px-2 py-1 text-xs text-slate-500 transition hover:bg-slate-200" @click="onClearFormat">
                {{ t('mailing.editor.clear') }}
            </button>
        </div>

        <div class="relative">
            <p v-if="isEmpty && placeholder" class="pointer-events-none absolute left-3 top-2 text-sm text-slate-400">{{ placeholder }}</p>
            <div
                :id="id"
                ref="editor"
                contenteditable="true"
                role="textbox"
                aria-multiline="true"
                :style="{ minHeight }"
                class="html-editor w-full px-3 py-2 text-sm leading-6 focus:outline-none"
                @input="onInput"
                @blur="onInput"
                @paste="onPaste"
            ></div>
        </div>
    </div>
</template>

<style scoped>
/* Tailwind resetuje nadpisy a zoznamy – v editore ich potrebujeme vidieť. */
.html-editor :deep(h2) { font-size: 1.125rem; font-weight: 600; margin: 0.75rem 0 0.35rem; }
.html-editor :deep(h3) { font-size: 1rem; font-weight: 600; margin: 0.6rem 0 0.3rem; }
.html-editor :deep(p) { margin: 0.35rem 0; }
.html-editor :deep(ul) { list-style: disc; padding-left: 1.4rem; margin: 0.35rem 0; }
.html-editor :deep(ol) { list-style: decimal; padding-left: 1.4rem; margin: 0.35rem 0; }
.html-editor :deep(a) { color: #4f46e5; text-decoration: underline; }
</style>
