/**
 * What the DevSettings search box searches.
 *
 * The panel renders one section at a time (`v-if`, not `v-show`) because some sections
 * are heavy, so a search cannot read labels out of the DOM — nothing but the open
 * section is mounted. This list is that missing index: one entry per `SettingsCard`,
 * naming the section it lives in and the `anchor` that card renders as its element id.
 *
 * `label` is an i18n key, so results are translated and always read the same as the
 * card's own heading. `keywords` is deliberately NOT translated: it holds the env var
 * names and the English jargon a developer actually types — someone hunting for the
 * flag they half-remember searches `HAS_PAGES` or `pusher`, not "الصفحات".
 *
 * Adding a card? Add its entry here and give the card a matching `anchor`. A card
 * missing from this list is simply unreachable by search, with no error anywhere.
 */
export type SettingEntry = {
    /** Menu id in `Index.vue`'s `menuItems`. */
    section: string;
    /** The card's `anchor` prop; the DOM id is `setting-${anchor}`. */
    anchor: string;
    /** i18n key, the same one the card's `title` uses. */
    label: string;
    /** Untranslated search terms: env keys, endpoint names, developer jargon. */
    keywords: string;
};

/**
 * Section id → its i18n label key. Lives here rather than only in `Index.vue`'s
 * `menuItems` because the command palette also names the section a result sits in,
 * and two lists of the same nine labels drift.
 */
export const SECTION_LABELS: Record<string, string> = {
    general: 'general_settings',
    appearance: 'appearance',
    environment: 'environment',
    authentication: 'authentication',
    mail: 'mail_settings',
    broadcasting: 'broadcasting',
    notifications: 'fcm_notifications',
    data: 'data_and_limits',
    deployment: 'deployment',
    databases: 'databases',
};

export const settingsIndex: SettingEntry[] = [
    // General
    { section: 'general', anchor: 'app-name', label: 'app_name', keywords: 'APP_NAME title rename project' },
    { section: 'general', anchor: 'branding', label: 'branding', keywords: 'logo dark logo favicon icon image upload brand' },
    { section: 'general', anchor: 'actions', label: 'actions', keywords: 'build assets npm vite api docs scribe postman openapi cache clear' },

    // Appearance
    {
        section: 'appearance',
        anchor: 'light-colors',
        label: 'light_theme_colors',
        keywords: 'theme colours palette primary css variables light mode tokens',
    },
    { section: 'appearance', anchor: 'dark-colors', label: 'dark_theme_colors', keywords: 'theme colours palette dark mode css variables tokens' },

    // Environment
    {
        section: 'environment',
        anchor: 'env-toggles',
        label: 'env_toggles',
        keywords:
            'APP_USERS APP_GUESTS HAS_TRANSLATIONS HAS_NOTIFICATION_TEMPLATES HAS_PAGES HAS_APP_SETTINGS HAS_DYNAMIC_STORAGE HAS_ACTIVITY_LOGS IS_TESTING APP_DEBUG IS_OTP_WHATSAPP feature flags modules switches testing debug whatsapp sms',
    },
    { section: 'environment', anchor: 'urls', label: 'urls', keywords: 'APP_URL FRONTEND_URL domain host localhost base url' },
    {
        section: 'environment',
        anchor: 'protected-pages',
        label: 'protected_pages',
        keywords: 'PROTECTED_PAGES terms privacy slug seeder locked undeletable cms',
    },

    // Authentication
    {
        section: 'authentication',
        anchor: 'auth-config',
        label: 'auth_config',
        keywords:
            'AUTH_MODE AUTH_IDENTIFIERS HAS_EMAIL_FIELD HAS_PHONE_FIELD HAS_USERNAME_FIELD identifier email phone username otp password login register verification',
    },
    {
        section: 'authentication',
        anchor: 'social-auth',
        label: 'social_auth_config',
        keywords: 'SOCIAL_AUTH_PROVIDERS SOCIAL_AUTH_MAX_ACCOUNTS google apple firebase sign in social link account',
    },
    {
        section: 'authentication',
        anchor: 'admin-credentials',
        label: 'admin_credentials',
        keywords: 'ADMIN_EMAIL ADMIN_PASSWORD super admin seeder login password',
    },
    {
        section: 'authentication',
        anchor: 'api-token',
        label: 'api_token',
        keywords: 'APP_X_API_TOKEN X-API-TOKEN header secret mobile token generate',
    },
    {
        section: 'authentication',
        anchor: 'reviewer-accounts',
        label: 'reviewer_accounts',
        keywords: 'app store review apple google test account reviewer bypass',
    },
    {
        section: 'authentication',
        anchor: 'sessions',
        label: 'sessions_settings',
        keywords: 'MULTI_SESSION_ENABLED devices multi session logout revoke',
    },

    // Mail
    {
        section: 'mail',
        anchor: 'mail',
        label: 'mail_settings',
        keywords: 'MAIL_MAILER MAIL_HOST MAIL_PORT MAIL_USERNAME MAIL_PASSWORD MAIL_FROM_ADDRESS smtp email sending mailtrap',
    },

    // Broadcasting
    {
        section: 'broadcasting',
        anchor: 'pusher',
        label: 'pusher_settings',
        keywords: 'PUSHER_APP_ID PUSHER_APP_KEY PUSHER_APP_SECRET PUSHER_APP_CLUSTER BROADCAST_CONNECTION echo websockets realtime channel',
    },
    { section: 'broadcasting', anchor: 'test-broadcast', label: 'test_broadcast', keywords: 'pusher test event channel realtime ping' },

    // Notifications
    {
        section: 'notifications',
        anchor: 'firebase',
        label: 'firebase_config',
        keywords: 'FIREBASE_CREDENTIALS service account json fcm push notification upload',
    },
    {
        section: 'notifications',
        anchor: 'fcm-topics',
        label: 'fcm_topics',
        keywords: 'FCM_TOPICS users guests topic push subscribe language variant',
    },

    // Data & limits
    {
        section: 'data',
        anchor: 'validation',
        label: 'validation_settings',
        keywords: 'ALLOWED_PHONE_COUNTRIES ALLOWED_EMAIL_DOMAINS MEDIA_MAX_IMAGE_KB MEDIA_MAX_VIDEO_KB MEDIA_MAX_FILE_KB upload size country domain',
    },
    {
        section: 'data',
        anchor: 'rate-limiting',
        label: 'rate_limiting',
        keywords: 'RATE_LIMIT_API RATE_LIMIT_AUTH RATE_LIMIT_OTP RATE_LIMIT_API_DECAY throttle requests per minute',
    },
    {
        section: 'data',
        anchor: 'account-deletion',
        label: 'account_deletion_config',
        keywords: 'ACCOUNT_DELETION_RETENTION_DAYS purge soft delete retention',
    },

    // Deployment
    { section: 'deployment', anchor: 'github', label: 'github', keywords: 'git repo branch commit push pull remote clone origin diff' },
    {
        section: 'deployment',
        anchor: 'hostinger',
        label: 'hostinger_api',
        keywords: 'HOSTINGER_API_TOKEN provision website database subdomain hpanel',
    },
    {
        section: 'deployment',
        anchor: 'deploy-targets',
        label: 'deployment_targets',
        keywords: 'flavor flavours dev staging uat production ssh deploy seeders migrate ALLOW_CONTENT_SEEDING cron scheduler document root',
    },

    // Databases
    {
        section: 'databases',
        anchor: 'databases',
        label: 'databases',
        keywords: 'database mysql phpmyadmin sql hostinger tables dump size db',
    },
];

/**
 * Case-insensitive AND match over the translated label and the raw keywords: every
 * whitespace-separated term has to appear somewhere, so `pages protected` finds the same
 * card as `protected pages` and a second word narrows instead of widening.
 */
export const searchSettings = (query: string, translate: (key: string) => string): SettingEntry[] => {
    const terms = query.toLowerCase().split(/\s+/).filter(Boolean);

    if (terms.length === 0) {
        return [];
    }

    return settingsIndex.filter((entry) => {
        const haystack = `${translate(entry.label)} ${entry.keywords} ${entry.section}`.toLowerCase();

        return terms.every((term) => haystack.includes(term));
    });
};
