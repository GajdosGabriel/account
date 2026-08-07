<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '../../Layouts/AuthLayout.vue';
import InputError from '../../Components/InputError.vue';

const form = useForm({ email: '' });

const submit = () => form.post('/forgot-password');
</script>

<template>
    <Head title="Zabudnuté heslo" />

    <AuthLayout title="Obnova hesla" subtitle="Pošleme vám odkaz na nastavenie nového hesla.">
        <form class="space-y-5" @submit.prevent="submit">
            <div>
                <label for="email">E-mail</label>
                <input id="email" v-model="form.email" type="email" autocomplete="username" placeholder="vas@email.sk" required autofocus />
                <InputError :message="form.errors.email" />
            </div>

            <button type="submit" class="btn-primary w-full" :disabled="form.processing">
                {{ form.processing ? 'Posielam…' : 'Poslať odkaz' }}
            </button>
        </form>

        <p class="mt-8 text-center text-sm text-slate-500">
            <Link href="/login" class="link">Späť na prihlásenie</Link>
        </p>
    </AuthLayout>
</template>
