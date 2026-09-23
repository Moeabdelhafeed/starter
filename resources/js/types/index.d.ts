/** An asset serialized by the Image morph model (see "Media serialization" in CLAUDE.md). */
export interface ImageAsset {
    id: number;
    url: string;
    type: string;
    blurhash: string | null;
    image_api: string;
}

/**
 * The signed-in admin, exactly as HandleInertiaRequests shares it.
 *
 * This is deliberately narrow: the middleware ships these four fields and no
 * more, so a page needing another column must pass it from its own controller.
 */
export interface AuthUser {
    id: number;
    name: string;
    email: string;
    image: ImageAsset | null;
}

/** A record row that may carry extra columns a given page selected. */
export interface User {
    id: number;
    name: string;
    email: string | null;
    image?: ImageAsset | null;
    verified_at?: string | null;
    is_active?: boolean;
    deleted_at?: string | null;
    created_at?: string;
    updated_at?: string;
    [key: string]: unknown; // This allows for additional properties...
}

export interface Role {
    id: number;
    name: string;
    guard_name?: string;
    is_active?: boolean;
    permissions?: Permission[];
    users_count?: number;
}

export interface Permission {
    id: number;
    name: string;
}

/** Laravel's length-aware paginator, as it arrives in an Inertia prop. */
export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    next_page_url: string | null;
    prev_page_url: string | null;
    links?: Array<{ url: string | null; label: string; active: boolean }>;
}

/**
 * An admin notification row, as serialized by App\Models\AdminNotification
 * and broadcast by the notification.created Pusher event.
 */
export interface AdminNotification {
    id: string;
    type: string;
    title: string;
    message?: string | null;
    read_at: string | null;
    created_at: string;
    target?: { route?: string | null; highlight?: number | string | null } | null;
}

/** Which identity fields this install exposes (see config/auth.php). */
export interface AuthFields {
    email: boolean;
    phone: boolean;
    username: boolean;
}

export interface Auth {
    user: AuthUser | null;
    roles: string[];
    permissions: string[];
}

export interface AppLocale {
    code: string;
    dir: 'ltr' | 'rtl';
    name?: string;
}

/** Count of records with incomplete translations, keyed by feature. */
export interface TranslationWarnings {
    pages?: number;
    app_settings?: number;
    [feature: string]: number | undefined;
}

/**
 * Everything HandleInertiaRequests shares with every page.
 * Feature flags mirror the HAS_* env flags documented in CLAUDE.md.
 */
export interface SharedProps {
    name: string;
    auth: Auth;
    locale: AppLocale;
    success: string | null;
    error: string | null;
    is_local: boolean;
    is_testing: boolean;
    app_users: boolean;
    app_guests: boolean;
    has_translations: boolean;
    has_pages: boolean;
    has_app_settings: boolean;
    has_activity_logs: boolean;
    has_dynamic_storage: boolean;
    has_notification_templates: boolean;
    auth_fields: AuthFields;
    auth_identifiers: string[];
    multi_session: boolean;
    translation_warnings: TranslationWarnings;
    notifications: { unread_count: number };
}

/** Value of a single filter in an admin list's `filters` prop / filter-change emit. */
export type FilterValue = string | number | null | undefined;

/** A `{ value, label }` pair for a filter/select dropdown. */
export interface SelectOption {
    value: string;
    label: string;
}

/** A translated field value, as HasTranslations serializes the `translations` relation. */
export interface TranslationEntry {
    field: string;
    locale: string;
    value: string;
}

/**
 * An active language, as the admin controllers select it:
 * `Language::active()->get(['id', 'code', 'name', 'native_name', 'direction'])`.
 * `is_default` is only present where the full model is loaded (Translations page),
 * hence optional — do not rely on it outside that page.
 */
export interface Language {
    id: number;
    code: string;
    name: string;
    native_name: string;
    direction: 'ltr' | 'rtl';
    is_default?: boolean;
}

/** A row of the Languages CRUD: the whole Language model plus its eager-loaded Image morph. */
export interface LanguageRow {
    id: number;
    code: string;
    name: string;
    native_name: string;
    direction: 'ltr' | 'rtl';
    is_active: boolean;
    is_default: boolean;
    image?: { image_api?: string | null } | null;
}

/** An installable locale offered by LanguageController::getAvailableLocales() — no id, it is not a row yet. */
export interface AvailableLocale {
    code: string;
    name: string;
    native_name: string;
    direction: 'ltr' | 'rtl';
}

/**
 * A row of the Translations CMS, built by Admin/TranslationController::index():
 * the key columns plus one string per active locale code, keyed by that code.
 */
export interface TranslationRow {
    id: number;
    key: string;
    group: string;
    sub_group?: string | null;
    [locale: string]: unknown;
}

/** Query filters of the Translations CMS list. */
export interface TranslationFilterState {
    search?: string | null;
    group?: string | null;
    sub_group?: string | null;
    [key: string]: string | null | undefined;
}

/**
 * The asset nested inside a MediaRow — one of Image/Video/MediaFile `toApiArray()`
 * (see "Media serialization" in CLAUDE.md). Every field is optional because which
 * ones arrive depends on the row's `type`: `image_api` + `blurhash` for an image,
 * `video_api` + `thumbnail` for a video, `file_api` + `name` + `size` for a file.
 */
export interface MediaAsset {
    id?: number;
    url?: string;
    type?: string;
    name?: string;
    size?: number;
    image_api?: string;
    video_api?: string;
    file_api?: string;
    thumbnail?: { image_api?: string } | null;
}

/** A Dynamic Storage row: the key columns merged with `MediaItem::toApi()` (only the morph matching `type` is set). */
export interface MediaRow {
    id: number;
    key: string;
    group: string;
    sub_group?: string | null;
    type: 'image' | 'video' | 'file';
    image?: MediaAsset | null;
    video?: MediaAsset | null;
    file?: MediaAsset | null;
}

/** Query filters of the Dynamic Storage media list. */
export interface MediaFilterState {
    search?: string | null;
    group?: string | null;
    sub_group?: string | null;
    [key: string]: string | null | undefined;
}

/** An AppSetting model with its translations + image loaded (`text_api`/`missing_translations` come from HasTranslations::toArray). */
export interface AppSettingItem {
    id: number;
    type: string;
    url?: string | null;
    text_api?: string | null;
    is_active: boolean;
    image?: { image_api?: string | null } | null;
    missing_translations?: string[];
    translations: TranslationEntry[];
}

/** A Page model with its translations + image loaded (`name_api`/`missing_translations` come from HasTranslations::toArray). */
export interface PageRow {
    id: number;
    slug: string;
    is_active: boolean;
    /** Appended by the model: the slug is listed in PROTECTED_PAGES, so it cannot be deleted or renamed. */
    is_protected: boolean;
    name_api: string;
    image?: { image_api?: string | null } | null;
    missing_translations?: string[];
    translations: TranslationEntry[];
}

/** An FCM topic offered by FcmTopics::structured(). */
export interface Topic {
    name: string;
    lang?: string | null;
}

/** A model that can trigger a notification template, from NotificationModelRegistry::all(). */
export interface TriggerModel {
    class: string;
    label: string;
}

/** A NotificationTemplate model with its translations loaded (`title_api` comes from HasTranslations::toArray). */
export interface NotificationTemplateRow {
    id: number;
    slug: string;
    /** Non-null when application code sends this notification by looking the row up: copy editable, wiring frozen, never deletable. */
    system_type?: string | null;
    title_api: string;
    topic: string;
    trigger_model?: string | null;
    trigger_event?: string | null;
    is_active: boolean;
    last_sent_at?: string | null;
    translations: TranslationEntry[];
}

export type AppPageProps<T extends Record<string, unknown> = Record<string, unknown>> = T &
    SharedProps & {
        [key: string]: unknown;
    };

declare module '@inertiajs/core' {
    // Intentionally empty: this only widens Inertia's PageProps with ours, so
    // usePage().props is typed everywhere without a cast.
    // eslint-disable-next-line @typescript-eslint/no-empty-object-type
    interface PageProps extends AppPageProps {}
}
