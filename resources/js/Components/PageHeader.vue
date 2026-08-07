<script setup>
defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: null },
});
</script>

<template>
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
        <div class="min-w-0">
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">{{ title }}</h1>
            <p v-if="subtitle" class="mt-1.5 text-sm text-slate-500">{{ subtitle }}</p>
        </div>
        <div v-if="$slots.action" class="flex shrink-0 gap-2">
            <slot name="action" />
        </div>
    </div>
</template>
