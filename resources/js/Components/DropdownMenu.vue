<script setup>
import { computed, onMounted, onUnmounted, ref, nextTick } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import Icon from './Icon.vue';
import LocaleFlag from './LocaleFlag.vue';
import { t } from '../Composables/useLang';

/**
 * Kontextové menu pre položku v zozname.
 *
 * Kľúčová vec: každá položka nesie `can` – meno oprávnenia z InvoicePolicy.
 * Ak backend v `can` mape povolenie neposlal, položka sa nevykreslí. UI teda
 * nikdy neponúkne akciu, ktorú by server odmietol, a keď sa raz zmení
 * pravidlo v policy, menu sa prispôsobí samo.
 *
 *   <DropdownMenu :abilities="invoice.can" :items="[
 *       { label: 'Upraviť', icon: 'pencil', can: 'update', href: '/invoices/1/edit' },
 *       { label: 'Zmazať',  icon: 'trash',  can: 'delete', method: 'delete',
 *         url: '/invoices/1', danger: true, confirm: 'Naozaj zmazať?' },
 *   ]" />
 */
const props = defineProps({
    /** Mapa oprávnení zo servera, napr. { update: true, delete: false } */
    abilities: { type: Object, default: () => ({}) },
    /** Položky menu */
    items: { type: Array, default: () => [] },
    align: { type: String, default: 'right' },
    /** Zobraziť aj zakázané položky (neaktívne) namiesto ich skrytia */
    showDisabled: { type: Boolean, default: false },
    label: { type: String, default: null },
    /**
     * Kód jazyka na tlačidle – prepínač jazyka ukazuje vlajku namiesto
     * popisku. Položky menu majú vlastné `flag` na tom istom mieste, kde
     * ostatné menu kreslia `icon`.
     */
    flag: { type: String, default: null },
    size: { type: String, default: 'md' },
});

const open = ref(false);
const root = ref(null);
const panel = ref(null);
const dropUp = ref(false);
const position = ref({ top: 0, left: 0 });

const allowed = (item) => {
    if (item.can === undefined || item.can === null) return true;
    if (typeof item.can === 'boolean') return item.can;
    return props.abilities?.[item.can] === true;
};

// Skupiny oddelené separátorom – zbytočné oddeľovače na okrajoch odfiltrujeme.
const visible = computed(() => {
    const list = props.items
        .filter((item) => item.separator || props.showDisabled || allowed(item))
        .map((item) => ({ ...item, disabled: !item.separator && !allowed(item) }));

    const cleaned = [];
    for (const item of list) {
        if (item.separator && (cleaned.length === 0 || cleaned[cleaned.length - 1]?.separator)) continue;
        cleaned.push(item);
    }
    while (cleaned.length && cleaned[cleaned.length - 1].separator) cleaned.pop();

    return cleaned;
});

/**
 * Umiestnenie menu.
 *
 * Panel sa vykresľuje cez `Teleport` priamo v `body`, nie v riadku tabuľky.
 * Zoznamy aj karty sú totiž zabalené v `overflow-hidden` (kvôli zaobleným
 * rohom) a ten by absolútne umiestnené menu odrezal – pri krátkej tabuľke
 * o dvoch riadkoch by z neho nebolo vidieť nič a akcia by sa javila ako
 * nefunkčná. Za cenu prepočtu súradníc je menu vždy celé vidieť.
 */
const place = () => {
    const rect = root.value?.getBoundingClientRect();

    if (!rect) return;

    const panelRect = panel.value?.getBoundingClientRect();
    const width = panelRect?.width ?? 224;
    const height = panelRect?.height ?? 0;

    // Nahor len vtedy, keď sa dole naozaj nezmestí a hore áno.
    dropUp.value = height > 0
        && window.innerHeight - rect.bottom < height + 12
        && rect.top > height + 12;

    position.value = {
        top: dropUp.value ? rect.top - height - 6 : rect.bottom + 6,
        left: props.align === 'right'
            ? Math.max(8, rect.right - width)
            : Math.min(rect.left, window.innerWidth - width - 8),
    };
};

const toggle = async () => {
    open.value = !open.value;

    if (!open.value) return;

    // Prvé umiestnenie ešte pred vykreslením, aby menu nepreblislo v rohu;
    // po vykreslení sa dopočíta podľa skutočnej výšky.
    place();
    await nextTick();
    place();
};

const run = (item) => {
    if (item.disabled) return;

    open.value = false;

    if (item.onSelect) return item.onSelect();
    if (item.confirm && !window.confirm(item.confirm)) return;

    if (item.method) {
        // router.delete berie len (url, options) – s trojicou argumentov
        // by sa preserveScroll ticho zahodilo a stránka by odskočila hore.
        item.method === 'delete'
            ? router.delete(item.url, { preserveScroll: true })
            : router[item.method](item.url, item.data ?? {}, { preserveScroll: true });
    } else if (item.url) {
        window.location.href = item.url;
    }
};

// Panel je v `body`, takže „mimo menu“ znamená mimo tlačidla aj mimo panela.
const onOutside = (event) => {
    if (root.value?.contains(event.target) || panel.value?.contains(event.target)) return;

    open.value = false;
};

const onEscape = (event) => {
    if (event.key === 'Escape') open.value = false;
};

// Pri rolovaní by menu zostalo visieť nad pôvodným miestom – radšej zavrieme.
const onScroll = () => { open.value = false; };

onMounted(() => {
    document.addEventListener('click', onOutside);
    document.addEventListener('keydown', onEscape);
    window.addEventListener('scroll', onScroll, true);
    window.addEventListener('resize', onScroll);
});

onUnmounted(() => {
    document.removeEventListener('click', onOutside);
    document.removeEventListener('keydown', onEscape);
    window.removeEventListener('scroll', onScroll, true);
    window.removeEventListener('resize', onScroll);
});
</script>

<template>
    <div ref="root" class="relative inline-block text-left">
        <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-lg border border-transparent text-slate-500 transition hover:border-slate-200 hover:bg-white hover:text-slate-900"
            :class="[
                label || flag ? 'px-3 py-1.5 text-sm font-medium' : (size === 'sm' ? 'h-7 w-7 justify-center' : 'h-8 w-8 justify-center'),
                open ? 'border-slate-200 bg-white text-slate-900 shadow-sm' : '',
            ]"
            :aria-expanded="open"
            aria-haspopup="true"
            @click.stop="toggle"
        >
            <LocaleFlag v-if="flag" :code="flag" />
            <span v-if="label">{{ label }}</span>
            <Icon name="dots" :size="label || flag ? 15 : 17" />
        </button>

        <Teleport to="body">
            <transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="opacity-0 scale-95"
                leave-active-class="transition duration-100 ease-in"
                leave-to-class="opacity-0 scale-95"
            >
                <div
                    v-if="open"
                    ref="panel"
                    class="fixed z-50 w-56 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-xl shadow-slate-900/10"
                    :class="dropUp ? 'origin-bottom-right' : 'origin-top-right'"
                    :style="{ top: `${position.top}px`, left: `${position.left}px` }"
                >
                <template v-for="(item, index) in visible" :key="index">
                    <div v-if="item.separator" class="my-1 border-t border-slate-100"></div>

                    <Link
                        v-else-if="item.href && !item.disabled"
                        :href="item.href"
                        class="flex w-full items-center gap-2.5 px-3.5 py-2 text-sm text-slate-700 transition hover:bg-slate-50"
                        :class="item.danger ? 'text-rose-600 hover:bg-rose-50' : ''"
                        @click="open = false"
                    >
                        <LocaleFlag v-if="item.flag" :code="item.flag" />
                        <Icon v-else-if="item.icon" :name="item.icon" :size="16" class="text-slate-400" />
                        <span class="min-w-0 flex-1 truncate">{{ item.label }}</span>
                        <span v-if="item.badge" class="shrink-0 text-xs text-slate-400">{{ item.badge }}</span>
                    </Link>

                    <button
                        v-else
                        type="button"
                        :disabled="item.disabled"
                        class="flex w-full items-center gap-2.5 px-3.5 py-2 text-left text-sm transition"
                        :class="[
                            item.disabled
                                ? 'cursor-not-allowed text-slate-300'
                                : item.danger
                                    ? 'text-rose-600 hover:bg-rose-50'
                                    : 'text-slate-700 hover:bg-slate-50',
                        ]"
                        :title="item.disabled ? (item.disabledHint ?? t('actions.disabled')) : null"
                        @click="run(item)"
                    >
                        <LocaleFlag v-if="item.flag" :code="item.flag" />
                        <Icon v-else-if="item.icon" :name="item.icon" :size="16" :class="item.disabled ? 'text-slate-300' : 'text-slate-400'" />
                        <span class="min-w-0 flex-1 truncate">{{ item.label }}</span>
                        <span v-if="item.badge" class="shrink-0 text-xs text-slate-400">{{ item.badge }}</span>
                    </button>
                </template>

                    <div v-if="!visible.length" class="px-3.5 py-2 text-sm text-slate-400">
                        {{ t('actions.empty') }}
                    </div>
                </div>
            </transition>
        </Teleport>
    </div>
</template>
