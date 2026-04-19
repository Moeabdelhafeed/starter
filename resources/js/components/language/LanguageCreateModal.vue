<script setup>
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ref, computed } from 'vue';
import { XIcon, Loader2, AlertCircle } from 'lucide-vue-next';
import Input from '@/components/ui/input/Input.vue';
import Button from '@/components/ui/button/Button.vue';
import Checkbox from '@/components/ui/checkbox/Checkbox.vue';

const { t } = useI18n();

const props = defineProps({
    isOpen: Boolean,
    availableLocales: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['close']);

const searchQuery = ref('');
const selectedLocaleCode = ref('');

const filteredLocales = computed(() => {
    if (!searchQuery.value) {
        return props.availableLocales;
    }
    const query = searchQuery.value.toLowerCase();
    return props.availableLocales.filter(
        (locale) =>
            locale.code.toLowerCase().includes(query) ||
            locale.name.toLowerCase().includes(query) ||
            locale.native_name.toLowerCase().includes(query)
    );
});

const form = useForm({
    code: '',
    name: '',
    native_name: '',
    direction: 'ltr',
    image: null,
    is_active: true,
    is_default: false,
});

const selectLocale = (locale) => {
    selectedLocaleCode.value = locale.code;
    form.code = locale.code;
    form.name = locale.name;
    form.native_name = locale.native_name;
    form.direction = locale.direction;
    searchQuery.value = '';
};

const close = () => {
    emit('close');
    setTimeout(() => {
        form.reset();
        form.clearErrors();
        selectedLocaleCode.value = '';
        searchQuery.value = '';
    }, 200);
};

const handleImageChange = (e) => {
    form.image = e.target.files[0] || null;
};

const submit = () => {
    form.post(route('languages.store'), {
        preserveScroll: true,
        preserveState: true,
        reset: ['languages', 'availableLocales', 'success', 'error', 'filters'],
        forceFormData: true,
        onSuccess: () => {
            close();
        },
    });
};
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="isOpen" class="fixed inset-0 z-50 overflow-y-auto" @click.self="close">
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>

                <div class="relative flex min-h-full items-center justify-center p-4">
                    <div class="relative w-full max-w-lg transform overflow-hidden rounded-2xl bg-card p-6 shadow-xl transition-all text-start">
                        <div class="mb-6 flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-foreground">
                                {{ t('create_language') }}
                            </h3>
                            <button
                                @click="close"
                                class="rounded-full p-1 text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
                            >
                                <XIcon class="h-5 w-5" />
                            </button>
                        </div>

                        <div v-if="Object.keys(form.errors).length > 0" class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4">
                            <div class="mb-2 flex items-center gap-2">
                                <AlertCircle class="h-4 w-4 shrink-0 text-red-500" />
                                <p class="text-sm font-semibold text-red-700">{{ t('please_fix_errors') }}</p>
                            </div>
                            <ul class="space-y-1 ps-6">
                                <li v-for="(error, key) in form.errors" :key="key" class="text-sm text-red-600">
                                    {{ error }}
                                </li>
                            </ul>
                        </div>

                        <form @submit.prevent="submit" class="space-y-5">
                            <!-- Language Selector -->
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">
                                    {{ t('select_language') }} <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <Input
                                        v-model="searchQuery"
                                        type="text"
                                        :placeholder="selectedLocaleCode ? '' : t('search_language')"
                                        class="w-full"
                                    />
                                    <div v-if="selectedLocaleCode && !searchQuery" class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3">
                                        <span class="text-foreground">{{ form.name }} ({{ form.native_name }})</span>
                                    </div>
                                </div>
                                <div v-if="searchQuery || !selectedLocaleCode" class="max-h-48 overflow-y-auto rounded-lg border bg-card">
                                    <div
                                        v-for="locale in filteredLocales"
                                        :key="locale.code"
                                        @click="selectLocale(locale)"
                                        class="flex cursor-pointer items-center justify-between px-3 py-2 hover:bg-accent"
                                    >
                                        <div class="flex flex-col">
                                            <span class="text-sm font-medium text-foreground">{{ locale.name }}</span>
                                            <span class="text-xs text-muted-foreground">{{ locale.native_name }} ({{ locale.code }})</span>
                                        </div>
                                        <span class="text-xs text-muted-foreground">{{ locale.direction.toUpperCase() }}</span>
                                    </div>
                                    <div v-if="filteredLocales.length === 0" class="px-3 py-4 text-center text-sm text-muted-foreground">
                                        {{ t('no_languages_found') }}
                                    </div>
                                </div>
                            </div>

                            <!-- Selected Language Info (readonly) -->
                            <div v-if="selectedLocaleCode" class="grid grid-cols-2 gap-4 rounded-lg border bg-muted/50 p-4">
                                <div>
                                    <span class="text-xs text-muted-foreground">{{ t('language_code') }}</span>
                                    <p class="font-medium text-foreground">{{ form.code }}</p>
                                </div>
                                <div>
                                    <span class="text-xs text-muted-foreground">{{ t('direction') }}</span>
                                    <p class="font-medium text-foreground">{{ form.direction.toUpperCase() }}</p>
                                </div>
                                <div>
                                    <span class="text-xs text-muted-foreground">{{ t('language_name') }}</span>
                                    <p class="font-medium text-foreground">{{ form.name }}</p>
                                </div>
                                <div>
                                    <span class="text-xs text-muted-foreground">{{ t('native_name') }}</span>
                                    <p class="font-medium text-foreground">{{ form.native_name }}</p>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">
                                    {{ t('image') }}
                                </label>
                                <input
                                    type="file"
                                    accept="image/*"
                                    @change="handleImageChange"
                                    class="block w-full text-sm text-muted-foreground file:me-4 file:rounded-full file:border-0 file:bg-primary/10 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-primary hover:file:bg-primary/20"
                                />
                            </div>

                            <div class="flex items-center gap-6">
                                <label class="flex items-center gap-2 text-sm text-foreground">
                                    <Checkbox v-model="form.is_active" />
                                    {{ t('active') }}
                                </label>
                                <label class="flex items-center gap-2 text-sm text-foreground">
                                    <Checkbox v-model="form.is_default" />
                                    {{ t('is_default') }}
                                </label>
                            </div>

                            <div class="flex gap-3 pt-4">
                                <Button type="button" variant="outline" @click="close" class="flex-1">
                                    {{ t('cancel') }}
                                </Button>
                                <Button type="submit" :disabled="form.processing" class="flex-1">
                                    <Loader2 v-if="form.processing" class="me-2 h-4 w-4 animate-spin" />
                                    {{ form.processing ? t('creating') : t('create') }}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
