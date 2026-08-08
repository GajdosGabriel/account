<script setup>
import { ref } from 'vue';
import Icon from './Icon.vue';
import { t } from '../Composables/useLang';

/**
 * Pole na heslo s možnosťou zobraziť, čo je v ňom napísané.
 *
 * Preklep v hesle, ktoré nikdy neuvidíš, sa prejaví až chybovou hláškou
 * po odoslaní – pri „nové heslo“ a jeho potvrdení dokonca dvakrát.
 * Prepínač je preto pri každom takom poli, nie len pri prihlásení.
 *
 * Zobrazenie sa po odoslaní nedrží: `useForm().reset('password')` zmaže
 * hodnotu, ale stav prepínača je lokálny a ostane, kým sa pole nevykreslí
 * odznova – to je v poriadku, heslo v ňom už nie je.
 *
 *   <PasswordInput id="password" v-model="form.password" autocomplete="new-password" required />
 */
defineProps({
    id: { type: String, default: null },
    autocomplete: { type: String, default: 'current-password' },
    placeholder: { type: String, default: '••••••••' },
    required: { type: Boolean, default: false },
    autofocus: { type: Boolean, default: false },
});

const model = defineModel({ type: String, default: '' });

const visible = ref(false);
</script>

<template>
    <div class="relative">
        <input
            :id="id"
            v-model="model"
            :type="visible ? 'text' : 'password'"
            :autocomplete="autocomplete"
            :placeholder="placeholder"
            :required="required"
            :autofocus="autofocus"
            class="pr-11!"
        />
        <button
            type="button"
            class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-xl text-slate-400 transition hover:text-slate-700"
            :title="visible ? t('actions.hide_password') : t('actions.show_password')"
            :aria-label="visible ? t('actions.hide_password') : t('actions.show_password')"
            :aria-pressed="visible"
            tabindex="-1"
            @click="visible = !visible"
        >
            <Icon :name="visible ? 'eye-off' : 'eye'" :size="17" />
        </button>
    </div>
</template>
