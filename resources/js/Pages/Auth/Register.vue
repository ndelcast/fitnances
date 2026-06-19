<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import Card from 'primevue/card';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import Button from 'primevue/button';
import Message from 'primevue/message';

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
    <Head title="Créer un compte" />

    <main class="flex min-h-full items-center justify-center bg-surface-50 px-4 py-12">
        <Card class="w-full max-w-md">
            <template #title>Créer votre compte</template>
            <template #subtitle>14 jours d'essai gratuit, sans carte bancaire.</template>
            <template #content>
                <form class="flex flex-col gap-5" @submit.prevent="submit">
                    <div class="flex flex-col gap-2">
                        <label for="name" class="text-surface-700 text-sm font-medium">Nom</label>
                        <InputText id="name" v-model="form.name" autocomplete="name" :invalid="!!form.errors.name" fluid />
                        <Message v-if="form.errors.name" severity="error" size="small" variant="simple">{{ form.errors.name }}</Message>
                    </div>

                    <div class="flex flex-col gap-2">
                        <label for="email" class="text-surface-700 text-sm font-medium">Email</label>
                        <InputText id="email" v-model="form.email" type="email" autocomplete="email" :invalid="!!form.errors.email" fluid />
                        <Message v-if="form.errors.email" severity="error" size="small" variant="simple">{{ form.errors.email }}</Message>
                    </div>

                    <div class="flex flex-col gap-2">
                        <label for="password" class="text-surface-700 text-sm font-medium">Mot de passe</label>
                        <Password input-id="password" v-model="form.password" autocomplete="new-password" :invalid="!!form.errors.password" toggle-mask fluid />
                        <Message v-if="form.errors.password" severity="error" size="small" variant="simple">{{ form.errors.password }}</Message>
                    </div>

                    <div class="flex flex-col gap-2">
                        <label for="password_confirmation" class="text-surface-700 text-sm font-medium">Confirmer le mot de passe</label>
                        <Password input-id="password_confirmation" v-model="form.password_confirmation" autocomplete="new-password" :feedback="false" toggle-mask fluid />
                    </div>

                    <Button type="submit" label="Créer mon compte" :loading="form.processing" fluid />

                    <p class="text-surface-500 text-center text-sm">
                        Déjà un compte ?
                        <Link href="/login" class="font-medium text-emerald-600 hover:underline">Se connecter</Link>
                    </p>
                </form>
            </template>
        </Card>
    </main>
</template>
