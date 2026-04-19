<script setup>
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { computed } from 'vue';
import { XIcon, Loader2, AlertCircle, ImagePlus } from 'lucide-vue-next';
import Input from '@/components/ui/input/Input.vue';
import Button from '@/components/ui/button/Button.vue';
import Checkbox from '@/components/ui/checkbox/Checkbox.vue';
import TranslatableInput from '@/components/ui/translatable-input/TranslatableInput.vue';

const { t } = useI18n();

const props = defineProps({
    isOpen: Boolean,
    languages: Array,
});

const emit = defineEmits(['close']);

const form = useForm({
    slug: '',
    is_active: true,
    image: null,
    translations: {
        name: {},
        content: {},
    },
});

const imagePreview = computed(() => {
    if (form.image) {
        return URL.createObjectURL(form.image);
    }
    return null;
});

const handleImageChange = (e) => {
    form.image = e.target.files[0] || null;
};

const close = () => {
    emit('close');
    setTimeout(() => {
        form.reset();
        form.clearErrors();
    }, 200);
};

const submit = () => {
    form.post(route('pages.store'), {
        preserveScroll: true,
        preserveState: false,
        forceFormData: true,
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
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>

                <!-- Modal Container -->
                <div class="relative flex min-h-full items-center justify-center p-4">
                    <!-- Modal Content -->
                    <div class="relative w-full max-w-lg transform overflow-hidden rounded-2xl bg-card p-6 shadow-xl transition-all text-start">
                        <!-- Modal Header -->
                        <div class="mb-6 flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-foreground">
                                    {{ t('create_page') }}
                                </h3>
                                <p class="text-sm text-muted-foreground">{{ t('create_page_hint') }}</p>
                            </div>
                            <button
                                @click="close"
                                class="rounded-full p-1 text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
                            >
                                <XIcon class="h-5 w-5" />
                            </button>
                        </div>

                        <!-- Error Box -->
                        <div v-if="Object.keys(form.errors).length > 0" class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4">
                            <div class="mb-2 flex items-center gap-2">
                                <AlertCircle class="h-4 w-4 shrink-0 text-red-500" />
                                <p class="text-sm font-semibold text-red-700">{{ t('please_fix_errors') }}</p>
                            </div>
                            <ul class="space-y-1 ps-6">
                                <li v-for="(error, field) in form.errors" :key="field" class="text-sm text-red-600">
                                    {{ error }}
                                </li>
                            </ul>
                        </div>

                        <!-- Modal Body -->
                        <form @submit.prevent="submit" class="space-y-5">
                            <!-- Slug -->
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">
                                    {{ t('slug') }} <span class="text-red-500">*</span>
                                </label>
                                <Input v-model="form.slug" type="text" :placeholder="t('slug')" />
                            </div>

                            <!-- Image -->
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">
                                    {{ t('image') }}
                                </label>
                                <div class="flex items-center gap-4">
                                    <div class="flex h-16 w-16 items-center justify-center rounded-lg bg-muted overflow-hidden">
                                        <img
                                            v-if="imagePreview"
                                            :src="imagePreview"
                                            alt=""
                                            class="h-full w-full object-cover"
                                        />
                                        <ImagePlus v-else class="h-6 w-6 text-muted-foreground" />
                                    </div>
                                    <input
                                        type="file"
                                        accept="image/*"
                                        @change="handleImageChange"
                                        class="block w-full text-sm text-muted-foreground file:me-4 file:rounded-full file:border-0 file:bg-primary/10 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-primary hover:file:bg-primary/20"
                                    />
                                </div>
                            </div>

                            <!-- Active Checkbox -->
                            <div class="flex items-center gap-2">
                                <Checkbox id="is_active" v-model="form.is_active" />
                                <label for="is_active" class="text-sm font-medium text-foreground cursor-pointer">
                                    {{ t('active') }}
                                </label>
                            </div>

                            <!-- Translatable Name -->
                            <TranslatableInput
                                v-model="form.translations.name"
                                :languages="languages"
                                :label="t('name')"
                                :required="true"
                                :placeholder="t('enter_name')"
                            />

                            <!-- Modal Footer -->
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
