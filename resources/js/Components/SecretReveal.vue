<script setup>
/**
 * Jednorazová tajná hodnota (service token, podpisový kľúč webhooku).
 *
 * Toast na to nestačí: sám sa po pár sekundách schová a hodnotu treba
 * z vety vyznačiť myšou. Preto vlastné okno, ktoré nezmizne samo, drží
 * token v označenom poli a má tlačidlo na skopírovanie.
 *
 * Kliknutie mimo okna zámerne nezatvára – hodnotu už nikto druhýkrát
 * neukáže, takže omyl by stál nový token.
 */
import { nextTick, onUnmounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Icon from './Icon.vue';
import { t } from '../Composables/useLang';

const page = usePage();

const secret = ref(null);
const copied = ref(false);
const field = ref(null);

let copiedTimer = null;

const close = () => {
    secret.value = null;
    copied.value = false;
    clearTimeout(copiedTimer);
};

const copy = async () => {
    const value = secret.value?.value ?? '';

    try {
        // Bez HTTPS (a v starších prehliadačoch) `navigator.clipboard`
        // vôbec neexistuje – vtedy ostáva stará cesta cez označenie.
        await navigator.clipboard.writeText(value);
    } catch {
        field.value?.select();
        document.execCommand('copy');
    }

    copied.value = true;
    clearTimeout(copiedTimer);
    copiedTimer = setTimeout(() => (copied.value = false), 2500);
};

// Escape počúvame na dokumente, nie na dialógu: po kliknutí vedľa je
// fokus preč z okna a klávesa by inak neurobila nič.
const onKeydown = (event) => {
    if (event.key === 'Escape') close();
};

watch(secret, (value) => {
    value
        ? document.addEventListener('keydown', onKeydown)
        : document.removeEventListener('keydown', onKeydown);
});

watch(
    () => page.props.flash?.secret,
    (value) => {
        if (!value?.value) return;

        secret.value = value;
        copied.value = false;

        // Hodnota je rovno označená, takže funguje aj obyčajné Ctrl+C.
        nextTick(() => field.value?.select());
    },
    { immediate: true, deep: true },
);

onUnmounted(() => {
    clearTimeout(copiedTimer);
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <div
        v-if="secret"
        class="fixed inset-0 z-[60] flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
    >
        <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" />

        <div class="relative w-full max-w-xl rounded-2xl bg-white p-5 shadow-xl ring-1 ring-slate-900/10">
            <div class="flex items-start gap-3">
                <span class="rounded-xl bg-brand-50 p-2 text-brand-600">
                    <Icon name="key" :size="20" />
                </span>
                <div class="min-w-0 flex-1">
                    <h2 class="text-base font-semibold text-slate-900">{{ secret.title }}</h2>
                    <p v-if="secret.hint" class="mt-1 text-sm text-slate-500">{{ secret.hint }}</p>
                </div>
                <button
                    type="button"
                    class="-m-1 shrink-0 rounded-lg p-1 text-slate-400 transition hover:text-slate-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
                    :aria-label="t('common.secret.close')"
                    @click="close"
                >
                    <Icon name="close" :size="18" />
                </button>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-2">
                <input
                    ref="field"
                    :value="secret.value"
                    type="text"
                    readonly
                    class="min-w-64 flex-1 font-mono text-xs"
                    :aria-label="secret.title"
                    @focus="$event.target.select()"
                />
                <button type="button" class="btn-primary shrink-0" @click="copy">
                    <Icon :name="copied ? 'check' : 'copy'" :size="16" />
                    {{ copied ? t('common.secret.copied') : t('common.secret.copy') }}
                </button>
            </div>

            <div class="mt-4 flex items-start justify-between gap-4">
                <p class="flex items-start gap-1.5 text-xs text-amber-700">
                    <Icon name="warning" :size="14" class="mt-0.5 shrink-0" />
                    {{ t('common.secret.once') }}
                </p>
                <button type="button" class="btn-secondary shrink-0" @click="close">
                    {{ t('common.secret.done') }}
                </button>
            </div>
        </div>
    </div>
</template>
