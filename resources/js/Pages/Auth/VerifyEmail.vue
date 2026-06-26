<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Message from 'primevue/message';
import GuestLayout from '@/Layouts/GuestLayout.vue';

const props = defineProps({
    status: { type: String, default: null },
});

const page = usePage();
const sending = ref(false);
const email = computed(() => page.props.auth?.user?.email);

const resend = () => {
    sending.value = true;
    router.post('/email/verification-notification', {}, {
        preserveScroll: true,
        onFinish: () => (sending.value = false),
    });
};

const logout = () => router.post('/logout');
</script>

<template>
    <Head title="Verifica tu correo" />

    <GuestLayout>
        <div class="mx-auto max-w-md space-y-5 p-6">
            <div class="text-center">
                <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                    <i class="pi pi-envelope text-xl" />
                </div>
                <h1 class="text-xl font-bold text-surface-900">Verifica tu correo</h1>
                <p class="mt-2 text-sm text-surface-500">
                    Te hemos enviado un enlace de verificación a
                    <span v-if="email" class="font-medium text-surface-700">{{ email }}</span>.
                    Haz clic en el enlace para activar tu cuenta.
                </p>
            </div>

            <Message v-if="status === 'verification-link-sent'" severity="success" :closable="false">
                Te hemos reenviado un nuevo enlace de verificación.
            </Message>

            <div class="flex flex-col gap-2">
                <Button label="Reenviar el enlace" icon="pi pi-refresh" :loading="sending" @click="resend" />
                <button
                    type="button"
                    class="text-sm text-surface-500 underline-offset-2 hover:underline"
                    @click="logout"
                >
                    Cerrar sesión
                </button>
            </div>

            <p class="text-center text-xs text-surface-400">
                ¿No has recibido nada? Revisa tu carpeta de spam o reenvía el enlace.
            </p>
        </div>
    </GuestLayout>
</template>
