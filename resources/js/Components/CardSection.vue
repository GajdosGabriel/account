<script setup>
import Icon from './Icon.vue';

defineProps({
    title: { type: String, default: null },
    description: { type: String, default: null },
    icon: { type: String, default: null },
    tone: { type: String, default: 'brand' },
});

const tones = {
    brand: 'bg-brand-50 text-brand-600',
    emerald: 'bg-emerald-50 text-emerald-600',
    amber: 'bg-amber-50 text-amber-600',
    rose: 'bg-rose-50 text-rose-600',
    slate: 'bg-slate-100 text-slate-500',
};
</script>

<template>
    <section class="card overflow-hidden">
        <header v-if="title" class="flex items-start gap-3 border-b border-slate-100 px-5 py-4">
            <span v-if="icon" class="chip mt-0.5 h-9 w-9" :class="tones[tone] ?? tones.brand">
                <Icon :name="icon" :size="18" />
            </span>
            <div class="min-w-0">
                <h2 class="font-semibold text-slate-900">{{ title }}</h2>
                <p v-if="description" class="mt-0.5 text-sm text-slate-500">{{ description }}</p>
            </div>
            <div v-if="$slots.action" class="ml-auto shrink-0">
                <slot name="action" />
            </div>
        </header>

        <div class="p-5">
            <slot />
        </div>

        <footer v-if="$slots.footer" class="border-t border-slate-100 bg-slate-50/70 px-5 py-4">
            <slot name="footer" />
        </footer>
    </section>
</template>
