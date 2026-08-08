<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Icon from '../Components/Icon.vue';
import DropdownMenu from '../Components/DropdownMenu.vue';

defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: null },
});

const page = usePage();
const flash = computed(() => page.props.flash ?? {});

// Prihlasovacia obrazovka je prvé, čo človek uvidí – jazyk sa preto
// prepína už tu, nielen po prihlásení.
const locales = computed(() => page.props.locales ?? []);
const currentLocale = computed(() => page.props.locale ?? 'sk');

const localeShort = computed(
    () => locales.value.find((item) => item.value === currentLocale.value)?.short ?? currentLocale.value.toUpperCase(),
);

const localeMenu = computed(() => locales.value.map((item) => ({
    label: item.label,
    method: 'post',
    url: '/locale',
    data: { locale: item.value },
    badge: item.value === currentLocale.value ? '✓' : item.short,
})));
</script>

<template>
    <div class="grid min-h-screen lg:grid-cols-2">
        <!-- Ľavý panel s brandingom, na mobile skrytý -->
        <aside class="relative hidden overflow-hidden bg-gradient-to-br from-brand-600 via-violet-600 to-indigo-800 p-12 lg:flex lg:flex-col lg:justify-between">
            <div class="absolute -right-24 -top-24 h-96 w-96 rounded-full bg-white/10 blur-3xl"></div>
            <div class="absolute -bottom-32 -left-20 h-96 w-96 rounded-full bg-teal-300/20 blur-3xl"></div>

            <Link href="/" class="relative flex items-center gap-2.5 text-white">
                <span class="chip h-9 w-9 bg-white/15 text-sm font-bold backdrop-blur">A</span>
                <span class="font-semibold tracking-tight">Account</span>
            </Link>

            <div class="relative">
                <h2 class="text-3xl font-semibold leading-tight text-white">
                    Jedno miesto pre firmy,<br />limity a fakturáciu.
                </h2>
                <ul class="mt-8 space-y-3.5 text-sm text-white/80">
                    <li class="flex items-center gap-3">
                        <span class="chip h-6 w-6 bg-white/15"><Icon name="check" :size="14" /></span>
                        IČO a IČ DPH overené v registri, nie prepisované ručne
                    </li>
                    <li class="flex items-center gap-3">
                        <span class="chip h-6 w-6 bg-white/15"><Icon name="check" :size="14" /></span>
                        Limity a predplatné pre všetky projekty na jednom mieste
                    </li>
                    <li class="flex items-center gap-3">
                        <span class="chip h-6 w-6 bg-white/15"><Icon name="check" :size="14" /></span>
                        Prehľad, kto čo má zaplatené a kto je pri strope
                    </li>
                </ul>
            </div>

            <p class="relative text-xs text-white/50">© {{ new Date().getFullYear() }} Account</p>
        </aside>

        <!-- Formulár -->
        <main class="relative flex items-center justify-center px-4 py-12">
            <div v-if="locales.length > 1" class="absolute right-4 top-4">
                <DropdownMenu :items="localeMenu" :label="localeShort" />
            </div>

            <div class="w-full max-w-sm">
                <div class="mb-8 text-center lg:hidden">
                    <span class="chip mx-auto h-12 w-12 bg-gradient-to-br from-brand-500 to-violet-600 font-bold text-white shadow-lg shadow-brand-600/25">A</span>
                </div>

                <div class="mb-7">
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900">{{ title }}</h1>
                    <p v-if="subtitle" class="mt-1.5 text-sm text-slate-500">{{ subtitle }}</p>
                </div>

                <div
                    v-if="flash.success"
                    class="mb-5 flex items-start gap-2.5 rounded-2xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800 ring-1 ring-emerald-600/15"
                >
                    <Icon name="check" :size="18" class="mt-0.5" />
                    <span>{{ flash.success }}</span>
                </div>

                <slot />
            </div>
        </main>
    </div>
</template>
