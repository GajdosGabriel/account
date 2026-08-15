<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { t } from '../Composables/useLang';

/**
 * Stránkovanie pre Laravel paginátor.
 *
 * Berie priamo objekt, ktorý vracia `->paginate()` cez Inertiu, takže sa
 * dá pripnúť na akýkoľvek zoznam bez ďalšieho mapovania. Pri jednej strane
 * sa nevykreslí vôbec – prázdne stránkovanie pod trojriadkovou tabuľkou
 * je len šum.
 */
const props = defineProps({
    /** { data, links, from, to, total, current_page, last_page } */
    meta: { type: Object, required: true },
    /** Názov záznamov v jazyku rozhrania, napríklad `t('invoices.records')`. */
    label: { type: String, default: '' },
});

const show = computed(() => (props.meta?.last_page ?? 1) > 1);

// "« Predchádzajúca" / "Nasledujúca »" nahradíme šípkami, čísla necháme.
const links = computed(() =>
    (props.meta?.links ?? []).map((link, index, all) => ({
        ...link,
        text:
            index === 0 ? '‹' : index === all.length - 1 ? '›' : link.label.replace(/&laquo;|&raquo;/g, '').trim(),
        arrow: index === 0 || index === all.length - 1,
    })),
);
</script>

<template>
    <div
        v-if="show || meta.total"
        class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-4 py-3"
    >
        <p class="text-sm text-slate-500">
            <template v-if="meta.total">
                {{ meta.from }}–{{ meta.to }} {{ t('common.pagination.of') }}
                <strong class="font-medium text-slate-700">{{ meta.total }}</strong>
                {{ label }}
            </template>
            <template v-else>{{ t('common.pagination.empty') }}</template>
        </p>

        <nav v-if="show" class="flex flex-wrap gap-1">
            <Link
                v-for="link in links"
                :key="link.label"
                :href="link.url ?? '#'"
                preserve-scroll
                preserve-state
                class="min-w-9 rounded-lg px-3 py-1.5 text-center text-sm transition"
                :class="[
                    link.active
                        ? 'bg-brand-600 font-medium text-white shadow-sm shadow-brand-600/20'
                        : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900',
                    !link.url ? 'pointer-events-none opacity-30' : '',
                    link.arrow ? 'font-semibold' : '',
                ]"
            >
                {{ link.text }}
            </Link>
        </nav>
    </div>
</template>
