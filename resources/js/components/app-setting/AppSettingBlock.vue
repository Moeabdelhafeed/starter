<script setup>
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Plus, ImageIcon, AlertTriangle, ExternalLink } from 'lucide-vue-next';
import Button from '@/components/ui/button/Button.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useI18n();
const { translationsToObject } = useTranslations();

const props = defineProps({
    type: {
        type: String,
        required: true,
    },
    items: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['add', 'edit', 'delete']);

const toggleStatus = (item) => {
    const newStatus = !item.is_active;
    item.is_active = newStatus;

    router.post(
        route('app_settings.update', item.id),
        {
            _method: 'PUT',
            type: item.type,
            url: item.url || '',
            is_active: newStatus,
            translations: translationsToObject(item.translations, ['text']),
        },
        {
            preserveScroll: true,
            preserveState: true,
            reset: ['blocks', 'success', 'error'],
            onError: () => {
                item.is_active = !newStatus;
            },
        },
    );
};
</script>

<template>
    <div class="flex flex-col gap-5 rounded-3xl border bg-card p-6">
        <!-- Block header -->
        <div class="flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center">
            <h2 class="text-lg font-bold tracking-tight text-foreground">
                {{ t('appset_type_' + type) }}
            </h2>
            <Button size="sm" @click="emit('add', type)">
                <Plus class="me-2 h-4 w-4" />
                {{ t('add_item') }}
            </Button>
        </div>

        <!-- Empty state -->
        <p v-if="!items.length" class="rounded-xl border border-dashed bg-muted/30 p-6 text-center text-sm text-muted-foreground">
            {{ t('no_items_yet') }}
        </p>

        <!-- Items -->
        <ul v-else class="flex flex-col gap-3">
            <li
                v-for="item in items"
                :key="item.id"
                class="flex flex-col gap-3 rounded-2xl border bg-background p-4 md:flex-row md:items-center md:gap-4"
            >
                <!-- Thumbnail -->
                <div class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-muted">
                    <img
                        v-if="item.image?.image_api"
                        :src="item.image.image_api"
                        :alt="item.text_api || ''"
                        class="h-full w-full object-cover"
                    />
                    <ImageIcon v-else class="h-5 w-5 text-muted-foreground" />
                </div>

                <!-- Text + url -->
                <div class="flex min-w-0 flex-1 flex-col gap-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span v-if="item.text_api" class="truncate font-medium text-foreground">{{ item.text_api }}</span>
                        <span v-else class="text-muted-foreground">—</span>

                        <!-- Missing translations badge -->
                        <span
                            v-if="item.missing_translations?.length"
                            class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700"
                            :title="t('missing_translations_hint')"
                        >
                            <AlertTriangle class="h-3 w-3" />
                            {{ t('missing_translations') + ': ' + item.missing_translations.join(', ') }}
                        </span>
                    </div>

                    <a
                        v-if="item.url"
                        :href="item.url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex max-w-full items-center gap-1 truncate text-sm text-primary hover:underline"
                        dir="ltr"
                    >
                        <ExternalLink class="h-3 w-3 shrink-0" />
                        <span class="truncate">{{ item.url }}</span>
                    </a>
                </div>

                <!-- Status + actions -->
                <div class="flex items-center justify-between gap-2 md:justify-end">
                    <button
                        type="button"
                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:outline-none"
                        :class="item.is_active ? 'bg-primary' : 'bg-border'"
                        @click="toggleStatus(item)"
                    >
                        <span
                            class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                            :class="item.is_active ? 'ltr:translate-x-6 rtl:-translate-x-6' : 'ltr:translate-x-1 rtl:-translate-x-1'"
                        />
                    </button>

                    <Button
                        variant="outline"
                        class="border-yellow-500 text-yellow-500 shadow-none! hover:bg-yellow-500 hover:text-white"
                        @click="emit('edit', item)"
                    >
                        {{ t('edit') }}
                    </Button>
                    <Button
                        variant="outline"
                        class="border-red-500 text-red-500 shadow-none! hover:bg-red-500 hover:text-white"
                        @click="emit('delete', item)"
                    >
                        {{ t('delete') }}
                    </Button>
                </div>
            </li>
        </ul>
    </div>
</template>
