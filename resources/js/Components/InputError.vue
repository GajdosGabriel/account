<script setup>
defineProps({ message: { type: String, default: null } });
</script>

<template>
    <transition
        enter-active-class="transition duration-150 ease-out"
        enter-from-class="opacity-0 -translate-y-0.5"
    >
        <p v-if="message" class="mt-1.5 text-sm text-rose-600">{{ message }}</p>
    </transition>
</template>
