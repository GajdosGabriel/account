<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthLayout from '../../Layouts/AuthLayout.vue';
import InputError from '../../Components/InputError.vue';
import PasswordInput from '../../Components/PasswordInput.vue';

const props = defineProps({
    token: { type: String, required: true },
    email: { type: String, default: '' },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => form.post('/reset-password', {
    onFinish: () => form.reset('password', 'password_confirmation'),
});
</script>

<template>
    <Head title="Nové heslo" />

    <AuthLayout title="Nové heslo" subtitle="Zvoľte si heslo, ktoré inde nepoužívate.">
        <form class="space-y-5" @submit.prevent="submit">
            <div>
                <label for="email">E-mail</label>
                <input id="email" v-model="form.email" type="email" autocomplete="username" required />
                <InputError :message="form.errors.email" />
            </div>

            <div>
                <label for="password">Nové heslo</label>
                <PasswordInput id="password" v-model="form.password" autocomplete="new-password" required autofocus />
                <InputError :message="form.errors.password" />
            </div>

            <div>
                <label for="password_confirmation">Nové heslo znova</label>
                <PasswordInput id="password_confirmation" v-model="form.password_confirmation" autocomplete="new-password" required />
            </div>

            <button type="submit" class="btn-primary w-full" :disabled="form.processing">
                {{ form.processing ? 'Ukladám…' : 'Zmeniť heslo' }}
            </button>
        </form>
    </AuthLayout>
</template>
