<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import CardSection from '../../Components/CardSection.vue';
import InputError from '../../Components/InputError.vue';
import PageHeader from '../../Components/PageHeader.vue';

const props = defineProps({
    user: { type: Object, required: true },
    operators: { type: Array, default: () => [] },
});

const profileForm = useForm({ name: props.user.name, email: props.user.email });

const passwordForm = useForm({
    current_password: '', password: '', password_confirmation: '',
});

const operatorForm = useForm({ name: '', email: '', password: '' });
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
                    <input id="current_password" v-model="passwordForm.current_password" type="password" autocomplete="current-password" placeholder="••••••••" required />
                    <InputError :message="passwordForm.errors.current_password" />
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="new_password">Nové heslo</label>
                        <input id="new_password" v-model="passwordForm.password" type="password" autocomplete="new-password" placeholder="••••••••" required />
                        <InputError :message="passwordForm.errors.password" />
                    </div>
                    <div>
                        <label for="new_password_confirmation">Nové heslo znova</label>
                        <input id="new_password_confirmation" v-model="passwordForm.password_confirmation" type="password" autocomplete="new-password" placeholder="••••••••" required />
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
                <li v-for="op in operators" :key="op.email" class="py-2.5">
                    <p class="font-medium text-slate-900">
                        {{ op.name }}
                        <span v-if="op.is_me" class="ml-1 text-xs font-normal text-brand-600">(vy)</span>
                    </p>
                    <p class="text-xs text-slate-500">{{ op.email }}</p>
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
                <div>
                    <label for="op_password">Dočasné heslo</label>
                    <input id="op_password" v-model="operatorForm.password" type="text" required />
                    <InputError :message="operatorForm.errors.password" />
                </div>
                <button type="submit" class="btn-primary btn-sm w-full" :disabled="operatorForm.processing">Vytvoriť operátora</button>
            </form>
        </CardSection>
    </div>
</template>
