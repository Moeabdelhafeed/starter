<script setup>
import { usePage, router } from '@inertiajs/vue3';
import { onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Navbar from '../components/Shared/Navbar.vue';
import { AlertCircle, CheckCircle, X } from 'lucide-vue-next';

const page = usePage();
const { locale } = useI18n();

const showSuccess = ref(false);
const showError = ref(false);

onMounted(() => {
    document.body.classList.add('font-[Cairo]');
    document.body.classList.add('text-scondary');
    document.body.setAttribute('dir', page.props.locale.dir);

    // Apply saved theme
    const theme = localStorage.getItem('theme');
    document.documentElement.classList.toggle('dark', theme === 'dark');

    router.on('start', () => {
        showSuccess.value = false;
        showError.value = false;
        page.props.success = null;
        page.props.error = null;
    });
});

let successTimeout;
let errorTimeout;

watch(
    () => page.props.success,
    (newVal) => {
        if (newVal) {
            showSuccess.value = true;
            clearTimeout(successTimeout);
            successTimeout = setTimeout(() => {
                showSuccess.value = false;
                page.props.success = null;
            }, 3000);
        }
    },
    { immediate: true },
);

watch(
    () => page.props.error,
    (newVal) => {
        if (newVal) {
            showError.value = true;
            clearTimeout(errorTimeout);
            errorTimeout = setTimeout(() => {
                showError.value = false;
                page.props.error = null;
            }, 3000);
        }
    },
    { immediate: true },
);

onMounted(() => {
    locale.value = page.props.locale.code;
});
</script>

<template>
    <div id="main_div" class="text-scondary font-[Cairo]" :dir="page.props.locale.dir">
        <Navbar v-if="page.props.auth.user" />

        <!-- Success Toast -->
        <Transition
            enter-active-class="transition ease-out duration-300"
            enter-from-class="transform opacity-0 translate-y-2 scale-95"
            enter-to-class="transform opacity-100 translate-y-0 scale-100"
            leave-active-class="transition ease-in duration-200"
            leave-from-class="transform opacity-100 translate-y-0 scale-100"
            leave-to-class="transform opacity-0 translate-y-2 scale-95"
        >
            <div
                v-if="showSuccess && page.props.success"
                class="fixed end-6 top-6 z-50 flex min-w-[300px] items-center justify-between rounded-lg bg-emerald-600 px-4 py-3 text-white"
                role="alert"
            >
                <div class="flex items-center gap-3">
                    <CheckCircle class="size-5 shrink-0" />
                    <p class="text-sm font-medium">{{ page.props.success }}</p>
                </div>
                <button @click="showSuccess = false" class="rounded-full p-1 text-white/80 transition-colors hover:bg-white/10 hover:text-white">
                    <X class="size-4" />
                </button>
            </div>
        </Transition>

        <!-- Error Toast -->
        <Transition
            enter-active-class="transition ease-out duration-300"
            enter-from-class="transform opacity-0 translate-y-2 scale-95"
            enter-to-class="transform opacity-100 translate-y-0 scale-100"
            leave-active-class="transition ease-in duration-200"
            leave-from-class="transform opacity-100 translate-y-0 scale-100"
            leave-to-class="transform opacity-0 translate-y-2 scale-95"
        >
            <div
                v-if="showError && page.props.error"
                class="fixed end-6 top-6 z-50 flex min-w-[300px] items-center justify-between rounded-lg bg-red-600 px-4 py-3 text-white"
                role="alert"
            >
                <div class="flex items-center gap-3">
                    <AlertCircle class="size-5 shrink-0" />
                    <p class="text-sm font-medium">{{ page.props.error }}</p>
                </div>
                <button @click="showError = false" class="rounded-full p-1 text-white/80 transition-colors hover:bg-white/10 hover:text-white">
                    <X class="size-4" />
                </button>
            </div>
        </Transition>

        <slot />
    </div>
</template>
