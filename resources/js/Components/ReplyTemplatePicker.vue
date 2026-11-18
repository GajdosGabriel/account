<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import Icon from './Icon.vue';
import { t } from '../Composables/useLang';
import { excerpt, searchTemplates } from '../Composables/useReplyTemplates';

/**
 * Výber šablóny odpovede – tlačidlo s rozbaľovacím zoznamom a hľadaním.
 * Šípky vyberajú, Enter vloží, Esc zavrie.
 */
const props = defineProps({
    templates: { type: Array, default: () => [] },
    context: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['pick', 'save']);

const open = ref(false);
const query = ref('');
const active = ref(0);
const root = ref(null);
const input = ref(null);
const list = ref(null);

const results = computed(() => searchTemplates(props.templates, query.value));

watch(query, () => {
    active.value = 0;
});

const show = async () => {
    open.value = true;
    query.value = '';
    active.value = 0;
    await nextTick();
    input.value?.focus();
};

const hide = () => {
    open.value = false;
};

const toggle = () => (open.value ? hide() : show());

const pick = (tpl) => {
    hide();
    emit('pick', tpl);
};

const move = async (step) => {
    const count = results.value.length;
    if (!count) return;
    active.value = (active.value + step + count) % count;
    await nextTick();
    list.value?.querySelector(`[data-index="${active.value}"]`)?.scrollIntoView({ block: 'nearest' });
};

const onKeydown = (event) => {
    if (event.key === 'ArrowDown') { event.preventDefault(); move(1); }
    else if (event.key === 'ArrowUp') { event.preventDefault(); move(-1); }
    else if (event.key === 'Enter') {
        event.preventDefault();
        const tpl = results.value[active.value];
        if (tpl) pick(tpl);
    } else if (event.key === 'Escape') { event.preventDefault(); hide(); }
};

const onDocumentClick = (event) => {
    if (open.value && root.value && !root.value.contains(event.target)) hide();
};

document.addEventListener('mousedown', onDocumentClick);
onBeforeUnmount(() => document.removeEventListener('mousedown', onDocumentClick));

const save = () => {
    hide();
    emit('save');
};

defineExpose({ show });
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            class="btn-secondary btn-sm"
            :aria-expanded="open"
            aria-haspopup="listbox"
            @click="toggle"
        >
            <Icon name="bolt" :size="14" />
            {{ t('messaging.templates.picker') }}
        </button>

        <div
            v-if="open"
            class="absolute right-0 z-30 mt-2 w-[min(28rem,calc(100vw-2rem))] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg"
            @keydown="onKeydown"
        >
            <div class="border-b border-slate-100 p-2">
                <div class="relative">
                    <Icon name="search" :size="15" class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400" />
                    <input
                        ref="input"
                        v-model="query"
                        type="search"
                        class="py-1.5 pl-8 text-sm"
                        :placeholder="t('messaging.templates.picker_search')"
                        :aria-label="t('messaging.templates.picker_search')"
                    />
                </div>
            </div>

            <ul v-if="results.length" ref="list" role="listbox" class="max-h-80 overflow-y-auto py-1">
                <li
                    v-for="(tpl, index) in results"
                    :key="tpl.id"
                    role="option"
                    :aria-selected="index === active"
                    :data-index="index"
                    class="cursor-pointer px-3 py-2"
                    :class="index === active ? 'bg-brand-50' : 'hover:bg-slate-50'"
                    @mouseenter="active = index"
                    @mousedown.prevent="pick(tpl)"
                >
                    <p class="flex flex-wrap items-center gap-1.5 text-sm font-medium text-slate-900">
                        <span class="truncate">{{ tpl.name }}</span>
                        <code v-if="tpl.shortcut" class="rounded bg-slate-100 px-1 py-px text-[11px] font-normal text-slate-600">/{{ tpl.shortcut }}</code>
                        <span v-if="tpl.product" class="rounded bg-sky-50 px-1 py-px text-[11px] font-normal text-sky-700">{{ tpl.product }}</span>
                        <span v-if="tpl.private" class="rounded bg-amber-50 px-1 py-px text-[11px] font-normal text-amber-700">{{ t('messaging.templates.private_badge') }}</span>
                    </p>
                    <p class="mt-0.5 line-clamp-2 text-xs text-slate-500">{{ excerpt(tpl.body, context) }}</p>
                </li>
            </ul>
            <p v-else class="px-3 py-6 text-center text-sm text-slate-500">
                {{ templates.length ? t('messaging.templates.no_results') : t('messaging.templates.picker_empty') }}
            </p>

            <div class="flex items-center justify-between gap-2 border-t border-slate-100 bg-slate-50/70 px-3 py-2 text-xs">
                <button type="button" class="text-slate-600 hover:text-slate-900" @click="save">
                    {{ t('messaging.templates.save_current') }}
                </button>
                <Link href="/inbox/templates" class="text-slate-600 hover:text-slate-900">
                    {{ t('messaging.templates.manage') }} ›
                </Link>
            </div>
        </div>
    </div>
</template>
