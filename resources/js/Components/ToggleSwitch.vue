<script setup>
/**
 * Prepínač zapnuté / vypnuté s popiskom a nápovedou.
 *
 * Pre nastavenia je čitateľnejší než checkbox – stav vidno na prvý
 * pohľad aj v dlhom zozname.
 */
defineProps({
    label: { type: String, required: true },
    hint: { type: String, default: null },
    disabled: { type: Boolean, default: false },
});

const model = defineModel({ type: Boolean, default: false });
</script>

<template>
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
            <p class="text-sm font-medium text-slate-900">{{ label }}</p>
            <p v-if="hint" class="mt-0.5 text-sm text-slate-500">{{ hint }}</p>
            <slot />
        </div>
        <button
            type="button"
            role="switch"
            :aria-checked="model"
            :aria-label="label"
            :disabled="disabled"
            class="relative mt-0.5 inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/40 disabled:cursor-not-allowed disabled:opacity-50"
            :class="model ? 'bg-brand-600' : 'bg-slate-200'"
            @click="model = !model"
        >
            <span
                class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow ring-0 transition"
                :class="model ? 'translate-x-5' : 'translate-x-0'"
            ></span>
        </button>
    </div>
</template>
