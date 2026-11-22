<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { COLORS, HIGHLIGHTS, SIZES } from '../Composables/richText';
import { t } from '../Composables/useLang';

/**
 * Lišta formátovania pre vizuálne editory: tučné, farba písma, pozadie,
 * zarovnanie, veľkosť. Samotný príkaz vykoná rodič cez `run`, lebo
 * textový editor beží v stránke a layout v iframe.
 */
const props = defineProps({
    run: { type: Function, required: true },
    // Layout: farba pozadia celého bloku (bunky tabuľky), nielen zvýraznenie textu.
    blockBackground: { type: Function, default: null },
});

const open = ref(null);
const root = ref(null);

const toggle = (name) => { open.value = open.value === name ? null : name; };

const pick = (name, color) => {
    if (name === 'color') props.run('foreColor', color);
    if (name === 'highlight') props.run('hiliteColor', color);
    if (name === 'block') props.blockBackground?.(color);
    open.value = null;
};

const marks = [
    { label: 'B', title: 'bold', command: 'bold', class: 'font-bold' },
    { label: 'I', title: 'italic', command: 'italic', class: 'italic' },
    { label: 'U', title: 'underline', command: 'underline', class: 'underline' },
];

const aligns = [
    { title: 'align_left', command: 'justifyLeft', icon: 'M3 6h18M3 12h12M3 18h16' },
    { title: 'align_center', command: 'justifyCenter', icon: 'M3 6h18M6 12h12M4 18h16' },
    { title: 'align_right', command: 'justifyRight', icon: 'M3 6h18M9 12h12M5 18h16' },
];

const palettes = [
    { name: 'color', colors: COLORS },
    { name: 'highlight', colors: HIGHLIGHTS },
];

const onOutside = (event) => {
    if (open.value && !root.value?.contains(event.target)) open.value = null;
};
onMounted(() => document.addEventListener('mousedown', onOutside));
onBeforeUnmount(() => document.removeEventListener('mousedown', onOutside));

const btn = 'rounded px-2 py-1 text-xs text-slate-700 transition hover:bg-slate-200';
</script>

<template>
    <!-- mousedown.prevent: klik na lištu nesmie zobrať výber textu v editore. -->
    <div ref="root" class="flex flex-wrap items-center gap-1" @mousedown.prevent>
        <button
            v-for="m in marks"
            :key="m.command"
            type="button"
            :class="[btn, m.class]"
            :title="t(`mailing.editor.${m.title}`)"
            :aria-label="t(`mailing.editor.${m.title}`)"
            @click="run(m.command)"
        >{{ m.label }}</button>

        <span class="mx-1 h-4 w-px bg-slate-300"></span>

        <div v-for="p in [...palettes, ...(blockBackground ? [{ name: 'block', colors: [...HIGHLIGHTS, ...COLORS] }] : [])]" :key="p.name" class="relative">
            <button
                type="button"
                :class="[btn, open === p.name ? 'bg-slate-200' : '']"
                :title="t(`mailing.editor.${p.name}`)"
                :aria-expanded="open === p.name"
                @click="toggle(p.name)"
            >
                <span v-if="p.name === 'color'" class="font-bold underline decoration-rose-600 decoration-2 underline-offset-2">A</span>
                <span v-else-if="p.name === 'highlight'" class="rounded bg-yellow-200 px-1 font-bold">A</span>
                <span v-else class="inline-block h-3 w-4 rounded-sm border border-slate-400 bg-gradient-to-br from-sky-200 to-emerald-200 align-middle"></span>
                <span class="ml-1">{{ t(`mailing.editor.${p.name}`) }}</span>
            </button>
            <div v-if="open === p.name" class="absolute left-0 top-full z-20 mt-1 w-48 rounded-lg border border-slate-200 bg-white p-2 shadow-lg">
                <div class="grid grid-cols-6 gap-1">
                    <button
                        v-for="c in p.colors"
                        :key="c"
                        type="button"
                        class="h-6 w-6 rounded border border-slate-300 transition hover:scale-110"
                        :style="{ background: c }"
                        :title="c"
                        :aria-label="c"
                        @click="pick(p.name, c)"
                    ></button>
                </div>
                <label class="mt-2 flex items-center gap-2 text-xs text-slate-600">
                    <input type="color" class="h-6 w-8 cursor-pointer rounded border-0 p-0" @mousedown.stop @change="pick(p.name, $event.target.value)" />
                    {{ t('mailing.editor.custom_color') }}
                </label>
                <button v-if="p.name !== 'color'" type="button" class="mt-1 text-xs text-slate-500 hover:text-slate-900" @click="pick(p.name, p.name === 'block' ? '' : 'transparent')">
                    {{ t('mailing.editor.no_color') }}
                </button>
            </div>
        </div>

        <span class="mx-1 h-4 w-px bg-slate-300"></span>

        <button
            v-for="a in aligns"
            :key="a.command"
            type="button"
            :class="btn"
            :title="t(`mailing.editor.${a.title}`)"
            :aria-label="t(`mailing.editor.${a.title}`)"
            @click="run(a.command)"
        >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="inline-block"><path :d="a.icon" /></svg>
        </button>

        <select
            class="!w-auto rounded border-slate-200 py-0.5 pl-2 pr-7 text-xs"
            :aria-label="t('mailing.editor.size')"
            :title="t('mailing.editor.size')"
            @mousedown.stop
            @change="run('fontSize', $event.target.value); $event.target.selectedIndex = 0"
        >
            <option value="" disabled selected>{{ t('mailing.editor.size') }}</option>
            <option v-for="s in SIZES" :key="s.key" :value="s.value">{{ t(`mailing.editor.size_${s.key}`) }}</option>
        </select>

        <slot />
    </div>
</template>
