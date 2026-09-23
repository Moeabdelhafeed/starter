<script setup lang="ts">
import { Database, ExternalLink, Loader2, RefreshCw } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

import SettingsCard from '@/components/dev-setting/SettingsCard.vue';
import Button from '@/components/ui/button/Button.vue';
import { getJson, postJson } from '@/lib/json-api';

const { t } = useI18n();

type DatabaseRow = {
    username: string;
    name: string;
    user: string;
    domain: string;
    host: string;
    port: number;
    disk_usage_mb: number;
    max_size_mb: number;
    created_at: string;
};

const rows = ref<DatabaseRow[]>([]);
const configured = ref(true);
const loading = ref(false);
const error = ref('');
const opening = ref('');

const load = async () => {
    loading.value = true;
    error.value = '';

    const result = await getJson<{ databases: DatabaseRow[]; configured: boolean; error?: string }>(
        route('dev_settings.databases'),
        t('databases_failed'),
    );

    if (result.ok) {
        rows.value = result.data.databases ?? [];
        configured.value = result.data.configured;
        error.value = result.data.error ?? '';
    } else {
        error.value = result.error;
    }

    loading.value = false;
};

/**
 * The sign-on URL is a live credential for that database, so it is fetched per click
 * and never held in the page. Opening the tab synchronously with a placeholder keeps
 * Safari from treating the post-await `window.open` as a popup.
 */
const openPhpMyAdmin = async (row: DatabaseRow) => {
    opening.value = row.name;
    error.value = '';

    const tab = window.open('', '_blank');

    const result = await postJson<{ link: string }>(
        route('dev_settings.phpmyadmin_link'),
        { username: row.username, name: row.name },
        t('phpmyadmin_failed'),
    );

    if (result.ok && result.data.link) {
        if (tab) {
            tab.location.href = result.data.link;
        } else {
            window.location.href = result.data.link;
        }
    } else {
        tab?.close();
        error.value = result.ok ? t('phpmyadmin_failed') : result.error;
    }

    opening.value = '';
};

onMounted(load);
</script>

<template>
    <div class="space-y-5">
        <SettingsCard anchor="databases" :title="t('databases')" :description="t('databases_desc')">
            <template #icon><Database class="size-5 text-sky-500" /></template>

            <template #actions>
                <Button type="button" variant="outline" size="sm" :disabled="loading" @click="load">
                    <Loader2 v-if="loading" class="size-4 animate-spin" />
                    <RefreshCw v-else class="size-4" />
                    {{ t('refresh') }}
                </Button>
            </template>

            <p v-if="error" class="mb-4 rounded-xl border border-destructive/30 bg-destructive/5 p-3 text-xs font-medium text-destructive">
                {{ error }}
            </p>

            <div v-if="!configured" class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                {{ t('databases_no_token') }}
            </div>

            <div v-else-if="loading && rows.length === 0" class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                {{ t('loading') }}
            </div>

            <div v-else-if="rows.length === 0" class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                {{ t('databases_empty') }}
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-start text-sm">
                    <thead>
                        <tr class="border-b text-xs text-muted-foreground">
                            <th class="py-2 pe-4 text-start font-medium">{{ t('database') }}</th>
                            <th class="py-2 pe-4 text-start font-medium">{{ t('website') }}</th>
                            <th class="py-2 pe-4 text-start font-medium">{{ t('size') }}</th>
                            <th class="py-2 text-end font-medium"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in rows" :key="row.name" class="border-b last:border-0">
                            <td class="py-3 pe-4 align-top">
                                <p class="font-mono text-xs text-foreground">{{ row.name }}</p>
                                <p class="font-mono text-xs text-muted-foreground">{{ row.user }}</p>
                            </td>
                            <td class="py-3 pe-4 align-top text-xs text-muted-foreground">{{ row.domain || '—' }}</td>
                            <td class="py-3 pe-4 align-top text-xs whitespace-nowrap text-muted-foreground">
                                {{ row.disk_usage_mb }} / {{ row.max_size_mb }} MB
                            </td>
                            <td class="py-3 text-end align-top">
                                <Button type="button" variant="outline" size="sm" :disabled="opening === row.name" @click="openPhpMyAdmin(row)">
                                    <Loader2 v-if="opening === row.name" class="size-4 animate-spin" />
                                    <ExternalLink v-else class="size-4" />
                                    <span class="hidden sm:inline">phpMyAdmin</span>
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p class="mt-4 text-xs text-muted-foreground">{{ t('phpmyadmin_hint') }}</p>
        </SettingsCard>
    </div>
</template>
