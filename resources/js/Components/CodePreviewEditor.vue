<script setup>
import { computed, ref, watch } from 'vue';
import EmailHtmlFrame from './EmailHtmlFrame.vue';
import { t } from '../Composables/useLang';

/**
 * HTML kód s náhľadom v jednom poli – prepína sa Kód / Náhľad / Vedľa seba,
 * takže náhľad nemusí trvalo zaberať pravý stĺpec. Posledný režim si
 * pamätáme v prehliadači (len pohodlie, bez neho sa začne kódom).
 */
const props = defineProps({
    modelValue: { type: String, default: '' },
    // HTML pre náhľad (napr. s nahradenými značkami); inak sa ukáže samotný kód.
    preview: { type: String, default: null },
    id: { type: String, default: null },
    rows: { type: Number, default: 18 },
    required: { type: Boolean, default: false },
    storageKey: { type: String, default: 'code-preview-mode' },
});

const emit = defineEmits(['update:modelValue']);

const MODES = ['code', 'preview', 'split'];

const load = () => {
    try {
        const saved = window.localStorage.getItem(props.storageKey);
        return MODES.includes(saved) ? saved : 'code';
    } catch {
        return 'code';
    }
};

const mode = ref(load());

watch(mode, (value) => {
    try {
        window.localStorage.setItem(props.storageKey, value);
    } catch {
        // Bez úložiska si režim len nezapamätáme.
    }
});

const previewHtml = computed(() => props.preview ?? props.modelValue);
const showCode = computed(() => mode.value !== 'preview');
const showPreview = computed(() => mode.value !== 'code');
</script>

<template>
    <div>
        <div class="mb-2 inline-flex rounded-lg border border-slate-200 bg-slate-50 p-0.5 text-xs" role="tablist">
            <button
                v-for="m in MODES"
                :key="m"
                type="button"
                role="tab"
                :aria-selected="mode === m"
                class="rounded-md px-3 py-1 font-medium transition"
                :class="[
                    mode === m ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200' : 'text-slate-500 hover:text-slate-900',
                    m === 'split' ? 'hidden lg:inline-block' : '',
                ]"
                @click="mode = m"
            >{{ t(`mailing.editor.view_${m}`) }}</button>
        </div>

        <div :class="mode === 'split' ? 'grid gap-4 lg:grid-cols-2' : ''">
            <!-- Skryté pole prehliadač nevie zamerať, preto required len pri viditeľnom kóde (server overí aj tak). -->
            <textarea
                v-show="showCode"
                :id="id"
                :value="modelValue"
                :rows="rows"
                :required="required && showCode"
                spellcheck="false"
                class="font-mono text-xs"
                @input="emit('update:modelValue', $event.target.value)"
            ></textarea>
            <EmailHtmlFrame v-if="showPreview" :html="previewHtml" />
        </div>
    </div>
</template>
