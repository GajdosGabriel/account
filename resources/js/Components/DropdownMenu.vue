<script setup>
import { computed, onMounted, onUnmounted, ref, nextTick } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import Icon from './Icon.vue';

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
    size: { type: String, default: 'md' },
});

const open = ref(false);
const root = ref(null);
const dropUp = ref(false);

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

const toggle = async () => {
    open.value = !open.value;

    if (!open.value) return;

    // Pri položkách na spodku stránky menu vyklopíme nahor.
    await nextTick();
    const rect = root.value?.getBoundingClientRect();
    dropUp.value = rect ? window.innerHeight - rect.bottom < 260 : false;
};

const run = (item) => {
    if (item.disabled) return;

    open.value = false;

    if (item.onSelect) return item.onSelect();
    if (item.confirm && !window.confirm(item.confirm)) return;

    if (item.method) {
        router[item.method](item.url, item.data ?? {}, { preserveScroll: true });
    } else if (item.url) {
        window.location.href = item.url;
    }
};

const onOutside = (event) => {
    if (!root.value?.contains(event.target)) open.value = false;
};

const onEscape = (event) => {
    if (event.key === 'Escape') open.value = false;
};

onMounted(() => {
    document.addEventListener('click', onOutside);
    document.addEventListener('keydown', onEscape);
});

onUnmounted(() => {
    document.removeEventListener('click', onOutside);
    document.removeEventListener('keydown', onEscape);
});
</script>

<template>
    <div ref="root" class="relative inline-block text-left">
        <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-lg border border-transparent text-slate-500 transition hover:border-slate-200 hover:bg-white hover:text-slate-900"
            :class="[
                label ? 'px-3 py-1.5 text-sm font-medium' : (size === 'sm' ? 'h-7 w-7 justify-center' : 'h-8 w-8 justify-center'),
                open ? 'border-slate-200 bg-white text-slate-900 shadow-sm' : '',
            ]"
            :aria-expanded="open"
            aria-haspopup="true"
            @click.stop="toggle"
        >
            <span v-if="label">{{ label }}</span>
            <Icon name="dots" :size="label ? 15 : 17" />
        </button>

        <transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 scale-95"
            leave-active-class="transition duration-100 ease-in"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-if="open"
                class="absolute z-50 w-56 origin-top-right overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-xl shadow-slate-900/10"
                :class="[
                    align === 'right' ? 'right-0' : 'left-0',
                    dropUp ? 'bottom-full mb-1.5' : 'top-full mt-1.5',
                ]"
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
                        <Icon v-if="item.icon" :name="item.icon" :size="16" class="text-slate-400" />
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
                        :title="item.disabled ? (item.disabledHint ?? 'Táto akcia nie je pre tento doklad povolená') : null"
                        @click="run(item)"
                    >
                        <Icon v-if="item.icon" :name="item.icon" :size="16" :class="item.disabled ? 'text-slate-300' : 'text-slate-400'" />
                        <span class="min-w-0 flex-1 truncate">{{ item.label }}</span>
                        <span v-if="item.badge" class="shrink-0 text-xs text-slate-400">{{ item.badge }}</span>
                    </button>
                </template>

                <div v-if="!visible.length" class="px-3.5 py-2 text-sm text-slate-400">
                    Žiadne dostupné akcie
                </div>
            </div>
        </transition>
    </div>
</template>
