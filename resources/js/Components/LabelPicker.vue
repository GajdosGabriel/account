<script setup>
import { computed, ref } from 'vue';
import { t } from '../Composables/useLang';
import { labelClass } from '../Composables/useLabels';

/**
 * Výber štítkov: vybrané ako čipy, pod poľom našepkávač existujúcich
 * štítkov a možnosť vytvoriť nový (Enter, čiarka). Hodnota je zoznam
 * názvov – server ich spáruje s existujúcimi štítkami alebo vytvorí nové.
 */
const model = defineModel({ type: Array, default: () => [] });

const props = defineProps({
    /** Existujúce štítky: [{ name, color }] */
    options: { type: Array, default: () => [] },
    /** Povoliť nový štítok, ktorý ešte neexistuje. */
    creatable: { type: Boolean, default: true },
    placeholder: { type: String, default: null },
    id: { type: String, default: null },
});

const query = ref('');
const open = ref(false);
const active = ref(0);

const norm = (s) => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
const selected = computed(() => new Set(model.value.map(norm)));

const suggestions = computed(() => {
    const q = norm(query.value);
    const list = props.options.filter((o) => !selected.value.has(norm(o.name)) && (!q || norm(o.name).includes(q))).slice(0, 8);
    const exact = props.options.some((o) => norm(o.name) === q) || selected.value.has(q);

    return props.creatable && q && !exact ? [...list, { name: query.value.trim(), color: null, isNew: true }] : list;
});

const colorOf = (name) => props.options.find((o) => norm(o.name) === norm(name))?.color ?? 'indigo';

const add = (name) => {
    const value = (name ?? '').trim();
    if (!value) return;
    const existing = props.options.find((o) => norm(o.name) === norm(value));
    if (!existing && !props.creatable) return;
    if (!selected.value.has(norm(value))) model.value = [...model.value, existing?.name ?? value];
    query.value = '';
    active.value = 0;
};

const remove = (name) => { model.value = model.value.filter((n) => n !== name); };

const onKey = (e) => {
    if (e.key === 'Enter' || e.key === ',' || e.key === ';') {
        if (!query.value.trim()) {
            if (e.key === 'Enter') return; // prázdne pole – nech sa odošle formulár
            e.preventDefault();
            return;
        }
        e.preventDefault();
        add(suggestions.value[active.value]?.name ?? query.value);
    } else if (e.key === 'Backspace' && !query.value && model.value.length) {
        model.value = model.value.slice(0, -1);
    } else if (e.key === 'ArrowDown') {
        e.preventDefault();
        open.value = true;
        active.value = Math.min(active.value + 1, suggestions.value.length - 1);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        active.value = Math.max(active.value - 1, 0);
    } else if (e.key === 'Escape') {
        open.value = false;
    }
};

// Vložený text „a, b, c“ sa rozdelí na štítky.
const onPaste = (e) => {
    const text = e.clipboardData?.getData('text') ?? '';
    if (!/[,;\n]/.test(text)) return;
    e.preventDefault();
    text.split(/[,;\n]+/).forEach(add);
};

const blur = () => setTimeout(() => {
    open.value = false;
    if (query.value.trim() && props.creatable) add(query.value);
}, 150);
</script>

<template>
    <div class="relative">
        <div class="flex min-h-[2.5rem] flex-wrap items-center gap-1 rounded-md border border-slate-300 bg-white px-2 py-1 focus-within:border-brand-500 focus-within:ring-1 focus-within:ring-brand-500">
            <span v-for="name in model" :key="name" class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-xs" :class="labelClass(colorOf(name))">
                {{ name }}
                <button type="button" class="opacity-60 hover:opacity-100" :aria-label="`${t('mailing.labels.delete')} ${name}`" @click="remove(name)">×</button>
            </span>
            <input
                :id="id"
                v-model="query"
                type="text"
                class="!m-0 min-w-[8rem] flex-1 !border-0 !p-1 !shadow-none !ring-0 text-sm"
                :placeholder="model.length ? '' : (placeholder ?? t('mailing.labels.placeholder'))"
                autocomplete="off"
                role="combobox"
                :aria-expanded="open && suggestions.length > 0"
                @focus="open = true"
                @input="open = true; active = 0"
                @keydown="onKey"
                @paste="onPaste"
                @blur="blur"
            />
        </div>
        <ul v-if="open && suggestions.length" class="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-md border border-slate-200 bg-white py-1 text-sm shadow-lg" role="listbox">
            <li
                v-for="(s, i) in suggestions"
                :key="(s.isNew ? 'new-' : '') + s.name"
                role="option"
                :aria-selected="i === active"
                class="flex cursor-pointer items-center gap-2 px-3 py-1.5"
                :class="i === active ? 'bg-slate-100' : ''"
                @mousedown.prevent="add(s.name)"
                @mouseenter="active = i"
            >
                <template v-if="s.isNew"><span class="text-brand-700">+ {{ t('mailing.labels.new_option', { name: s.name }) }}</span></template>
                <template v-else><span class="h-2 w-2 rounded-full" :class="labelClass(s.color, 'dot')"></span>{{ s.name }}</template>
            </li>
        </ul>
    </div>
</template>
