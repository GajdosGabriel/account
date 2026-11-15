<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import CardSection from '../../Components/CardSection.vue';
import InputError from '../../Components/InputError.vue';
import PageHeader from '../../Components/PageHeader.vue';
import PasswordInput from '../../Components/PasswordInput.vue';

const props = defineProps({
    user: { type: Object, required: true },
    operators: { type: Array, default: () => [] },
});

const profileForm = useForm({ name: props.user.name, email: props.user.email });

const passwordForm = useForm({
    current_password: '', password: '', password_confirmation: '',
});

const operatorForm = useForm({ name: '', email: '' });
const addingOperator = ref(false);

const updateProfile = () => profileForm.patch('/settings/profile', { preserveScroll: true });

const updatePassword = () => passwordForm.put('/settings/password', {
    preserveScroll: true,
    onSuccess: () => passwordForm.reset(),
    onError: () => passwordForm.reset('current_password'),
});

const addOperator = () => operatorForm.post('/settings/operators', {
    preserveScroll: true,
    onSuccess: () => { operatorForm.reset(); addingOperator.value = false; },
});

const removeOperator = (op) => {
    if (!confirm(`Odobrať prístup operátorovi ${op.email}? Odhlási sa zo všetkých zariadení.`)) return;
    router.delete(`/settings/operators/${op.id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Nastavenia" />

    <PageHeader title="Nastavenia" subtitle="Tvoj účet a ostatní operátori back-office." />

    <div class="grid gap-6 lg:grid-cols-3">
        <CardSection class="lg:col-span-2" icon="user" title="Osobné údaje">
            <form class="space-y-5" @submit.prevent="updateProfile">
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="name">Meno a priezvisko</label>
                        <input id="name" v-model="profileForm.name" type="text" required />
                        <InputError :message="profileForm.errors.name" />
                    </div>
                    <div>
                        <label for="email">E-mail</label>
                        <input id="email" v-model="profileForm.email" type="email" required />
                        <InputError :message="profileForm.errors.email" />
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button type="submit" class="btn-primary" :disabled="profileForm.processing">Uložiť zmeny</button>
                    <transition enter-active-class="transition duration-150" enter-from-class="opacity-0">
                        <span v-if="profileForm.recentlySuccessful" class="text-sm text-emerald-600">Uložené</span>
                    </transition>
                </div>
            </form>
        </CardSection>

        <CardSection icon="shield" tone="emerald" title="Prehľad">
            <dl class="space-y-3.5 text-sm">
                <div>
                    <dt class="text-slate-500">Posledné prihlásenie</dt>
                    <dd class="mt-0.5 font-medium text-slate-900">{{ user.last_login_at ?? 'teraz' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Operátorov celkom</dt>
                    <dd class="mt-0.5 font-medium text-slate-900">{{ operators.length }}</dd>
                </div>
            </dl>
        </CardSection>

        <CardSection
            class="lg:col-span-2"
            icon="key"
            tone="amber"
            title="Zmena hesla"
            description="Po zmene odhlásime všetky ostatné zariadenia."
        >
            <form class="space-y-5" @submit.prevent="updatePassword">
                <div>
                    <label for="current_password">Súčasné heslo</label>
                    <PasswordInput id="current_password" v-model="passwordForm.current_password" autocomplete="current-password" required />
                    <InputError :message="passwordForm.errors.current_password" />
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="new_password">Nové heslo</label>
                        <PasswordInput id="new_password" v-model="passwordForm.password" autocomplete="new-password" required />
                        <InputError :message="passwordForm.errors.password" />
                    </div>
                    <div>
                        <label for="new_password_confirmation">Nové heslo znova</label>
                        <PasswordInput id="new_password_confirmation" v-model="passwordForm.password_confirmation" autocomplete="new-password" required />
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button type="submit" class="btn-primary" :disabled="passwordForm.processing">Zmeniť heslo</button>
                    <transition enter-active-class="transition duration-150" enter-from-class="opacity-0">
                        <span v-if="passwordForm.recentlySuccessful" class="text-sm text-emerald-600">Heslo zmenené</span>
                    </transition>
                </div>
            </form>
        </CardSection>

        <CardSection icon="user" title="Operátori" description="Kto sa dostane do back-office.">
            <template #action>
                <button type="button" class="btn-secondary btn-sm" @click="addingOperator = !addingOperator">
                    {{ addingOperator ? 'Zavrieť' : 'Pridať' }}
                </button>
            </template>

            <ul class="divide-y divide-slate-100 text-sm">
                <li v-for="op in operators" :key="op.id" class="flex items-start justify-between gap-3 py-2.5">
                    <div class="min-w-0">
                        <p class="font-medium text-slate-900">
                            {{ op.name }}
                            <span v-if="op.is_me" class="ml-1 text-xs font-normal text-brand-600">(vy)</span>
                        </p>
                        <p class="truncate text-xs text-slate-500">{{ op.email }}</p>
                        <p class="text-xs text-slate-400">{{ op.last_login_at ? `prihlásený ${op.last_login_at}` : 'ešte sa neprihlásil' }}</p>
                    </div>
                    <button
                        v-if="!op.is_me"
                        type="button"
                        class="shrink-0 text-xs text-rose-600 hover:text-rose-700"
                        @click="removeOperator(op)"
                    >Odobrať</button>
                </li>
            </ul>

            <form v-if="addingOperator" class="mt-4 space-y-3 border-t border-slate-100 pt-4" @submit.prevent="addOperator">
                <div>
                    <label for="op_name">Meno</label>
                    <input id="op_name" v-model="operatorForm.name" type="text" required />
                    <InputError :message="operatorForm.errors.name" />
                </div>
                <div>
                    <label for="op_email">E-mail</label>
                    <input id="op_email" v-model="operatorForm.email" type="email" required />
                    <InputError :message="operatorForm.errors.email" />
                </div>
                <p class="text-xs text-slate-500">Na e-mail mu príde odkaz, cez ktorý si nastaví vlastné heslo.</p>
                <button type="submit" class="btn-primary btn-sm w-full" :disabled="operatorForm.processing">Vytvoriť operátora</button>
            </form>
        </CardSection>
    </div>
</template>
