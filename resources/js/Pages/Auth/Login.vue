<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import Message from 'primevue/message';
import GuestLayout from '@/Layouts/GuestLayout.vue';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Iniciar sesión" />

    <GuestLayout title="Iniciar sesión" subtitle="Nos alegra verte de nuevo.">
        <form class="flex flex-col gap-5" @submit.prevent="submit">
            <div class="flex flex-col gap-2">
                <label for="email" class="text-sm font-medium text-surface-700">Correo electrónico</label>
                <InputText id="email" v-model="form.email" type="email" autocomplete="email" :invalid="!!form.errors.email" fluid />
                <Message v-if="form.errors.email" severity="error" size="small" variant="simple">{{ form.errors.email }}</Message>
            </div>

            <div class="flex flex-col gap-2">
                <div class="flex items-center justify-between">
                    <label for="password" class="text-sm font-medium text-surface-700">Contraseña</label>
                    <a href="#" class="text-xs text-emerald-600 hover:underline">¿Olvidaste tu contraseña?</a>
                </div>
                <Password input-id="password" v-model="form.password" autocomplete="current-password" :feedback="false" :invalid="!!form.errors.password" toggle-mask fluid />
                <Message v-if="form.errors.password" severity="error" size="small" variant="simple">{{ form.errors.password }}</Message>
            </div>

            <div class="flex items-center gap-2">
                <Checkbox v-model="form.remember" input-id="remember" binary />
                <label for="remember" class="text-sm text-surface-600">Recordarme</label>
            </div>

            <Button type="submit" label="Iniciar sesión" :loading="form.processing" fluid />

            <p class="text-center text-sm text-surface-500">
                ¿Aún no tienes cuenta?
                <Link href="/register" class="font-medium text-emerald-600 hover:underline">Crear cuenta</Link>
            </p>
        </form>
    </GuestLayout>
</template>
