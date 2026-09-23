<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import {
    Activity,
    Bell,
    Check,
    CornerDownLeft,
    FileText,
    Globe,
    HardDrive,
    Hammer,
    Languages,
    type LucideIcon,
    Search,
    Share2,
    Users,
} from 'lucide-vue-next';
import type { Component } from 'vue';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useId, watch } from 'vue';
import { useI18n } from 'vue-i18n';

import { SECTION_LABELS, searchSettings } from '@/components/dev-setting/settings-index';
import BaseModal from '@/components/ui/modal/BaseModal.vue';
import { useCommandPalette } from '@/composables/useCommandPalette';

/**
 * Global CMS search, opened with Cmd/Ctrl+K.
 *
 * Two kinds of hit sit in one list. Navigation targets are matched locally from
 * the same permission-filtered nav tree the sidebar renders, so jumping to a
 * screen never waits for the network. Records come from GET /search, which
 * filters every module by the caller's permissions server-side.
 *
 * Keyboard contract: arrows move, Enter opens, Escape closes (BaseModal owns
 * Escape, the focus trap and the scroll lock).
 *
 * **Below `sm` it is a full-screen sheet, not a floating card.** Everything the
 * desktop palette leans on is absent on a phone: no Escape key, no arrows, no
 * Enter hint worth reading, and a software keyboard that eats the bottom half
 * of the screen the moment the field takes focus. So the chrome swaps rather
 * than hides — a real close button instead of the ESC badge, thumb-sized rows
 * instead of a hover highlight, and the keyboard legend gone entirely.
 */
const { isOpen, close } = useCommandPalette();
const { t } = useI18n();
const page = usePage();

const uid = useId();
const listId = `palette-list-${uid}`;
const optionId = (index: number) => `palette-option-${uid}-${index}`;

const DEBOUNCE_MS = 200;
/** Below this a server query matches most of the table; nav-only is more useful. */
const MIN_TERM = 2;

/**
 * Phone-sized viewport.
 *
 * A media *query* rather than a `hidden sm:block` class because the difference
 * is behavioural, not cosmetic: mobile gets a close button the desktop does not
 * render at all, and drops a footer that would otherwise leave its own margin
 * behind. Starts false so the server and the first client render agree — the
 * desktop layout is the safe guess, and `onMounted` corrects it before paint.
 */
const compact = ref(false);

const query = ref('');
const activeIndex = ref(0);
const loading = ref(false);
const input = ref<HTMLInputElement | null>(null);

interface ResultItem {
    id: number | string;
    title: string;
    subtitle: string | null;
    url: string;
}

interface ResultGroup {
    key: string;
    route: string;
    items: ResultItem[];
}

/** One row in the flattened list the keyboard walks. */
interface PaletteRow {
    key: string;
    groupKey: string;
    title: string;
    subtitle: string | null;
    url: string;
    icon: Component;
}

const GROUP_ICONS: Record<string, LucideIcon> = {
    users: Users,
    app_users: Users,
    roles: Check,
    pages: FileText,
    translations: Languages,
    languages: Globe,
    media: HardDrive,
    notification_templates: Bell,
    app_settings: Share2,
    activity_logs: Activity,
};

const can = (permission: string) => page.props.auth.permissions.includes(permission);

/**
 * Screens this admin can open. Mirrors the sidebar's own gating — an entry
 * whose feature is off has no registered route, and route() throws on those.
 */
const destinations = computed(() =>
    [
        { key: 'dashboard', label: t('dashboard'), icon: Globe, routeName: 'dashboard', show: true },
        { key: 'users', label: t('users'), icon: Users, routeName: 'users', show: can('users') },
        {
            key: 'app_users',
            label: page.props.app_users ? t('app_users') : t('guest_users'),
            icon: Users,
            routeName: 'app_users',
            show: can('app_users') && (page.props.app_users || page.props.app_guests),
        },
        { key: 'roles', label: t('roles'), icon: Check, routeName: 'roles', show: can('roles') },
        {
            key: 'translations',
            label: t('translations'),
            icon: Languages,
            routeName: 'translations',
            show: page.props.has_translations && can('translations'),
        },
        {
            key: 'languages',
            label: t('languages'),
            icon: Globe,
            routeName: 'languages',
            show: page.props.has_translations && can('translations'),
        },
        {
            key: 'activity_logs',
            label: t('activity_logs'),
            icon: Activity,
            routeName: 'activity_logs',
            show: page.props.has_activity_logs && can('activity_logs'),
        },
        { key: 'pages', label: t('pages'), icon: FileText, routeName: 'pages', show: page.props.has_pages && can('pages') },
        {
            key: 'app_settings',
            label: t('app_settings'),
            icon: Share2,
            routeName: 'app_settings',
            show: page.props.has_app_settings && can('app_settings'),
        },
        {
            key: 'media',
            label: t('dynamic_storage'),
            icon: HardDrive,
            routeName: 'media',
            show: page.props.has_dynamic_storage && can('dynamic_storage'),
        },
        {
            key: 'notification_templates',
            label: t('notification_templates'),
            icon: Bell,
            routeName: 'notification_templates',
            show: page.props.has_notification_templates && can('notification_templates'),
        },
        { key: 'profile', label: t('profile'), icon: Users, routeName: 'profile', show: true },
    ]
        .filter((item) => item.show)
        // href resolved after the filter: a disabled feature's route is not registered.
        .map((item) => ({ ...item, href: route(item.routeName) })),
);

const matchedDestinations = computed<PaletteRow[]>(() => {
    const needle = query.value.trim().toLowerCase();

    return destinations.value
        .filter((item) => !needle || item.label.toLowerCase().includes(needle) || item.key.includes(needle))
        .map((item) => ({
            key: `nav:${item.key}`,
            groupKey: 'go_to',
            title: item.label,
            subtitle: null,
            url: item.href,
            icon: item.icon,
        }));
});

/**
 * Individual DevSettings cards, searchable from here.
 *
 * Local-only and super_admin-only, because that is exactly what the `dev-settings`
 * route requires — `route()` throws on an unregistered route, and showing
 * twenty-five rows that all 403 would be worse than showing none.
 *
 * The same `settingsIndex` and matcher the panel's own search box uses, so the two
 * never disagree about what a setting is called or where it lives. The link is the
 * card's anchor as a hash; DevSetting/Index.vue resolves it to a section and rings
 * the card on arrival.
 */
const canSeeDevSettings = computed(
    () => Boolean(page.props.is_local) && (page.props.auth.roles ?? []).includes('super_admin'),
);

const settingRows = computed<PaletteRow[]>(() => {
    if (!canSeeDevSettings.value) {
        return [];
    }

    const needle = query.value.trim();

    // With no term this would append every card to the nav list; the panel's own
    // search box is the place to browse them.
    if (needle === '') {
        return [];
    }

    return searchSettings(needle, (key: string) => t(key)).map((entry) => ({
        key: `setting:${entry.anchor}`,
        groupKey: 'dev_settings',
        title: t(entry.label),
        subtitle: t(SECTION_LABELS[entry.section] ?? entry.section),
        url: `${route('dev_settings')}#${entry.anchor}`,
        icon: Hammer,
    }));
});

const results = ref<ResultGroup[]>([]);

const recordRows = computed<PaletteRow[]>(() =>
    results.value.flatMap((group) =>
        group.items.map((item) => ({
            key: `${group.key}:${item.id}`,
            groupKey: group.key,
            title: item.title,
            subtitle: item.subtitle,
            url: item.url,
            icon: GROUP_ICONS[group.key] ?? Search,
        })),
    ),
);

/** Flat list in render order, so an index maps 1:1 to what the user sees. */
const rows = computed<PaletteRow[]>(() => [...matchedDestinations.value, ...settingRows.value, ...recordRows.value]);

/** Rows regrouped for rendering, preserving the flat index for aria/selection. */
const renderGroups = computed(() => {
    const groups: { key: string; rows: Array<PaletteRow & { index: number }> }[] = [];

    rows.value.forEach((row, index) => {
        const last = groups[groups.length - 1];

        if (last && last.key === row.groupKey) {
            last.rows.push({ ...row, index });
            return;
        }

        groups.push({ key: row.groupKey, rows: [{ ...row, index }] });
    });

    return groups;
});

let debounce: ReturnType<typeof setTimeout> | null = null;
let inFlight: AbortController | null = null;

const fetchResults = async (term: string) => {
    inFlight?.abort();

    if (term.length < MIN_TERM) {
        results.value = [];
        loading.value = false;
        return;
    }

    const controller = new AbortController();
    inFlight = controller;
    loading.value = true;

    try {
        const response = await fetch(`${route('search')}?q=${encodeURIComponent(term)}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            signal: controller.signal,
        });

        if (!response.ok) throw new Error(String(response.status));

        const payload = (await response.json()) as { groups: ResultGroup[] };
        results.value = payload.groups ?? [];
    } catch (error) {
        // An aborted request is the expected outcome of typing another key.
        if ((error as Error)?.name === 'AbortError') return;
        results.value = [];
    } finally {
        if (inFlight === controller) {
            loading.value = false;
            inFlight = null;
        }
    }
};

watch(query, (term) => {
    activeIndex.value = 0;

    if (debounce) clearTimeout(debounce);
    debounce = setTimeout(() => fetchResults(term.trim()), DEBOUNCE_MS);
});

watch(isOpen, async (open) => {
    if (!open) {
        if (debounce) clearTimeout(debounce);
        inFlight?.abort();
        query.value = '';
        results.value = [];
        loading.value = false;
        activeIndex.value = 0;
        return;
    }

    await nextTick();
    input.value?.focus();
});

// 639px is the pixel below Tailwind's `sm`, so the JS and the classes switch
// on the same boundary.
const phone = typeof window === 'undefined' ? null : window.matchMedia('(max-width: 639px)');
const trackViewport = (event: MediaQueryListEvent | MediaQueryList) => {
    compact.value = event.matches;
};

onMounted(() => {
    if (!phone) return;
    trackViewport(phone);
    phone.addEventListener('change', trackViewport);
});

onBeforeUnmount(() => {
    if (debounce) clearTimeout(debounce);
    inFlight?.abort();
    phone?.removeEventListener('change', trackViewport);
});

const scrollActiveIntoView = async () => {
    await nextTick();
    document.getElementById(optionId(activeIndex.value))?.scrollIntoView({ block: 'nearest' });
};

const move = (delta: number) => {
    const total = rows.value.length;
    if (total === 0) return;

    // Wrap, so Down from the last row returns to the first.
    activeIndex.value = (activeIndex.value + delta + total) % total;
    scrollActiveIntoView();
};

const choose = (row?: PaletteRow) => {
    const target = row ?? rows.value[activeIndex.value];
    if (!target) return;

    close();
    router.visit(target.url);
};

const onKeydown = (event: KeyboardEvent) => {
    switch (event.key) {
        case 'ArrowDown':
            event.preventDefault();
            move(1);
            break;
        case 'ArrowUp':
            event.preventDefault();
            move(-1);
            break;
        case 'Home':
            event.preventDefault();
            activeIndex.value = 0;
            scrollActiveIntoView();
            break;
        case 'End':
            event.preventDefault();
            activeIndex.value = Math.max(0, rows.value.length - 1);
            scrollActiveIntoView();
            break;
        case 'Enter':
            event.preventDefault();
            choose();
            break;
    }
};

/** Backend group key → the locale key the sidebar already uses for that module. */
const GROUP_LABEL_KEYS: Record<string, string> = {
    media: 'dynamic_storage',
    dev_settings: 'developer_settings',
};

const groupLabel = (key: string) => t(GROUP_LABEL_KEYS[key] ?? key);

const showEmptyState = computed(() => !loading.value && rows.value.length === 0 && query.value.trim() !== '');
</script>

<template>
    <BaseModal :open="isOpen" size="lg" align="top" :hide-close="!compact" class="p-4 sm:max-w-xl sm:p-6" @close="close">
        <template #header>
            <div class="flex items-center gap-3">
                <Search class="size-5 shrink-0 text-muted-foreground" aria-hidden="true" />
                <input
                    ref="input"
                    v-model="query"
                    type="search"
                    role="combobox"
                    autocomplete="off"
                    autocapitalize="off"
                    autocorrect="off"
                    spellcheck="false"
                    enterkeyhint="go"
                    :aria-label="t('search_everything')"
                    :aria-expanded="rows.length > 0"
                    :aria-controls="listId"
                    :aria-activedescendant="rows.length ? optionId(activeIndex) : undefined"
                    :placeholder="t('search_everything')"
                    class="min-w-0 flex-1 bg-transparent text-base text-foreground placeholder:text-muted-foreground focus:outline-none"
                    @keydown="onKeydown"
                />
                <kbd class="hidden shrink-0 rounded border border-border px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground sm:inline">
                    ESC
                </kbd>
            </div>
        </template>

        <div v-if="loading && rows.length === 0" class="space-y-2 py-2">
            <div v-for="row in 4" :key="row" class="h-10 w-full animate-pulse rounded-md bg-muted"></div>
        </div>

        <div v-else-if="showEmptyState" class="flex flex-col items-center gap-2 px-4 py-12 text-center">
            <Search class="size-10 text-muted-foreground/40" aria-hidden="true" />
            <p class="text-sm font-medium text-foreground">{{ t('no_results_found') }}</p>
            <p class="text-xs text-muted-foreground">{{ t('try_adjusting_filters') }}</p>
        </div>

        <ul v-else :id="listId" role="listbox" :aria-label="t('search_everything')" class="-mx-1 space-y-4">
            <li v-for="group in renderGroups" :key="group.key + group.rows[0].index" role="presentation">
                <p class="px-3 pb-1 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                    {{ groupLabel(group.key) }}
                </p>

                <ul role="presentation" class="space-y-0.5">
                    <li
                        v-for="row in group.rows"
                        :id="optionId(row.index)"
                        :key="row.key"
                        role="option"
                        :aria-selected="row.index === activeIndex"
                        class="flex min-h-11 cursor-pointer items-center gap-3 rounded-md px-3 py-2.5 transition-colors sm:min-h-0 sm:py-2"
                        :class="row.index === activeIndex ? 'bg-accent text-accent-foreground' : 'hover:bg-accent/50'"
                        @click="choose(row)"
                        @mousemove="activeIndex = row.index"
                    >
                        <component :is="row.icon" class="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />

                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium text-foreground">{{ row.title }}</span>
                            <span v-if="row.subtitle" class="block truncate text-xs text-muted-foreground">{{ row.subtitle }}</span>
                        </span>

                        <CornerDownLeft
                            v-if="row.index === activeIndex"
                            class="hidden size-3.5 shrink-0 text-muted-foreground sm:block"
                            aria-hidden="true"
                        />
                    </li>
                </ul>
            </li>
        </ul>

        <template #footer>
            <p v-if="!compact" class="flex w-full flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                <span class="flex items-center gap-1.5">
                    <kbd class="rounded border border-border px-1.5 py-0.5 font-medium">↑</kbd>
                    <kbd class="rounded border border-border px-1.5 py-0.5 font-medium">↓</kbd>
                    {{ t('search_hint_navigate') }}
                </span>
                <span class="flex items-center gap-1.5">
                    <kbd class="rounded border border-border px-1.5 py-0.5 font-medium">↵</kbd>
                    {{ t('search_hint_open') }}
                </span>
            </p>
        </template>
    </BaseModal>
</template>
