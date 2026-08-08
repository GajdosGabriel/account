<script setup>
import { computed } from 'vue';
import DropdownMenu from './DropdownMenu.vue';
import { t } from '../Composables/useLang';

/**
 * Štandardné menu položky v zozname: zobraziť, upraviť, vymazať.
 *
 * Čo sa naozaj vykreslí, rozhoduje `abilities` – mapa z policy na
 * serveri (App\Support\Abilities). UI teda nikdy neponúkne akciu,
 * ktorú by server odmietol.
 *
 * Kôš má len dva stavy a menu ich odráža:
 *
 *   živý záznam   → zobraziť · upraviť · vymazať (do koša)
 *   záznam v koši → upraviť · obnoviť · odstrániť natrvalo
 *
 * Popisky aj potvrdzovacie otázky sú z lang/{locale}/actions.php.
 *
 *   <RowActions
 *       :abilities="token.can"
 *       :trashed="!!token.deleted_at"
 *       :base="`/developers/tokens/${token.id}`"
 *       :name="token.name"
 *       @edit="rename(token)"
 *   />
 */
const props = defineProps({
    /** Mapa oprávnení zo servera: { view, update, delete, restore, forceDelete } */
    abilities: { type: Object, default: () => ({}) },
    /** Základná adresa záznamu – z nej sa odvodí mazanie, obnova aj kôš. */
    base: { type: String, required: true },
    /** Názov záznamu do potvrdzovacej otázky. */
    name: { type: String, default: '' },
    /** Je záznam v koši? Vtedy zostáva len obnova a trvalé odstránenie. */
    trashed: { type: Boolean, default: false },
    /** Odkaz na detail; bez neho sa použije `base`. */
    viewHref: { type: String, default: null },
    /** Odkaz na úpravu; bez neho komponenta vyvolá udalosť `edit`. */
    editHref: { type: String, default: null },
    /** Vlastné adresy, ak sa nedržia konvencie `base`. */
    deleteUrl: { type: String, default: null },
    restoreUrl: { type: String, default: null },
    forceUrl: { type: String, default: null },
    /** Akcie špecifické pre entitu – vložia sa nad štandardné. */
    items: { type: Array, default: () => [] },
    align: { type: String, default: 'right' },
    size: { type: String, default: 'md' },
    label: { type: String, default: null },
});

const emit = defineEmits(['edit', 'view']);

const edit = () => ({
    label: t('actions.edit'),
    icon: 'pencil',
    can: 'update',
    ...(props.editHref ? { href: props.editHref } : { onSelect: () => emit('edit') }),
});

const menu = computed(() => {
    // V koši sa dá záznam ešte opraviť, inak už len vrátiť alebo dočistiť.
    if (props.trashed) {
        return [
            edit(),
            ...props.items,
            {
                label: t('actions.restore'),
                icon: 'refresh',
                can: 'restore',
                method: 'post',
                url: props.restoreUrl ?? `${props.base}/restore`,
            },
            {
                label: t('actions.force_delete'),
                icon: 'trash',
                can: 'forceDelete',
                danger: true,
                method: 'delete',
                url: props.forceUrl ?? `${props.base}/force`,
                confirm: t('actions.confirm.force_delete', { name: props.name }),
            },
        ];
    }

    return [
        {
            label: t('actions.view'),
            icon: 'eye',
            can: 'view',
            href: props.viewHref ?? props.base,
        },
        edit(),
        ...props.items,
        { separator: true },
        {
            label: t('actions.delete'),
            icon: 'trash',
            can: 'delete',
            danger: true,
            method: 'delete',
            url: props.deleteUrl ?? props.base,
            confirm: t('actions.confirm.delete', { name: props.name }),
        },
    ];
});
</script>

<template>
    <DropdownMenu
        :abilities="abilities"
        :items="menu"
        :align="align"
        :size="size"
        :label="label"
    />
</template>
