<script setup>
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { XIcon, Loader2, AlertCircle } from 'lucide-vue-next';
import Input from '@/components/ui/input/Input.vue';
import Button from '@/components/ui/button/Button.vue';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import Checkbox from '@/components/ui/checkbox/Checkbox.vue';

const { t } = useI18n();

const props = defineProps({
    isOpen: Boolean,
    language: Object,
});

const emit = defineEmits(['close']);

const form = useForm({
    code: '',
    name: '',
    native_name: '',
    direction: 'ltr',
    image: null,
    is_active: true,
    is_default: false,
    _method: 'PUT',
});

watch(
    () => props.language,
    (newLang) => {
        if (newLang) {
            form.code = newLang.code;
            form.name = newLang.name;
            form.native_name = newLang.native_name;
            form.direction = newLang.direction;
            form.is_active = newLang.is_active;
            form.is_default = newLang.is_default;
            form.image = null;
        }
    },
    { immediate: true },
);

const close = () => {
    emit('close');
    setTimeout(() => {
        form.clearErrors();
    }, 200);
};

const handleImageChange = (e) => {
    form.image = e.target.files[0] || null;
};

const submit = () => {
    if (!props.language) return;

    form.post(route('languages.update', props.language.id), {
        preserveScroll: true,
        preserveState: true,
        reset: ['languages', 'success', 'error', 'filters'],
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
                                {{ t('edit_language') }}
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
                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-foreground">
                                        {{ t('language_code') }}
                                    </label>
                                    <div class="flex h-10 items-center rounded-lg border bg-muted/50 px-3 text-sm text-muted-foreground">
                                        {{ form.code }}
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-foreground">
                                        {{ t('direction') }} <span class="text-red-500">*</span>
                                    </label>
                                    <Select v-model="form.direction">
                                        <SelectTrigger>
                                            <SelectValue :placeholder="t('direction')" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="ltr">LTR</SelectItem>
                                            <SelectItem value="rtl">RTL</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">
                                    {{ t('language_name') }} <span class="text-red-500">*</span>
                                </label>
                                <Input v-model="form.name" type="text" placeholder="English, Arabic, French..." />
                            </div>

                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">
                                    {{ t('native_name') }} <span class="text-red-500">*</span>
                                </label>
                                <Input v-model="form.native_name" type="text" placeholder="English, العربية, Français..." />
                            </div>

                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">
                                    {{ t('image') }}
                                </label>
                                <div v-if="language?.image" class="mb-2">
                                    <img :src="language.image.image_api" :alt="language.name" class="h-8 w-12 rounded object-cover" />
                                </div>
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
                                    {{ form.processing ? t('saving') : t('save_changes') }}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
