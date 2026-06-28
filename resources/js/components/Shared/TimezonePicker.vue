<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { PopoverRoot, PopoverTrigger, PopoverPortal, PopoverContent } from 'reka-ui';
import { Clock, Check, Search } from 'lucide-vue-next';
import { usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useDateFormat, timezoneOptions } from '@/composables/useDateFormat';

const page = usePage();
const { t } = useI18n();

const isAuthed = computed(() => Boolean(page.props.auth?.user));

const { timezone, setTimezone, resolvedTimezone } = useDateFormat();
const allZones = timezoneOptions();

const open = ref(false);
const search = ref('');

const locale = computed(() => page.props.locale?.code || 'en');

// Live ticking clock in the active timezone.
const now = ref(new Date());
let timer;
onMounted(() => {
    timer = setInterval(() => {
        now.value = new Date();
    }, 1000);
});
onUnmounted(() => clearInterval(timer));

const currentTime = computed(() =>
    new Intl.DateTimeFormat(locale.value, {
        hour: '2-digit',
        minute: '2-digit',
        timeZone: resolvedTimezone(),
    }).format(now.value),
);

// "Region/City" → "City" with underscores swapped for spaces.
const regionLabel = computed(() => {
    const tz = resolvedTimezone();
    const parts = tz.split('/');
    return (parts[parts.length - 1] || tz).replace(/_/g, ' ');
});

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return allZones;
    return allZones.filter((tz) => tz.toLowerCase().includes(q));
});

const select = (tz) => {
    setTimezone(tz);
    open.value = false;
    search.value = '';
};
</script>

<template>
    <div v-if="isAuthed" class="fixed top-4 end-4 z-40">
        <PopoverRoot v-model:open="open">
            <PopoverTrigger
                class="flex h-10 items-center gap-2 rounded-full border border-border bg-card px-3 text-muted-foreground shadow-sm transition-colors hover:bg-accent hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                :title="t('timezone') + ': ' + (timezone === 'auto' ? resolvedTimezone() : timezone)"
                :aria-label="t('timezone')"
            >
                <Clock class="size-5 shrink-0" />
                <span class="text-sm font-semibold tabular-nums text-foreground">{{ currentTime }}</span>
                <span class="hidden max-w-32 truncate text-xs sm:inline">{{ regionLabel }}</span>
            </PopoverTrigger>

            <PopoverPortal>
                <PopoverContent
                    align="end"
                    :side-offset="8"
                    class="z-50 w-72 rounded-xl border border-border bg-card p-2 text-foreground shadow-xl outline-none"
                >
                    <div class="mb-2 px-1">
                        <p class="text-sm font-semibold">{{ t('timezone') }}</p>
                        <p class="text-xs text-muted-foreground">{{ t('timezone_auto') }}: {{ resolvedTimezone() }}</p>
                    </div>

                    <div class="relative mb-2">
                        <Search class="absolute start-2 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <input
                            v-model="search"
                            :placeholder="t('search')"
                            class="h-9 w-full rounded-md border border-input bg-transparent ps-8 pe-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        />
                    </div>

                    <div class="max-h-64 overflow-y-auto">
                        <button
                            type="button"
                            class="flex w-full items-center justify-between rounded-md px-2 py-1.5 text-start text-sm transition-colors hover:bg-accent"
                            @click="select('auto')"
                        >
                            <span class="font-medium">{{ t('timezone_auto') }}</span>
                            <Check v-if="timezone === 'auto'" class="size-4 shrink-0 text-primary" />
                        </button>
                        <button
                            v-for="tz in filtered"
                            :key="tz"
                            type="button"
                            class="flex w-full items-center justify-between rounded-md px-2 py-1.5 text-start text-sm transition-colors hover:bg-accent"
                            @click="select(tz)"
                        >
                            <span class="truncate">{{ tz }}</span>
                            <Check v-if="timezone === tz" class="size-4 shrink-0 text-primary" />
                        </button>
                        <p v-if="!filtered.length" class="px-2 py-3 text-center text-xs text-muted-foreground">
                            {{ t('no_results', 'No results') }}
                        </p>
                    </div>
                </PopoverContent>
            </PopoverPortal>
        </PopoverRoot>
    </div>
</template>
