<script setup>
import { useI18n } from 'vue-i18n';
import { Laptop, Server, Info } from 'lucide-vue-next';

const { t } = useI18n();
</script>

<template>
    <div class="space-y-4">
        <!-- One-time explainer: what Local vs Base means -->
        <div class="flex items-start gap-2 rounded-xl border border-sky-500/30 bg-sky-500/5 p-3">
            <Info class="mt-0.5 size-4 shrink-0 text-sky-500" />
            <p class="text-xs text-muted-foreground">{{ t('local_base_explainer') }}</p>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <!-- Local (.env) -->
            <div class="space-y-4 rounded-2xl border border-border p-4">
                <div class="flex items-center gap-2">
                    <Laptop class="size-4 text-foreground" />
                    <span class="text-sm font-semibold text-foreground">{{ t('scope_local') }}</span>
                    <code class="rounded bg-muted px-1.5 py-0.5 text-xs text-muted-foreground">.env</code>
                </div>
                <slot name="local" />
            </div>

            <!-- Base (.env.production) -->
            <div class="space-y-4 rounded-2xl border border-sky-500/30 bg-sky-500/5 p-4">
                <div class="flex items-center gap-2">
                    <Server class="size-4 text-sky-600" />
                    <span class="text-sm font-semibold text-foreground">{{ t('scope_base') }}</span>
                    <code class="rounded bg-muted px-1.5 py-0.5 text-xs text-muted-foreground">.env.production</code>
                </div>
                <p class="text-xs text-muted-foreground">{{ t('scope_base_hint') }}</p>
                <slot name="base" />
            </div>
        </div>
    </div>
</template>
