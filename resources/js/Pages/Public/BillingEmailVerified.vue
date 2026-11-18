<script setup>
import { Head } from '@inertiajs/vue3';
import { t } from '../../Composables/useLang';

/**
 * Pristáva sem zákazník po kliknutí na odkaz z overovacieho e-mailu.
 *
 * Nie je prihlásený a ani nemá byť – stránka preto neukazuje nič
 * z back-officu okrem názvu firmy, ktorý aj tak pozná. Jazyk sa riadi
 * jeho prehliadačom (Accept-Language).
 */
defineProps({
    confirmed: { type: Boolean, required: true },
    organization: { type: String, required: true },
    email: { type: String, default: null },
});

// Názov firmy a adresa idú do textu ako tučné – escapované, nie ako HTML zo vstupu.
const strong = (value) => `<strong class="text-slate-900">${String(value ?? '')
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')}</strong>`;
</script>

<template>
    <Head :title="confirmed ? t('ui.billing_email.confirmed_title') : t('ui.billing_email.expired_title')" />

    <div class="flex min-h-screen items-center justify-center bg-slate-100 p-4">
        <div class="card w-full max-w-md p-8 text-center">
            <div
                class="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-full"
                :class="confirmed ? 'bg-teal-50 text-teal-600' : 'bg-amber-50 text-amber-600'"
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8"
                     stroke="currentColor" class="h-7 w-7">
                    <path v-if="confirmed" stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    <path v-else stroke-linecap="round" stroke-linejoin="round"
                          d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                </svg>
            </div>

            <template v-if="confirmed">
                <h1 class="text-xl font-semibold tracking-tight text-slate-900">{{ t('ui.billing_email.confirmed_heading') }}</h1>
                <p
                    class="mt-2 text-sm leading-relaxed text-slate-600"
                    v-html="t('ui.billing_email.confirmed_text', { email: strong(email), organization: strong(organization) })"
                ></p>
                <p class="mt-4 text-sm text-slate-500">{{ t('ui.billing_email.close') }}</p>
            </template>

            <template v-else>
                <h1 class="text-xl font-semibold tracking-tight text-slate-900">{{ t('ui.billing_email.expired_title') }}</h1>
                <!-- Najčastejšie je dôvod nudný: odkaz vypršal alebo sa adresa
                     medzitým zmenila. Netreba z toho robiť chybovú hlášku. -->
                <p
                    class="mt-2 text-sm leading-relaxed text-slate-600"
                    v-html="t('ui.billing_email.expired_text', { organization: strong(organization) })"
                ></p>
            </template>
        </div>
    </div>
</template>
