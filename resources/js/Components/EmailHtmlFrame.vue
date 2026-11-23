<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import { t } from '../Composables/useLang';

const props = defineProps({
    html: { type: String, required: true },
    // V konverzácii skryje citovanú predchádzajúcu správu (ako Gmail „···“) – vlákno sa inak opakuje v každej odpovedi.
    collapseQuotes: { type: Boolean, default: false },
});

defineOptions({ inheritAttrs: false });

const QUOTE = '.gmail_quote, blockquote[type="cite"], .moz-cite-prefix, #divRplyFwdMsg';

const frame = ref(null);
const hasQuote = ref(false);
const quoteOpen = ref(false);
let observer = null;

// Odkazy z e-mailu otvárame v novej karte – iframe je bez skriptov (sandbox bez allow-scripts).
const doc = computed(() => {
    const base = '<base target="_blank">';

    return /<head[^>]*>/i.test(props.html)
        ? props.html.replace(/<head[^>]*>/i, (head) => head + base)
        : base + props.html;
});

// Výška podľa obsahu, aby rámček nemal vlastný posuvník.
const fit = () => {
    const document = frame.value?.contentDocument;
    if (document?.documentElement) {
        frame.value.style.height = `${document.documentElement.scrollHeight}px`;
    }
};

// Len vonkajšie citáty – vnorené sa skryjú s nimi. Outlook (#divRplyFwdMsg) cituje všetko za hlavičkou.
const quotes = () => {
    const document = frame.value?.contentDocument;
    if (!document) return [];

    return [...document.querySelectorAll(QUOTE)].filter((el) => !el.parentElement?.closest(QUOTE));
};

const applyQuotes = () => {
    if (!props.collapseQuotes) return;

    const found = quotes();
    hasQuote.value = found.length > 0;
    found.forEach((el) => {
        el.style.display = quoteOpen.value ? '' : 'none';
        // Outlook: citát pokračuje súrodencami za hlavičkou.
        if (el.id === 'divRplyFwdMsg') {
            for (let next = el.nextElementSibling; next; next = next.nextElementSibling) {
                next.style.display = quoteOpen.value ? '' : 'none';
            }
        }
    });
    fit();
};

const toggleQuote = () => {
    quoteOpen.value = !quoteOpen.value;
    applyQuotes();
};

const onLoad = () => {
    quoteOpen.value = false;
    applyQuotes();
    fit();
    observer?.disconnect();
    const body = frame.value?.contentDocument?.body;
    if (body && typeof ResizeObserver !== 'undefined') {
        observer = new ResizeObserver(fit);
        observer.observe(body);
    }
};

onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <div v-bind="$attrs">
        <iframe
            ref="frame"
            :srcdoc="doc"
            sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox"
            referrerpolicy="no-referrer"
            class="block h-64 w-full rounded-lg border border-slate-200 bg-white"
            title="E-mail"
            @load="onLoad"
        ></iframe>
        <button
            v-if="hasQuote"
            type="button"
            class="mt-2 rounded-md bg-slate-100 px-2 py-0.5 text-xs text-slate-600 hover:bg-slate-200 hover:text-slate-900"
            :aria-expanded="quoteOpen"
            @click="toggleQuote"
        >
            {{ quoteOpen ? t('messaging.inbox.hide_quote') : t('messaging.inbox.show_quote') }}
        </button>
    </div>
</template>
