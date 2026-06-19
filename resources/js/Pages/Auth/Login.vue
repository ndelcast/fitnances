<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import Card from 'primevue/card';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import Message from 'primevue/message';

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
    <Head title="Connexion" />

    <main class="flex min-h-full items-center justify-center bg-surface-50 px-4 py-12">
        <Card class="w-full max-w-md">
            <template #title>Connexion</template>
            <template #subtitle>Ravi de vous revoir.</template>
            <template #content>
                <form class="flex flex-col gap-5" @submit.prevent="submit">
                    <div class="flex flex-col gap-2">
                        <label for="email" class="text-surface-700 text-sm font-medium">Email</label>
                        <InputText id="email" v-model="form.email" type="email" autocomplete="email" :invalid="!!form.errors.email" fluid />
                        <Message v-if="form.errors.email" severity="error" size="small" variant="simple">{{ form.errors.email }}</Message>
                    </div>

                    <div class="flex flex-col gap-2">
                        <label for="password" class="text-surface-700 text-sm font-medium">Mot de passe</label>
                        <Password input-id="password" v-model="form.password" autocomplete="current-password" :feedback="false" :invalid="!!form.errors.password" toggle-mask fluid />
                        <Message v-if="form.errors.password" severity="error" size="small" variant="simple">{{ form.errors.password }}</Message>
                    </div>

                    <div class="flex items-center gap-2">
                        <Checkbox v-model="form.remember" input-id="remember" binary />
                        <label for="remember" class="text-surface-600 text-sm">Se souvenir de moi</label>
                    </div>

                    <Button type="submit" label="Se connecter" :loading="form.processing" fluid />

                    <p class="text-surface-500 text-center text-sm">
                        Pas encore de compte ?
                        <Link href="/register" class="font-medium text-emerald-600 hover:underline">Créer un compte</Link>
                    </p>
                </form>
            </template>
        </Card>
    </main>
</template>
