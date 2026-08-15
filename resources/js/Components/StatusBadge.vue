<script setup>
import { computed } from 'vue';
import { t } from '../Composables/useLang';

const props = defineProps({
    status: { type: String, default: 'none' },
    label: { type: String, default: null },
    /** Priama voľba farby – používajú ju faktúry, ktoré si tón nesú zo servera. */
    tone: { type: String, default: null },
});

const tones = {
    sky: { chip: 'bg-sky-50 text-sky-700 ring-sky-600/20', dot: 'bg-sky-500' },
    indigo: { chip: 'bg-indigo-50 text-indigo-700 ring-indigo-600/20', dot: 'bg-indigo-500' },
    emerald: { chip: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20', dot: 'bg-emerald-500' },
    amber: { chip: 'bg-amber-50 text-amber-800 ring-amber-600/20', dot: 'bg-amber-500' },
    orange: { chip: 'bg-orange-50 text-orange-800 ring-orange-600/20', dot: 'bg-orange-500' },
    rose: { chip: 'bg-rose-50 text-rose-700 ring-rose-600/20', dot: 'bg-rose-500' },
    slate: { chip: 'bg-slate-100 text-slate-600 ring-slate-500/20', dot: 'bg-slate-400' },
};

// Predplatné + doklady v jednej tabuľke – kľúče sa neprekrývajú.
const statusTones = {
    trialing: 'sky',
    active: 'emerald',
    past_due: 'amber',
    suspended: 'orange',
    cancelled: 'rose',
    none: 'slate',

    draft: 'slate',
    issued: 'sky',
    sent: 'indigo',
    partially_paid: 'amber',
    paid: 'emerald',
    overdue: 'rose',
};

/**
 * Bez popisku zo servera si ho nájdeme v lang – najprv medzi stavmi
 * predplatného, potom medzi stavmi dokladu. `t()` vracia pri chýbajúcom
 * kľúči jeho meno, takže neznámy stav skončí pri svojej vlastnej hodnote.
 */
const translate = (group) => {
    const key = `enums.${group}.${props.status}`;
    const line = t(key);

    return line === key ? null : line;
};

const style = computed(() => tones[props.tone ?? statusTones[props.status]] ?? tones.slate);

const text = computed(
    () => props.label ?? translate('subscription_status') ?? translate('invoice_status') ?? props.status,
);
</script>

<template>
    <span
        class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset"
        :class="style.chip"
    >
        <span class="h-1.5 w-1.5 rounded-full" :class="style.dot"></span>
        {{ text }}
    </span>
</template>
