<script setup>
/**
 * Login Page Component
 * Handles user authentication and locale switching using Inertia.js and Vue 3.
 */
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from '../components/ui/button/Button.vue';
import Input from '../components/ui/input/Input.vue';

// Access Inertia page props for global data like locale
const page = usePage();
const currentLocale = computed(() => page.props.locale);

// Localization setup
const { t, locale } = useI18n();

// Login form state management
const form = useForm({
    email: '',
    password: '',
    remember: false,
});

// Synchronize i18n locale with backend locale on mount + apply theme
onMounted(() => {
    locale.value = page.props.locale.code;
    const theme = localStorage.getItem('theme');
    document.documentElement.classList.toggle('dark', theme === 'dark');
});

// Separate form for locale switching to avoid polluting login data
const localeForm = useForm({
    locale: '',
});

/**
 * Handle login form submission
 */
const submit = () => {
    form.post(route('login.post'));
};

/**
 * Toggle between Arabic and English locales
 * Updates both the i18n client-side state and the backend session
 */
const changeLocale = () => {
    const target = currentLocale.value.code === 'ar' ? 'en' : 'ar';

    // Update client-side i18n
    locale.value = target;

    // Persist change through backend endpoint
    localeForm.locale = target;
    localeForm.post(route('locale.post'), {
        preserveScroll: true,
        preserveState: false, // Refresh state to apply new direction/translations
    });
};
</script>

<template>
    <!-- Dynamic page title with pre-fetched fonts -->
    <Head :title="t('sign_in')" />

    <!-- Main Layout Container - Handles bidirectional layout (RTL/LTR) -->
    <div
        :dir="page.props.locale.dir"
        class="flex min-h-screen items-center justify-center bg-background p-4 font-[Cairo]"
    >
        <div class="w-full max-w-md space-y-8">
            <div class="text-center">
                <h1 class="text-3xl font-bold tracking-tight text-foreground">
                    {{ t('welcome_back') }}
                </h1>
            </div>

            <!-- Login Form -->
            <form @submit.prevent="submit" class="space-y-6">
                <!-- Email Field -->
                <div class="space-y-2">
                    <label for="email" class="text-sm leading-none font-medium peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
                        {{ t('email') }}
                    </label>
                    <Input
                        id="email"
                        v-model="form.email"
                        type="email"
                        :placeholder="t('email_placeholder')"
                        :disabled="form.processing"
                        class="aria-invalid:border-destructive"
                        :aria-invalid="form.errors.email ? 'true' : undefined"
                    />
                    <p v-if="form.errors.email" class="text-sm text-destructive">
                        {{ form.errors.email }}
                    </p>
                </div>

                <!-- Password Field -->
                <div class="space-y-2">
                    <label for="password" class="text-sm leading-none font-medium peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
                        {{ t('password') }}
                    </label>
                    <Input
                        id="password"
                        v-model="form.password"
                        type="password"
                        :placeholder="t('password_placeholder')"
                        :disabled="form.processing"
                        class="aria-invalid:border-destructive"
                        :aria-invalid="form.errors.password ? 'true' : undefined"
                    />
                    <p v-if="form.errors.password" class="text-sm text-destructive">
                        {{ form.errors.password }}
                    </p>
                </div>

                <!-- Submit Button -->
                <Button type="submit" class="w-full" :disabled="form.processing">
                    {{ form.processing ? t('signing_in') : t('sign_in') }}
                </Button>
            </form>
        </div>

        <!-- Floating Locale Switcher for quick language access -->
        <div class="fixed end-6 bottom-6 z-50">
            <Button @click="changeLocale">
                {{ currentLocale.code === 'ar' ? 'English' : 'عربي' }}
            </Button>
        </div>
    </div>
</template>
