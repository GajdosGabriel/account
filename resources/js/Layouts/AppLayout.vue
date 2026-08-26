<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import Icon from '../Components/Icon.vue';
import DropdownMenu from '../Components/DropdownMenu.vue';
import FlashMessage from '../Components/FlashMessage.vue';
import SecretReveal from '../Components/SecretReveal.vue';
import { t } from '../Composables/useLang';

const page = usePage();
const auth = computed(() => page.props.auth);
const menuOpen = ref(false);

// Prehľad tu nie je zámerne – na dashboard vedie logo vľavo a dva
// odkazy na to isté miesto vedľa seba si človek prečíta ako chybu.
//
// Computed, nie konštanta: layout medzi návštevami prežíva, takže po
// prepnutí jazyka by inak zostali popisky v pôvodnom jazyku.
const navigation = computed(() => [
    { name: t('common.nav.organizations'), href: '/organizations', icon: 'building' },
    { name: t('common.nav.products'), href: '/products', icon: 'card' },
    { name: t('common.nav.invoices'), href: '/invoices', icon: 'invoice' },
    { name: t('common.nav.developers'), href: '/developers', icon: 'code' },
]);

const isCurrent = (href) => page.url.startsWith(href);

const initials = computed(() =>
    (auth.value?.user?.name ?? '?')
        .split(' ')
        .map((part) => part[0])
        .slice(0, 2)
        .join('')
        .toUpperCase(),
);

const logout = () => router.post('/logout');

/* ---------- jazyk rozhrania ---------- */

const locales = computed(() => page.props.locales ?? []);
const currentLocale = computed(() => page.props.locale ?? 'sk');

// Voľba sa ukladá do session, preto POST a nie odkaz – po prepnutí
// zostávaš na tej istej stránke, len v inom jazyku.
const localeMenu = computed(() => locales.value.map((item) => ({
    label: item.label,
    flag: item.value,
    method: 'post',
    url: '/locale',
    data: { locale: item.value },
    badge: item.value === currentLocale.value ? '✓' : null,
})));

// Zatvorí otvorené menu pri kliknutí mimo neho.
const closeAll = (event) => {
    if (!event.target.closest('[data-dropdown]')) {
        menuOpen.value = false;
    }
};

onMounted(() => document.addEventListener('click', closeAll));
onUnmounted(() => document.removeEventListener('click', closeAll));
</script>

<template>
    <div class="min-h-screen">
        <header class="sticky top-0 z-30 border-b border-slate-200/70 bg-white/80 backdrop-blur-md">
            <div class="mx-auto flex h-16 max-w-6xl items-center gap-6 px-4">
                <Link href="/dashboard" class="flex items-center gap-2.5">
                    <span class="chip h-9 w-9 bg-gradient-to-br from-brand-500 to-violet-600 text-sm font-bold text-white shadow-sm shadow-brand-600/30">
                        A
                    </span>
                    <span class="font-semibold tracking-tight text-slate-900">Account</span>
                </Link>

                <nav class="hidden items-center gap-1 md:flex">
                    <Link
                        v-for="item in navigation"
                        :key="item.href"
                        :href="item.href"
                        class="flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-medium transition"
                        :class="isCurrent(item.href)
                            ? 'bg-brand-50 text-brand-700'
                            : 'text-slate-600 hover:bg-slate-100/70 hover:text-slate-900'"
                    >
                        <Icon :name="item.icon" :size="17" />
                        {{ item.name }}
                    </Link>
                </nav>

                <div class="ml-auto flex items-center gap-2">
                    <!-- Jazyk rozhrania -->
                    <DropdownMenu v-if="locales.length > 1" :items="localeMenu" :flag="currentLocale" />

                    <!-- Používateľské menu -->
                    <div class="relative" data-dropdown>
                        <button
                            type="button"
                            class="flex h-9 max-w-48 items-center gap-2 rounded-xl bg-gradient-to-br from-slate-700 to-slate-900 px-3 text-sm font-semibold text-white transition hover:opacity-90"
                            @click="menuOpen = !menuOpen"
                        >
                            <!-- Na úzkej obrazovke ostávajú iniciálky, celé meno by tlačilo navigáciu -->
                            <span class="hidden truncate sm:block">{{ auth.user.name }}</span>
                            <span class="text-xs sm:hidden">{{ initials }}</span>
                        </button>

                        <transition
                            enter-active-class="transition duration-150 ease-out"
                            enter-from-class="opacity-0 -translate-y-1"
                            leave-active-class="transition duration-100 ease-in"
                            leave-to-class="opacity-0"
                        >
                            <div v-if="menuOpen" class="absolute right-0 z-40 mt-2 w-60 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl shadow-slate-900/5">
                                <div class="border-b border-slate-100 px-4 py-3">
                                    <p class="truncate text-sm font-semibold text-slate-900">{{ auth.user.name }}</p>
                                    <p class="truncate text-xs text-slate-500">{{ auth.user.email }}</p>
                                </div>
                                <Link href="/settings" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-700 transition hover:bg-slate-50">
                                    <Icon name="settings" :size="17" />
                                    {{ t('common.nav.settings') }}
                                </Link>
                                <button
                                    type="button"
                                    class="flex w-full items-center gap-2.5 border-t border-slate-100 px-4 py-2.5 text-left text-sm text-rose-600 transition hover:bg-rose-50"
                                    @click="logout"
                                >
                                    <Icon name="logout" :size="17" />
                                    {{ t('common.nav.logout') }}
                                </button>
                            </div>
                        </transition>
                    </div>
                </div>
            </div>

            <!-- Mobilná navigácia -->
            <nav class="flex gap-1 overflow-x-auto border-t border-slate-100 px-4 py-2 md:hidden">
                <Link
                    v-for="item in navigation"
                    :key="item.href"
                    :href="item.href"
                    class="flex shrink-0 items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium transition"
                    :class="isCurrent(item.href) ? 'bg-brand-50 text-brand-700' : 'text-slate-600'"
                >
                    <Icon :name="item.icon" :size="16" />
                    {{ item.name }}
                </Link>
            </nav>
        </header>

        <FlashMessage />
        <SecretReveal />

        <main class="mx-auto max-w-6xl px-4 py-8">
            <slot />
        </main>

        <footer class="mx-auto max-w-6xl px-4 pb-8 text-center text-xs text-slate-400">
            {{ t('common.footer') }}
        </footer>
    </div>
</template>
