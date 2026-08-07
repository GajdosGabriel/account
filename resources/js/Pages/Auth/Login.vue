<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '../../Layouts/AuthLayout.vue';
import InputError from '../../Components/InputError.vue';

const form = useForm({ email: '', password: '', remember: false });

const submit = () => form.post('/login', { onFinish: () => form.reset('password') });
</script>

<template>
    <Head title="Prihlásenie" />

    <AuthLayout title="Vitajte späť" subtitle="Prihlásenie do back-office.">
        <form class="space-y-5" @submit.prevent="submit">
            <div>
                <label for="email">E-mail</label>
                <input id="email" v-model="form.email" type="email" autocomplete="username" placeholder="vas@email.sk" required autofocus />
                <InputError :message="form.errors.email" />
            </div>

            <div>
                <div class="mb-1.5 flex items-baseline justify-between">
                    <label for="password" class="mb-0">Heslo</label>
                    <Link href="/forgot-password" class="text-sm text-brand-700 hover:underline">Zabudli ste?</Link>
                </div>
                <input id="password" v-model="form.password" type="password" autocomplete="current-password" placeholder="••••••••" required />
                <InputError :message="form.errors.password" />
            </div>

            <label class="flex items-center gap-2.5 text-sm font-normal text-slate-600">
                <input v-model="form.remember" type="checkbox" />
                Zostať prihlásený
            </label>

            <button type="submit" class="btn-primary w-full" :disabled="form.processing">
                {{ form.processing ? 'Prihlasujem…' : 'Prihlásiť sa' }}
            </button>
        </form>

        <p class="mt-8 text-center text-sm text-slate-500">
            Nemáte účet?
            <Link href="/register" class="link">Zaregistrujte sa</Link>
        </p>
    </AuthLayout>
</template>
