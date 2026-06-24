<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import Button from 'primevue/button';
import Message from 'primevue/message';
import GuestLayout from '@/Layouts/GuestLayout.vue';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post('/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Crear cuenta" />

    <GuestLayout title="Crear cuenta" subtitle="14 días de prueba gratis, sin tarjeta de crédito.">
        <form class="flex flex-col gap-5" @submit.prevent="submit">
            <div class="flex flex-col gap-2">
                <label for="name" class="text-sm font-medium text-surface-700">Nombre</label>
                <InputText id="name" v-model="form.name" autocomplete="name" :invalid="!!form.errors.name" fluid />
                <Message v-if="form.errors.name" severity="error" size="small" variant="simple">{{ form.errors.name }}</Message>
            </div>

            <div class="flex flex-col gap-2">
                <label for="email" class="text-sm font-medium text-surface-700">Correo electrónico</label>
                <InputText id="email" v-model="form.email" type="email" autocomplete="email" :invalid="!!form.errors.email" fluid />
                <Message v-if="form.errors.email" severity="error" size="small" variant="simple">{{ form.errors.email }}</Message>
            </div>

            <div class="flex flex-col gap-2">
                <label for="password" class="text-sm font-medium text-surface-700">Contraseña</label>
                <Password input-id="password" v-model="form.password" autocomplete="new-password" :invalid="!!form.errors.password" toggle-mask fluid />
                <Message v-if="form.errors.password" severity="error" size="small" variant="simple">{{ form.errors.password }}</Message>
            </div>

            <div class="flex flex-col gap-2">
                <label for="password_confirmation" class="text-sm font-medium text-surface-700">Confirmar contraseña</label>
                <Password input-id="password_confirmation" v-model="form.password_confirmation" autocomplete="new-password" :feedback="false" toggle-mask fluid />
            </div>

            <Button type="submit" label="Crear mi cuenta" :loading="form.processing" fluid />

            <p class="text-center text-sm text-surface-500">
                ¿Ya tienes cuenta?
                <Link href="/login" class="font-medium text-emerald-600 hover:underline">Iniciar sesión</Link>
            </p>
        </form>
    </GuestLayout>
</template>
