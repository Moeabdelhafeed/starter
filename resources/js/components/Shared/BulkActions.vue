<script setup>
import { useI18n } from 'vue-i18n';
import Button from '@/components/ui/button/Button.vue';
import { Trash2, Power, PowerOff, X, RotateCcw, Trash } from 'lucide-vue-next';

const { t } = useI18n();

const props = defineProps({
    selectedCount: {
        type: Number,
        default: 0,
    },
    actions: {
        type: Object,
        default: () => ({
            delete: true,
            statusOn: true,
            statusOff: true,
            restore: false,
            forceDelete: false,
        }),
    },
});

const emit = defineEmits(['delete', 'turnOn', 'turnOff', 'clear', 'restore', 'forceDelete']);
</script>

<template>
    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="opacity-0 -translate-y-4"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 -translate-y-4"
    >
        <div v-if="selectedCount > 0" class="flex flex-col gap-3 rounded-xl border bg-card p-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-4">
                <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                    <span class="text-sm font-bold">{{ selectedCount }}</span>
                </div>
                <div>
                    <h4 class="text-sm font-bold text-foreground leading-none mb-1">{{ t('bulk_actions') }}</h4>
                    <p class="text-xs text-muted-foreground">{{ t('items_selected', { count: selectedCount }) }}</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                <Button
                    v-if="actions.statusOn"
                    variant="outline"
                    class="h-9 border-emerald-500/50 text-emerald-600 hover:bg-emerald-500 hover:text-white"
                    @click="emit('turnOn')"
                >
                    <Power class="me-2 size-4" />
                    {{ t('turn_on') }}
                </Button>

                <Button
                    v-if="actions.statusOff"
                    variant="outline"
                    class="h-9 border-amber-500/50 text-amber-600 hover:bg-amber-500 hover:text-white"
                    @click="emit('turnOff')"
                >
                    <PowerOff class="me-2 size-4" />
                    {{ t('turn_off') }}
                </Button>

                <Button
                    v-if="actions.delete"
                    variant="outline"
                    class="h-9 border-red-500/50 text-red-600 hover:bg-red-500 hover:text-white"
                    @click="emit('delete')"
                >
                    <Trash2 class="me-2 size-4" />
                    {{ t('delete') }}
                </Button>

                <Button
                    v-if="actions.restore"
                    variant="outline"
                    class="h-9 border-blue-500/50 text-blue-600 hover:bg-blue-500 hover:text-white"
                    @click="emit('restore')"
                >
                    <RotateCcw class="me-2 size-4" />
                    {{ t('restore') }}
                </Button>

                <Button
                    v-if="actions.forceDelete"
                    variant="outline"
                    class="h-9 border-red-700/50 text-red-700 hover:bg-red-700 hover:text-white"
                    @click="emit('forceDelete')"
                >
                    <Trash class="me-2 size-4" />
                    {{ t('force_delete') }}
                </Button>

                <div class="mx-2 h-6 w-px bg-border"></div>

                <button
                    @click="emit('clear')"
                    class="flex h-8 w-8 items-center justify-center rounded-full text-muted-foreground hover:bg-accent hover:text-accent-foreground transition-colors"
                >
                    <X class="size-5" />
                </button>
            </div>
        </div>
    </Transition>
</template>
