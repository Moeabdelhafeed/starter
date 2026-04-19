<script setup>
import { useI18n } from 'vue-i18n';
import { User, Activity, Clock, XIcon, Layers } from 'lucide-vue-next';
import Button from '@/components/ui/button/Button.vue';
import { computed } from 'vue';

const { t } = useI18n();

const props = defineProps({
    isOpen: Boolean,
    log: Object,
});

const emit = defineEmits(['close']);

const close = () => {
    emit('close');
};

const getActionColor = (action) => {
    switch (action) {
        case 'created': return 'bg-emerald-500/10 text-emerald-600 ring-emerald-500/20';
        case 'updated': return 'bg-amber-500/10 text-amber-600 ring-amber-500/20';
        case 'deleted': return 'bg-rose-500/10 text-rose-600 ring-rose-500/20';
        default: return 'bg-blue-500/10 text-blue-600 ring-blue-500/20';
    }
};

const formatDate = (dateString) => {
    return dateString ? new Date(dateString).toLocaleString() : '';
};

const getModelName = (subjectType) => {
    if (!subjectType) return 'N/A';
    const parts = subjectType.split('\\');
    return parts[parts.length - 1];
};

const diffData = computed(() => {
    if (!props.log) return [];
    
    const results = [];
    const oldData = props.log.old_data || {};
    const newData = props.log.new_data || {};
    
    // For Updates: show only changed fields or all? 
    // Usually better to show changed fields with old/new values.
    if (props.log.action === 'updated') {
        const keys = new Set([...Object.keys(oldData), ...Object.keys(newData)]);
        keys.forEach(key => {
            if (JSON.stringify(oldData[key]) !== JSON.stringify(newData[key])) {
                results.push({
                    key,
                    old: oldData[key],
                    new: newData[key],
                    type: 'update'
                });
            }
        });
    } else if (props.log.action === 'created') {
        Object.keys(newData).forEach(key => {
            results.push({
                key,
                new: newData[key],
                type: 'create'
            });
        });
    } else if (props.log.action === 'deleted') {
        Object.keys(oldData).forEach(key => {
            results.push({
                key,
                old: oldData[key],
                type: 'delete'
            });
        });
    }
    
    return results;
});
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
                    <div class="relative w-full max-w-2xl transform overflow-hidden rounded-2xl bg-card p-6 shadow-xl transition-all text-start">
                        <!-- Modal Header -->
                        <div class="mb-6 flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-foreground">
                                {{ t('log_details') }} #{{ log?.id }}
                            </h3>
                            <button
                                @click="close"
                                class="rounded-full p-1 text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
                            >
                                <XIcon class="h-5 w-5" />
                            </button>
                        </div>

                        <!-- Modal Body -->
                        <div class="space-y-6 max-h-[70vh] overflow-y-auto pr-2 custom-scrollbar">
                             <!-- Info Cards -->
                             <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="rounded-xl border border-border bg-muted p-4">
                                    <div class="flex items-center gap-3 mb-3">
                                        <User class="size-4 text-primary" />
                                        <span class="font-bold text-[10px] uppercase tracking-wider text-muted-foreground">{{ t('causer') }}</span>
                                    </div>
                                    <div class="space-y-1">
                                        <p class="text-sm font-bold text-foreground leading-none">{{ log?.causer_name }}</p>
                                        <p class="text-xs text-muted-foreground leading-none">{{ log?.causer_email }}</p>
                                    </div>
                                </div>
                                <div class="rounded-xl border border-border bg-muted p-4">
                                    <div class="flex items-center gap-3 mb-3">
                                        <Activity class="size-4 text-primary" />
                                        <span class="font-bold text-[10px] uppercase tracking-wider text-muted-foreground">{{ t('action_context') }}</span>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <p class="text-[9px] text-muted-foreground uppercase font-bold">{{ t('action') }}</p>
                                            <span :class="['inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-bold ring-1 ring-inset mt-1', getActionColor(log?.action)]">
                                                {{ t(log?.action) }}
                                            </span>
                                        </div>
                                        <div>
                                            <p class="text-[9px] text-muted-foreground uppercase font-bold">{{ t('target_id') }}</p>
                                            <p class="text-xs font-bold text-foreground mt-1">{{ log?.subject_id }}</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="rounded-xl border border-border bg-muted p-4">
                                    <div class="flex items-center gap-3 mb-3">
                                        <Layers class="size-4 text-primary" />
                                        <span class="font-bold text-[10px] uppercase tracking-wider text-muted-foreground">{{ t('target') }}</span>
                                    </div>
                                    <div class="space-y-1">
                                        <p class="text-sm font-bold text-foreground leading-none">{{ getModelName(log?.subject_type) }}</p>
                                        <p class="text-xs text-muted-foreground leading-none">{{ log?.subject_type }}</p>
                                    </div>
                                </div>
                                <div class="rounded-xl border border-border bg-muted p-4">
                                    <div class="flex items-center gap-3 mb-3">
                                        <Clock class="size-4 text-primary" />
                                        <span class="font-bold text-[10px] uppercase tracking-wider text-muted-foreground">{{ t('date') }}</span>
                                    </div>
                                    <div class="space-y-1">
                                        <p class="text-sm font-bold text-foreground leading-none">{{ formatDate(log?.created_at) }}</p>
                                        <p class="text-xs text-muted-foreground leading-none">{{ t('logged_at') }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Data Diff -->
                             <div class="space-y-4">
                                <div v-if="log?.old_data && Object.keys(log.old_data).length > 0" class="space-y-2">
                                    <div class="flex items-center gap-2">
                                        <div class="size-1.5 rounded-full bg-rose-500"></div>
                                        <h4 class="text-[10px] font-bold text-foreground uppercase tracking-tight">{{ t('previous_state') }}</h4>
                                    </div>
                                    <div class="rounded-xl border border-rose-100 bg-rose-50/10 p-4 font-mono text-[11px] overflow-x-auto">
                                        <pre class="text-rose-900">{{ JSON.stringify(log.old_data, null, 2) }}</pre>
                                                </div>
                                            </div>
                                            
                                <div v-if="log?.new_data && Object.keys(log.new_data).length > 0" class="space-y-2">
                                    <div class="flex items-center gap-2">
                                        <div class="size-1.5 rounded-full bg-emerald-500"></div>
                                        <h4 class="text-[10px] font-bold text-foreground uppercase tracking-tight">{{ t('new_state') }}</h4>
                                        </div>
                                    <div class="rounded-xl border border-emerald-100 bg-emerald-50/10 p-4 font-mono text-[11px] overflow-x-auto">
                                        <pre class="text-emerald-900">{{ JSON.stringify(log.new_data, null, 2) }}</pre>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Modal Footer -->
                        <div class="flex justify-end pt-6">
                            <Button type="button" variant="outline" @click="close" class="px-8">
                                {{ t('close') }}
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #e5e7eb;
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #d1d5db;
}
</style>
