<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';

const props = defineProps({
    html: { type: String, required: true },
});

const frame = ref(null);
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

const onLoad = () => {
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
    <iframe
        ref="frame"
        :srcdoc="doc"
        sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox"
        referrerpolicy="no-referrer"
        class="block h-64 w-full rounded-lg border border-slate-200 bg-white"
        title="E-mail"
        @load="onLoad"
    ></iframe>
</template>
