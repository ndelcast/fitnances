<script setup>
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import Avatar from 'primevue/avatar';
import Menu from 'primevue/menu';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import OnboardingWizard from '@/Components/OnboardingWizard.vue';

defineProps({
    title: { type: String, default: '' },
    fluid: { type: Boolean, default: false },
});

const page = usePage();
const user = computed(() => page.props.auth?.user ?? { name: 'Usuario' });

const nav = [
    { label: 'Resumen', icon: 'pi pi-chart-line', href: '/dashboard', match: ['/dashboard'] },
    { label: 'Flujo de caja', icon: 'pi pi-table', href: '/cash-flow', match: ['/cash-flow'] },
    { label: 'Movimientos', icon: 'pi pi-arrow-right-arrow-left', href: '/movements', match: ['/movements'] },
    { label: 'Perfil fiscal', icon: 'pi pi-id-card', href: '/fiscal-profile', match: ['/fiscal-profile'] },
];

const secondaryNav = [
    { label: 'Asistente anual', icon: 'pi pi-sparkles', href: '/onboarding', match: ['/onboarding'] },
];

const currentPath = computed(() => page.url.split('?')[0]);

const isActive = (item) => item.match.some((m) => currentPath.value === m || currentPath.value.startsWith(m + '/'));

const initials = computed(() => {
    return (user.value.name || 'U')
        .split(' ')
        .map((p) => p[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
});

const userMenu = ref();
const userMenuItems = [
    {
        label: 'Mi cuenta',
        icon: 'pi pi-user',
        command: () => router.visit('/fiscal-profile'),
    },
    {
        separator: true,
    },
    {
        label: 'Cerrar sesión',
        icon: 'pi pi-sign-out',
        command: () => router.post('/logout'),
    },
];

const toggleUserMenu = (event) => userMenu.value.toggle(event);

const mobileOpen = ref(false);

// Onboarding modal : s'auto-ouvre tant que le profil n'a pas été initialisé,
// sauf sur la page onboarding elle-même (qui a déjà le wizard inline).
const needsOnboarding = computed(() => page.props.auth?.needsOnboarding === true);
const onOnboardingPage = computed(() => currentPath.value === '/onboarding');
const onboardingOpen = computed({
    get: () => needsOnboarding.value && !onOnboardingPage.value,
    set: () => {},
});

// Banner email non vérifié (soft block : l'app reste utilisable).
const emailVerified = computed(() => page.props.auth?.user?.email_verified !== false);
const resendingVerification = ref(false);
const resendVerification = () => {
    resendingVerification.value = true;
    router.post('/email/verification-notification', {}, {
        preserveScroll: true,
        onFinish: () => (resendingVerification.value = false),
    });
};
</script>

<template>
    <div class="min-h-screen bg-surface-50">
        <!-- Sidebar (always collapsed, icons + tooltips) -->
        <aside
            class="fixed inset-y-0 left-0 z-30 flex w-16 flex-col items-center border-r border-surface-200 bg-white transition-transform"
            :class="mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
        >
            <div class="flex h-16 w-full items-center justify-center border-b border-surface-200">
                <div
                    v-tooltip.right="'Fitnances'"
                    class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-600 text-white"
                >
                    <i class="pi pi-wallet text-sm" />
                </div>
            </div>

            <nav class="flex w-full flex-1 flex-col items-center gap-1 overflow-y-auto py-4">
                <Link
                    v-for="item in nav"
                    :key="item.href"
                    v-tooltip.right="item.label"
                    :href="item.href"
                    class="flex h-10 w-10 items-center justify-center rounded-lg transition-colors"
                    :class="
                        isActive(item)
                            ? 'bg-emerald-50 text-emerald-700'
                            : 'text-surface-500 hover:bg-surface-100 hover:text-surface-900'
                    "
                    @click="mobileOpen = false"
                >
                    <i :class="item.icon" class="text-base" />
                </Link>

                <div class="my-2 h-px w-8 bg-surface-200" />

                <Link
                    v-for="item in secondaryNav"
                    :key="item.href"
                    v-tooltip.right="item.label"
                    :href="item.href"
                    class="flex h-10 w-10 items-center justify-center rounded-lg transition-colors"
                    :class="
                        isActive(item)
                            ? 'bg-emerald-50 text-emerald-700'
                            : 'text-surface-500 hover:bg-surface-100 hover:text-surface-900'
                    "
                    @click="mobileOpen = false"
                >
                    <i :class="item.icon" class="text-base" />
                </Link>
            </nav>

            <div class="w-full border-t border-surface-200 py-3">
                <div
                    v-tooltip.right="'Prueba gratuita · 14 días restantes'"
                    class="mx-auto flex h-9 w-9 items-center justify-center rounded-lg bg-amber-50 text-amber-600"
                >
                    <i class="pi pi-clock text-sm" />
                </div>
            </div>
        </aside>

        <!-- Backdrop mobile -->
        <div
            v-if="mobileOpen"
            class="fixed inset-0 z-20 bg-black/40 lg:hidden"
            @click="mobileOpen = false"
        />

        <!-- Content area -->
        <div class="lg:pl-16">
            <header class="sticky top-0 z-10 flex h-16 items-center justify-between border-b border-surface-200 bg-white/80 px-4 backdrop-blur md:px-8">
                <div class="flex items-center gap-3">
                    <Button
                        icon="pi pi-bars"
                        text
                        severity="secondary"
                        class="lg:hidden"
                        @click="mobileOpen = !mobileOpen"
                    />
                    <h1 v-if="title" class="text-base font-semibold text-surface-900 md:text-lg">{{ title }}</h1>
                </div>

                <button
                    type="button"
                    class="flex items-center gap-2 rounded-full p-1 transition-colors hover:bg-surface-100"
                    @click="toggleUserMenu"
                >
                    <Avatar :label="initials" shape="circle" class="!bg-emerald-100 !text-emerald-700" size="normal" />
                    <span class="hidden text-sm font-medium text-surface-700 md:inline">{{ user.name }}</span>
                    <i class="pi pi-chevron-down hidden text-xs text-surface-500 md:inline" />
                </button>
                <Menu ref="userMenu" :model="userMenuItems" :popup="true" />
            </header>

            <div
                v-if="!emailVerified"
                class="flex flex-col gap-2 border-b border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-800 md:flex-row md:items-center md:justify-between md:px-8"
            >
                <span class="flex items-center gap-2">
                    <i class="pi pi-exclamation-triangle text-amber-600" />
                    Verifica tu correo electrónico para asegurar tu cuenta.
                </span>
                <button
                    type="button"
                    class="text-sm font-semibold text-amber-900 underline-offset-2 hover:underline disabled:opacity-50"
                    :disabled="resendingVerification"
                    @click="resendVerification"
                >
                    {{ resendingVerification ? 'Enviando...' : 'Reenviar el enlace' }}
                </button>
            </div>

            <main :class="fluid ? '' : 'px-4 py-6 md:px-8 md:py-8'">
                <slot />
            </main>
        </div>

        <!-- Asistente anual : auto-modal tant que le profil n'a pas été initialisé -->
        <Dialog
            :visible="onboardingOpen"
            modal
            :closable="false"
            :closeOnEscape="false"
            :dismissableMask="false"
            :draggable="false"
            :style="{ width: '640px' }"
            :pt="{ root: { class: '!rounded-2xl !overflow-hidden' } }"
        >
            <template #header>
                <div class="flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700">
                        <i class="pi pi-sparkles" />
                    </span>
                    <div>
                        <h2 class="text-base font-semibold text-surface-900">Bienvenido a Fitnances</h2>
                        <p class="text-xs text-surface-500">Configura tu año en menos de 2 minutos.</p>
                    </div>
                </div>
            </template>

            <OnboardingWizard />
        </Dialog>
    </div>
</template>
