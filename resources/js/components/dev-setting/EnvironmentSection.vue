<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Link2, Loader2, Lock, Plus, RotateCcw, Save, ToggleLeft, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

import LocalBaseGrid from '@/components/dev-setting/LocalBaseGrid.vue';
import SettingsCard from '@/components/dev-setting/SettingsCard.vue';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';

const { t } = useI18n();

/** The APP_URL / FRONTEND_URL pair of one .env, as DevSettingController::index() shapes `urls`. */
type UrlPair = {
    APP_URL: string;
    FRONTEND_URL: string;
};

const props = withDefaults(
    defineProps<{
        /** The env keys DevSettingController::$envToggles exposes, in order. */
        envToggles?: string[];
        /** Current boolean value of each of those keys in the local .env. */
        envValues?: Record<string, boolean>;
        /** IS_TESTING in .env.production — null when there is no .env.production yet. */
        baseTesting?: boolean | null;
        urls?: { local: UrlPair; base: UrlPair };
        /** PROTECTED_PAGES, plus which of those slugs the CMS already holds. */
        protectedPagesConfig?: { slugs: string[]; existing: string[] };
    }>(),
    {
        // The template indexes `envValues[key]` directly; the controller always sends
        // the map, so `{}` is only a type-level guard, never a real fallback.
        envValues: () => ({}),
        baseTesting: null,
    },
);

/**
 * Toggles are edited as a draft and written on Save, not on click.
 *
 * Each write rewrites .env, mirrors it into .env.production and clears the config
 * cache, so flipping a switch per request made a pass over the list eleven round trips
 * of that — slow enough that you watched it happen. Only the keys that actually
 * changed are sent.
 */
const draft = ref<Record<string, boolean>>({ ...props.envValues });
const baseDraft = ref<boolean | null>(props.baseTesting);

// The server is still the source of truth: a save (or any other reload) re-seeds the draft.
watch(
    () => props.envValues,
    (values) => {
        draft.value = { ...values };
    },
    { deep: true },
);
watch(
    () => props.baseTesting,
    (value) => {
        baseDraft.value = value;
    },
);

const dirtyValues = computed<Record<string, boolean>>(() =>
    Object.fromEntries(Object.entries(draft.value).filter(([key, on]) => Boolean(props.envValues[key]) !== on)),
);

const baseDirty = computed(() => props.baseTesting !== null && baseDraft.value !== props.baseTesting);

const dirtyCount = computed(() => Object.keys(dirtyValues.value).length + (baseDirty.value ? 1 : 0));

const savingToggles = ref(false);

const saveToggles = () => {
    if (dirtyCount.value === 0) {
        return;
    }

    savingToggles.value = true;
    router.post(
        route('dev_settings.env'),
        {
            values: dirtyValues.value,
            base: baseDirty.value ? { IS_TESTING: Boolean(baseDraft.value) } : {},
            _method: 'PUT',
        },
        {
            preserveScroll: true,
            preserveState: true,
            // Deliberately NOT a partial reload. These toggles decide which features
            // exist, and that changes shared props the rest of the page reads —
            // `has_*` drives the navbar links, and `ziggy` is the route list those
            // links resolve against. An `only:` list filters shared props out of the
            // response too, so the flag was written to .env while the browser kept
            // the old value: you switched a feature off and its nav link stayed until
            // a manual refresh.
            onFinish: () => {
                savingToggles.value = false;
            },
        },
    );
};

const discardToggles = () => {
    draft.value = { ...props.envValues };
    baseDraft.value = props.baseTesting;
};

/**
 * Which switch belongs where. Eleven flat rows hide the one distinction that matters:
 * most of them turn a whole module on or off, three change how the running app behaves.
 */
const TOGGLE_GROUPS: { label: string; keys: string[] }[] = [
    {
        label: 'env_group_modules',
        keys: [
            'APP_USERS',
            'APP_GUESTS',
            'HAS_TRANSLATIONS',
            'HAS_NOTIFICATION_TEMPLATES',
            'HAS_PAGES',
            'HAS_APP_SETTINGS',
            'HAS_DYNAMIC_STORAGE',
            'HAS_ACTIVITY_LOGS',
        ],
    },
    { label: 'env_group_behaviour', keys: ['IS_TESTING', 'APP_DEBUG', 'IS_OTP_WHATSAPP'] },
];

const toggleGroups = computed(() => {
    const keys = props.envToggles ?? [];
    const grouped = new Set<string>();

    const groups = TOGGLE_GROUPS.map((group) => {
        const own = keys.filter((key) => group.keys.includes(key));
        own.forEach((key) => grouped.add(key));

        return { label: group.label, keys: own };
    }).filter((group) => group.keys.length > 0);

    // A toggle added to the controller but not to a group above still has to show up —
    // otherwise it is written to .env by a screen that never displays it.
    const ungrouped = keys.filter((key) => !grouped.has(key));

    return ungrouped.length > 0 ? [...groups, { label: 'env_group_other', keys: ungrouped }] : groups;
});

const envLabel = (key: string): string => {
    const labels: Record<string, string> = {
        APP_USERS: 'App Users Module',
        APP_GUESTS: 'App Guests Module',
        HAS_TRANSLATIONS: 'App Translations',
        HAS_NOTIFICATION_TEMPLATES: 'Notification Templates',
        HAS_PAGES: 'Pages',
        HAS_APP_SETTINGS: 'App Settings',
        HAS_DYNAMIC_STORAGE: 'Dynamic Storage',
        HAS_ACTIVITY_LOGS: 'Activity Logs',
        IS_TESTING: 'Testing Mode',
        APP_DEBUG: 'Debug Mode',
        IS_OTP_WHATSAPP: 'OTP via WhatsApp',
    };
    return labels[key] || key;
};

const envDescription = (key: string): string => {
    const desc: Record<string, string> = {
        APP_USERS: 'Enable/disable the app users (API guard) module and routes',
        APP_GUESTS:
            'Enable/disable lazy guest user creation in IdentifyDevice middleware. When off, X-Device-Id + X-Platform headers are still required but no guest row is created.',
        HAS_TRANSLATIONS: 'Enable/disable app translations feature (admin routes, navbar links, API endpoints for translations/languages)',
        HAS_NOTIFICATION_TEMPLATES: 'Enable/disable the notification templates feature (admin routes and navbar link). Some apps do not need it.',
        HAS_PAGES: 'Enable/disable the pages feature (admin CRUD, public /p/{slug} page, and API page endpoints).',
        HAS_APP_SETTINGS: 'Enable/disable the App Settings feature (social/contact/store-link blocks admin CRUD + the /api/app-settings endpoint).',
        HAS_DYNAMIC_STORAGE: 'Enable/disable the Dynamic Storage feature (keyed media store admin CRUD + the /api/media upload & fetch endpoints).',
        HAS_ACTIVITY_LOGS:
            'Enable/disable the activity logs admin feature (routes + navbar link). Models still record logs; only the admin viewer is hidden.',
        IS_TESTING: 'Enable/disable testing mode for the application',
        APP_DEBUG: 'Enable/disable detailed error pages and debug info',
        IS_OTP_WHATSAPP: 'If true, OTPs sent via WhatsApp. If false, sent via SMS. Only applies when identifier is phone.',
    };
    return desc[key] || '';
};

// URLs
// Protected pages chip editor — the slugs the CMS may never delete.
const protectedPagesForm = useForm({
    slugs: [...(props.protectedPagesConfig?.slugs ?? [])],
});

const newProtectedPage = ref('');

const addProtectedPage = () => {
    const value = (newProtectedPage.value || '').toLowerCase().trim();
    if (!value) return;
    if (!/^[a-z0-9_-]+$/.test(value)) return;
    if (protectedPagesForm.slugs.includes(value)) return;
    protectedPagesForm.slugs.push(value);
    newProtectedPage.value = '';
};

const removeProtectedPage = (slug: string) => {
    protectedPagesForm.slugs = protectedPagesForm.slugs.filter((s) => s !== slug);
};

/** A slug that is protected but has no row yet — `db:seed --class=PageSeeder` creates it. */
const isMissing = (slug: string) => !(props.protectedPagesConfig?.existing ?? []).includes(slug);

const submitProtectedPages = () => {
    protectedPagesForm.put(route('dev_settings.protected_pages'), {
        preserveScroll: true,
        preserveState: true,
        reset: ['protectedPagesConfig', 'success', 'error'],
    });
};

const localUrlsForm = useForm({
    target: 'local',
    APP_URL: props.urls?.local?.APP_URL || 'http://localhost',
    FRONTEND_URL: props.urls?.local?.FRONTEND_URL || 'http://localhost:5173',
});
const baseUrlsForm = useForm({
    target: 'production',
    FRONTEND_URL: props.urls?.base?.FRONTEND_URL || '',
});

const submitLocalUrls = () => {
    localUrlsForm.put(route('dev_settings.urls'), {
        preserveScroll: true,
        preserveState: true,
        reset: ['urls', 'success', 'error'],
    });
};
const submitBaseUrls = () => {
    baseUrlsForm.put(route('dev_settings.urls'), {
        preserveScroll: true,
        preserveState: true,
        reset: ['urls', 'success', 'error'],
    });
};
</script>

<template>
    <div class="space-y-5">
        <!-- Environment Toggles -->
        <SettingsCard anchor="env-toggles" :title="t('env_toggles')" :description="t('env_toggles_desc')">
            <template #icon><ToggleLeft class="size-5 text-violet-500" /></template>

            <template #actions>
                <div class="flex items-center gap-2">
                    <span v-if="dirtyCount > 0" class="hidden text-xs font-medium text-warning sm:inline">
                        {{ t('unsaved_changes') }} &middot; {{ dirtyCount }}
                    </span>
                    <Button v-if="dirtyCount > 0" type="button" variant="outline" size="sm" @click="discardToggles">
                        <RotateCcw class="size-4" />
                        <span class="hidden sm:inline">{{ t('discard') }}</span>
                    </Button>
                    <Button type="button" size="sm" :disabled="dirtyCount === 0 || savingToggles" @click="saveToggles">
                        <Loader2 v-if="savingToggles" class="size-4 animate-spin" />
                        <Save v-else class="size-4" />
                        {{ savingToggles ? t('saving') : t('save_changes') }}
                    </Button>
                </div>
            </template>

            <p class="mb-4 text-xs text-muted-foreground">{{ t('env_toggles_save_hint') }}</p>

            <div class="space-y-6">
                <div v-for="group in toggleGroups" :key="group.label">
                    <p class="pb-1 text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">{{ t(group.label) }}</p>

                    <div class="divide-y divide-border">
                        <div v-for="key in group.keys" :key="key" class="flex items-center justify-between gap-4 py-4">
                            <div class="min-w-0">
                                <p class="flex flex-wrap items-center gap-2 text-sm font-medium text-foreground">
                                    {{ envLabel(key) }}
                                    <span
                                        v-if="key in dirtyValues"
                                        class="rounded-full bg-warning/15 px-2 py-0.5 text-[10px] font-semibold text-warning"
                                    >
                                        {{ t('unsaved_changes') }}
                                    </span>
                                </p>
                                <p class="text-xs text-muted-foreground">{{ envDescription(key) }}</p>
                                <p class="mt-1 font-mono text-xs text-muted-foreground">{{ key }}</p>
                            </div>

                            <div class="flex shrink-0 items-center gap-4">
                                <!-- Local toggle -->
                                <div v-if="key === 'IS_TESTING'" class="flex items-center gap-2">
                                    <span class="text-xs text-muted-foreground">{{ t('local') }}</span>
                                </div>
                                <button
                                    type="button"
                                    role="switch"
                                    :aria-checked="Boolean(draft[key])"
                                    :aria-label="`${envLabel(key)} — ${t('local')}`"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                    :class="draft[key] ? 'bg-primary' : 'bg-muted'"
                                    :disabled="savingToggles"
                                    @click="draft[key] = !draft[key]"
                                >
                                    <span
                                        class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                        :class="draft[key] ? 'translate-x-6 rtl:-translate-x-6' : 'translate-x-1 rtl:-translate-x-1'"
                                    />
                                </button>

                                <!-- Base toggle (IS_TESTING only) -->
                                <template v-if="key === 'IS_TESTING' && baseTesting !== null">
                                    <div class="flex items-center gap-2 border-s border-border ps-4">
                                        <span class="text-xs text-muted-foreground">{{ t('base') }}</span>
                                    </div>
                                    <button
                                        type="button"
                                        role="switch"
                                        :aria-checked="Boolean(baseDraft)"
                                        :aria-label="`${envLabel('IS_TESTING')} — ${t('base')}`"
                                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                        :class="baseDraft ? 'bg-primary' : 'bg-muted'"
                                        :disabled="savingToggles"
                                        @click="baseDraft = !baseDraft"
                                    >
                                        <span
                                            class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                            :class="baseDraft ? 'translate-x-6 rtl:-translate-x-6' : 'translate-x-1 rtl:-translate-x-1'"
                                        />
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </SettingsCard>

        <!-- App URLs -->
        <SettingsCard anchor="urls" :title="t('urls')" :description="t('urls_desc')">
            <template #icon><Link2 class="size-5 text-blue-500" /></template>

            <LocalBaseGrid>
                <template #local>
                    <form @submit.prevent="submitLocalUrls" class="space-y-4">
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-muted-foreground">{{ t('app_url') }}</label>
                            <Input v-model="localUrlsForm.APP_URL" type="url" placeholder="http://localhost" />
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-muted-foreground">{{ t('frontend_url') }}</label>
                            <Input v-model="localUrlsForm.FRONTEND_URL" type="url" placeholder="http://localhost:5173" />
                        </div>
                        <Button type="submit" :disabled="localUrlsForm.processing">
                            <Loader2 v-if="localUrlsForm.processing" class="me-2 h-4 w-4 animate-spin" />
                            {{ localUrlsForm.processing ? t('saving') : t('save_urls') }}
                        </Button>
                    </form>
                </template>
                <template #base>
                    <form @submit.prevent="submitBaseUrls" class="space-y-4">
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-muted-foreground">{{ t('app_url') }}</label>
                            <Input :model-value="urls?.base?.APP_URL || ''" type="url" disabled class="opacity-70" />
                            <p class="text-xs text-muted-foreground">{{ t('production_app_url_derived') }}</p>
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-muted-foreground">{{ t('frontend_url') }}</label>
                            <Input v-model="baseUrlsForm.FRONTEND_URL" type="url" placeholder="https://app.yourdomain.com" />
                        </div>
                        <Button type="submit" :disabled="baseUrlsForm.processing">
                            <Loader2 v-if="baseUrlsForm.processing" class="me-2 h-4 w-4 animate-spin" />
                            {{ baseUrlsForm.processing ? t('saving') : t('save_urls') }}
                        </Button>
                    </form>
                </template>
            </LocalBaseGrid>
        </SettingsCard>
        <!-- Protected pages -->
        <SettingsCard anchor="protected-pages" :title="t('protected_pages')" :description="t('protected_pages_desc')">
            <template #icon><Lock class="size-5 text-rose-500" /></template>

            <form @submit.prevent="submitProtectedPages" class="space-y-6">
                <div class="rounded-xl border bg-muted/30 p-4">
                    <p class="mb-3 text-xs font-medium text-muted-foreground">{{ t('protected_pages_list') }}</p>
                    <div class="flex flex-wrap gap-2">
                        <span
                            v-for="slug in protectedPagesForm.slugs"
                            :key="slug"
                            class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-sm font-medium"
                            :class="isMissing(slug) ? 'bg-amber-500/10 text-amber-600' : 'bg-primary/10 text-primary'"
                            :title="isMissing(slug) ? t('protected_page_not_seeded') : undefined"
                        >
                            /{{ slug }}
                            <button
                                type="button"
                                :aria-label="`${t('remove')} ${slug}`"
                                class="rounded-full p-0.5 transition-colors hover:bg-foreground/10 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                @click="removeProtectedPage(slug)"
                            >
                                <X class="size-3" />
                            </button>
                        </span>
                        <span v-if="protectedPagesForm.slugs.length === 0" class="text-sm text-muted-foreground">
                            {{ t('no_protected_pages') }}
                        </span>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <Input
                        v-model="newProtectedPage"
                        :placeholder="t('page_slug_placeholder')"
                        class="flex-1"
                        @keydown.enter.prevent="addProtectedPage"
                    />
                    <Button type="button" variant="outline" @click="addProtectedPage">
                        <Plus class="me-2 size-4" />
                        {{ t('add') }}
                    </Button>
                </div>

                <p class="text-xs text-muted-foreground">{{ t('protected_pages_hint') }}</p>
                <p class="text-xs font-medium text-destructive">{{ t('protected_pages_removal_warning') }}</p>

                <div class="pt-2">
                    <Button type="submit" :disabled="protectedPagesForm.processing">
                        <Loader2 v-if="protectedPagesForm.processing" class="me-2 h-4 w-4 animate-spin" />
                        {{ protectedPagesForm.processing ? t('saving') : t('save') }}
                    </Button>
                </div>
            </form>
        </SettingsCard>
    </div>
</template>
