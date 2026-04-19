<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { XIcon, EyeIcon, EyeOffIcon, Loader2, AlertCircle } from 'lucide-vue-next';
import Input from '@/components/ui/input/Input.vue';
import Button from '@/components/ui/button/Button.vue';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

const { t } = useI18n();
const page = usePage();
const currentUser = computed(() => page.props.auth.user);

const props = defineProps({
    isOpen: Boolean,
    user: Object,
    roles: Array,
});

const emit = defineEmits(['close']);

const showPassword = ref(false);

const form = useForm({
    id: null,
    name: '',
    email: '',
    password: '',
    role: '',
    image: null,
    _method: 'PUT',
});

const currentImageUrl = ref(null);

watch(
    () => props.user,
    (newUser) => {
        if (newUser) {
            form.id = newUser.id;
            form.name = newUser.name;
            form.email = newUser.email;
            form.password = '';
            form.role = newUser.roles.length > 0 ? newUser.roles[0].name : '';
            form.image = null;
            currentImageUrl.value = newUser.image?.image_api || null;
        }
    },
    { immediate: true },
);

const handleImageChange = (e) => {
    form.image = e.target.files[0] || null;
};

const close = () => {
    emit('close');
    form.reset();
    form.clearErrors();
    showPassword.value = false;
};

const submit = () => {
    form.post(route('users.update', form.id), {
        preserveScroll: true,
        preserveState: true,
        reset: ['users', 'success', 'error', 'filters'],
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
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>

                <!-- Modal Container -->
                <div class="relative flex min-h-full items-center justify-center p-4">
                    <!-- Modal Content -->
                    <div class="relative w-full max-w-lg transform overflow-hidden rounded-2xl bg-card p-6 shadow-xl transition-all text-start">
                        <!-- Modal Header -->
                        <div class="mb-6 flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-foreground">
                                {{ t('edit') }}
                            </h3>
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
                                    <span class="font-medium">{{ t(field.toLowerCase()) }}</span>: {{ error }}
                                </li>
                            </ul>
                        </div>

                        <!-- Modal Body -->
                        <form @submit.prevent="submit" class="space-y-5">
                            <!-- Name -->
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">
                                    {{ t('name') }}
                                </label>
                                <Input v-model="form.name" type="text" :placeholder="t('name')" />
                                <div v-if="form.errors.name" class="text-sm text-red-600">
                                    {{ form.errors.name }}
                                </div>
                            </div>

                            <!-- Email -->
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">
                                    {{ t('email') }}
                                </label>
                                <Input v-model="form.email" type="email" :placeholder="t('email')" />
                                <div v-if="form.errors.email" class="text-sm text-red-600">
                                    {{ form.errors.email }}
                                </div>
                            </div>

                            <!-- Image -->
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">
                                    {{ t('image') }}
                                </label>
                                <div v-if="currentImageUrl && !form.image" class="mb-2">
                                    <img :src="currentImageUrl" alt="User image" class="h-16 w-16 rounded-full object-cover" />
                                </div>
                                <input
                                    type="file"
                                    accept="image/*"
                                    @change="handleImageChange"
                                    class="block w-full text-sm text-muted-foreground file:me-4 file:rounded-full file:border-0 file:bg-primary/10 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-primary hover:file:bg-primary/20"
                                />
                                <div v-if="form.errors.image" class="text-sm text-red-600">
                                    {{ form.errors.image }}
                                </div>
                            </div>

                            <!-- Role -->
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">
                                    {{ t('role') }}
                                </label>
                                <Select v-model="form.role" :disabled="form.id === currentUser?.id">
                                    <SelectTrigger :class="{ 'bg-muted/50 border-border text-muted-foreground!': form.id === currentUser?.id }">
                                        <SelectValue :placeholder="t('select_role')" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="r in roles" :key="r.id" :value="r.name">
                                            {{ r.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <div v-if="form.errors.role" class="text-sm text-red-600">
                                    {{ form.errors.role }}
                                </div>
                            </div>

                            <!-- Password -->
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">
                                    {{ t('password') }}
                                </label>
                                <div class="relative">
                                    <Input
                                        v-model="form.password"
                                        :type="showPassword ? 'text' : 'password'"
                                        :placeholder="t('password_placeholder')"
                                        class="pe-10"
                                    />
                                    <button
                                        type="button"
                                        @click="showPassword = !showPassword"
                                        class="absolute inset-y-0 end-0 flex items-center pe-3 text-muted-foreground hover:text-accent-foreground"
                                    >
                                        <EyeIcon v-if="!showPassword" class="h-5 w-5" />
                                        <EyeOffIcon v-else class="h-5 w-5" />
                                    </button>
                                </div>
                                <div v-if="form.errors.password" class="text-sm text-red-600">
                                    {{ form.errors.password }}
                                </div>
                            </div>

                            <!-- Modal Footer -->
                            <div class="flex gap-3 pt-4">
                                <Button type="button" variant="outline" @click="close" class="flex-1">
                                    {{ t('cancel') }}
                                </Button>
                                <Button type="submit" :disabled="form.processing" class="flex-1">
                                    <Loader2 v-if="form.processing" class="me-2 h-4 w-4 animate-spin" />
                                    {{ form.processing ? t('saving') : t('save') }}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
