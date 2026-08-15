<script setup>
/**
 * Flash hlásenie zo servera ako plávajúci toast.
 *
 * Správanie je zámerne „ako inde“: hlásenie sa samo schová po pár
 * sekundách, krížikom sa dá zavrieť hneď a kým je nad ním myš (alebo
 * fokus z klávesnice), odpočet stojí – inak by dlhšie hlásenie zmizlo
 * skôr, než ho stihneš dočítať.
 *
 * Toast nie je v toku stránky: keby bol, po zmiznutí by obsah poskočil.
 */
import { computed, onUnmounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Icon from './Icon.vue';

const props = defineProps({
    // Chyba zostáva dlhšie – býva dlhšia a treba ju naozaj prečítať.
    successDuration: { type: Number, default: 5000 },
    errorDuration: { type: Number, default: 8000 },
});

const page = usePage();

const visible = ref(false);
const text = ref('');
const isError = ref(false);
const paused = ref(false);

// Nový toast musí naštartovať animáciu odznova – aj keď je text ten istý
// (napr. dvakrát po sebe rovnaká chyba). Preto vlastný kľúč, nie text.
const seq = ref(0);

const duration = computed(() => (isError.value ? props.errorDuration : props.successDuration));

let timer = null;
let deadline = 0;
let remaining = 0;

const clearTimer = () => {
    if (timer) {
        clearTimeout(timer);
        timer = null;
    }
};

const startTimer = (ms) => {
    clearTimer();
    remaining = ms;
    deadline = Date.now() + ms;
    timer = setTimeout(dismiss, ms);
};

const dismiss = () => {
    clearTimer();
    paused.value = false;
    visible.value = false;
};

const pause = () => {
    if (!visible.value || paused.value) return;
    remaining = Math.max(0, deadline - Date.now());
    clearTimer();
    paused.value = true;
};

const resume = () => {
    if (!visible.value || !paused.value) return;
    paused.value = false;
    startTimer(remaining);
};

const show = (message, error) => {
    text.value = message;
    isError.value = error;
    paused.value = false;
    visible.value = true;
    seq.value += 1;
    startTimer(error ? props.errorDuration : props.successDuration);
};

// Inertia posiela pri každej návšteve nový objekt props, takže stačí
// sledovať referenciu – zachytí aj dve rovnaké hlásenia za sebou.
watch(
    () => page.props.flash,
    (flash) => {
        const error = flash?.error;
        const success = flash?.success;

        if (error) {
            show(error, true);
        } else if (success) {
            show(success, false);
        }
        // Stránka bez flashu starý toast nezhasína – o to sa stará odpočet.
    },
    { immediate: true, deep: true },
);

onUnmounted(clearTimer);
</script>

<template>
    <div class="pointer-events-none fixed inset-x-4 top-20 z-50 flex justify-center sm:inset-x-auto sm:right-4 sm:justify-end">
        <transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 -translate-y-2 sm:translate-x-3 sm:translate-y-0"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="opacity-0 -translate-y-1 sm:translate-x-3 sm:translate-y-0"
        >
            <div
                v-if="visible"
                :key="seq"
                class="pointer-events-auto w-full overflow-hidden rounded-2xl bg-white shadow-lg shadow-slate-900/10 ring-1 sm:w-96"
                :class="isError ? 'ring-rose-600/20' : 'ring-emerald-600/20'"
                :role="isError ? 'alert' : 'status'"
                :aria-live="isError ? 'assertive' : 'polite'"
                @mouseenter="pause"
                @mouseleave="resume"
                @focusin="pause"
                @focusout="resume"
            >
                <div
                    class="flex items-start gap-3 px-4 py-3 text-sm"
                    :class="isError ? 'bg-rose-50 text-rose-800' : 'bg-emerald-50 text-emerald-800'"
                >
                    <Icon :name="isError ? 'warning' : 'check'" :size="18" class="mt-0.5" />
                    <span class="min-w-0 flex-1 break-words">{{ text }}</span>
                    <button
                        type="button"
                        class="-m-1 shrink-0 rounded-lg p-1 opacity-60 transition hover:opacity-100 focus:opacity-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-current"
                        aria-label="Zavrieť hlásenie"
                        @click="dismiss"
                    >
                        <Icon name="close" :size="16" />
                    </button>
                </div>

                <!-- Ukazovateľ zvyšného času: kým odpočet stojí, stojí aj pruh. -->
                <div class="h-1 w-full" :class="isError ? 'bg-rose-100' : 'bg-emerald-100'">
                    <div
                        class="h-full origin-left"
                        :class="isError ? 'bg-rose-400' : 'bg-emerald-400'"
                        :style="{
                            animation: `flash-countdown ${duration}ms linear forwards`,
                            animationPlayState: paused ? 'paused' : 'running',
                        }"
                    />
                </div>
            </div>
        </transition>
    </div>
</template>

<style>
@keyframes flash-countdown {
    from { transform: scaleX(1); }
    to { transform: scaleX(0); }
}
</style>
