<script setup>
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { XIcon, Loader2, AlertCircle } from 'lucide-vue-next';
import Input from '@/components/ui/input/Input.vue';
import Button from '@/components/ui/button/Button.vue';
import Checkbox from '@/components/ui/checkbox/Checkbox.vue';
import TranslatableInput from '@/components/ui/translatable-input/TranslatableInput.vue';
import ImageUpload from '@/components/ui/image-upload/ImageUpload.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useI18n();
const { translationsToObject } = useTranslations();

const props = defineProps({
    isOpen: Boolean,
    languages: Array,
    /** Type string preset for create mode. */
    type: {
        type: String,
        default: '',
    },
    /** Existing item for edit mode; null = create. */
    item: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close']);

const form = useForm({
    type: '',
    url: '',
    is_active: true,
    image: null,
    remove_image: false,
    translations: {
        text: {},
    },
});

const resetForm = () => {
    if (props.item) {
        form.type = props.item.type;
        form.url = props.item.url || '';
        form.is_active = !!props.item.is_active;
        form.image = null;
        form.remove_image = false;
        form.translations = translationsToObject(props.item.translations, ['text']);
    } else {
        form.type = props.type;
        form.url = '';
        form.is_active = true;
        form.image = null;
        form.remove_image = false;
        form.translations = { text: {} };
    }
    form.clearErrors();
};

watch(
    () => props.isOpen,
    (open) => {
        if (open) resetForm();
    },
);

const close = () => {
    emit('close');
    setTimeout(() => {
        form.reset();
        form.clearErrors();
    }, 200);
};

const submit = () => {
    if (props.item) {
        form
            .transform((data) => ({ ...data, _method: 'PUT' }))
            .post(route('app_settings.update', props.item.id), {
                preserveScroll: true,
                preserveState: true,
                forceFormData: true,
                reset: ['blocks', 'success', 'error'],
                onSuccess: () => close(),
            });
    } else {
        form.post(route('app_settings.store'), {
            preserveScroll: true,
            preserveState: true,
            forceFormData: true,
            reset: ['blocks', 'success', 'error'],
            onSuccess: () => close(),
        });
    }
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
                                    {{ item ? t('edit') : t('add_item') }}
                                </h3>
                                <p class="text-sm text-muted-foreground">{{ t('appset_type_' + form.type) }}</p>
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
                            <!-- Translatable Text -->
                            <TranslatableInput
                                v-model="form.translations.text"
                                :languages="languages"
                                :label="t('link_text')"
                                :required="true"
                                :error="form.errors['translations.text']"
                            />

                            <!-- URL -->
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">
                                    {{ t('link_url') }}
                                </label>
                                <Input v-model="form.url" type="text" :placeholder="t('link_url')" dir="ltr" />
                            </div>

                            <!-- Image -->
                            <ImageUpload
                                v-model="form.image"
                                v-model:removed="form.remove_image"
                                :preview-url="item?.image?.image_api || null"
                                :label="t('image')"
                                :error="form.errors.image"
                            />

                            <!-- Active Checkbox -->
                            <div class="flex items-center gap-2">
                                <Checkbox id="appset_is_active" v-model="form.is_active" />
                                <label for="appset_is_active" class="cursor-pointer text-sm font-medium text-foreground">
                                    {{ t('active') }}
                                </label>
                            </div>

                            <!-- Modal Footer -->
                            <div class="flex gap-3 pt-4">
                                <Button type="button" variant="outline" @click="close" class="flex-1">
                                    {{ t('cancel') }}
                                </Button>
                                <Button type="submit" :disabled="form.processing" class="flex-1">
                                    <Loader2 v-if="form.processing" class="me-2 h-4 w-4 animate-spin" />
                                    {{ form.processing ? (item ? t('saving') : t('creating')) : (item ? t('save') : t('create')) }}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
