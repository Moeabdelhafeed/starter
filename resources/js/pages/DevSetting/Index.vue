<script setup>
import Default from '@/layouts/default.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Loader2, Hammer, Sun, Moon, Flame, CheckCircle, XCircle, Send, ImageIcon, Clipboard, Check, ShieldCheck, GitBranch, Database, Rocket, Mail, Link2, Server, KeyRound, RefreshCw, Download, GitPullRequest, GitCommit, Plus, ChevronDown, ChevronRight, FileCode, FilePlus, FileEdit, ArrowUpCircle, ArrowDownCircle, Shield, X, Search, Settings, Palette, Bell, Lock, Globe, ToggleLeft, Users, Hash } from 'lucide-vue-next';
import Checkbox from '@/components/ui/checkbox/Checkbox.vue';
import Input from '@/components/ui/input/Input.vue';
import Button from '@/components/ui/button/Button.vue';
import ImageUpload from '@/components/ui/image-upload/ImageUpload.vue';

defineOptions({
    layout: Default,
});

const { t } = useI18n();

const props = defineProps({
    lightColors: Object,
    darkColors: Object,
    envValues: Object,
    envToggles: Array,
    firebaseConfigExists: Boolean,
    firebaseCredentialsPath: String,
    authConfig: Object,
    socialAuthConfig: Object,
    validationConfig: Object,
    pusherConfig: Object,
    rateLimitConfig: Object,
    accountDeletionConfig: Object,
    sessionsConfig: Object,
    topicsConfig: Object,
    reviewerAccounts: Object,
    git: Object,
    productionDb: Object,
    productionMail: Object,
    localMail: Object,
    productionTesting: { type: Boolean, default: null },
    urls: Object,
    deployConfig: Object,
    deployLog: { type: String, default: null },
    adminCredentials: Object,
    apiToken: Object,
    appName: String,
});

// Sidebar Navigation
const menuItems = [
    { id: 'general', icon: Settings, label: 'general_settings' },
    { id: 'deployment', icon: Rocket, label: 'deployment' },
    { id: 'authentication', icon: Lock, label: 'authentication' },
    { id: 'reviewers', icon: Shield, label: 'reviewer_accounts' },
    { id: 'sessions', icon: KeyRound, label: 'sessions_settings' },
    { id: 'notifications', icon: Bell, label: 'fcm_notifications' },
    { id: 'topics', icon: Hash, label: 'fcm_topics' },
    { id: 'pusher', icon: Send, label: 'broadcasting' },
    { id: 'validation', icon: ShieldCheck, label: 'validation_settings' },
    { id: 'mail', icon: Mail, label: 'mail_settings' },
    { id: 'appearance', icon: Palette, label: 'appearance' },
    { id: 'environment', icon: ToggleLeft, label: 'environment' },
];

const validSections = menuItems.map((m) => m.id);

const sectionFromHash = () => {
    const hash = (typeof window !== 'undefined' ? window.location.hash : '').replace(/^#/, '');
    return validSections.includes(hash) ? hash : 'general';
};

const activeSection = ref(sectionFromHash());

if (typeof window !== 'undefined') {
    window.addEventListener('hashchange', () => {
        activeSection.value = sectionFromHash();
    });

    watch(activeSection, (val) => {
        if (window.location.hash.replace(/^#/, '') !== val) {
            history.replaceState(null, '', `#${val}`);
        }
    });
}

// Convert oklch to approximate hex for the color picker display
const oklchToHex = (oklch) => {
    if (!oklch || !oklch.startsWith('oklch')) return '#000000';
    const match = oklch.match(/oklch\(([\d.%]+)\s+([\d.]+)\s+([\d.]+)\)/);
    if (!match) return '#000000';

    let L = parseFloat(match[1]);
    if (match[1].includes('%')) L = L / 100;
    const C = parseFloat(match[2]);
    const H = parseFloat(match[3]);

    const hRad = (H * Math.PI) / 180;
    const a = C * Math.cos(hRad);
    const b = C * Math.sin(hRad);

    const l_ = L + 0.3963377774 * a + 0.2158037573 * b;
    const m_ = L - 0.1055613458 * a - 0.0638541728 * b;
    const s_ = L - 0.0894841775 * a - 1.2914855480 * b;

    const l = l_ * l_ * l_;
    const m = m_ * m_ * m_;
    const s = s_ * s_ * s_;

    let r = +4.0767416621 * l - 3.3077115913 * m + 0.2309699292 * s;
    let g = -1.2684380046 * l + 2.6097574011 * m - 0.3413193965 * s;
    let bl = -0.0041960863 * l - 0.7034186147 * m + 1.7076147010 * s;

    const toSrgb = (c) => {
        c = Math.max(0, Math.min(1, c));
        return c <= 0.0031308 ? 12.92 * c : 1.055 * Math.pow(c, 1 / 2.4) - 0.055;
    };

    r = Math.round(toSrgb(r) * 255);
    g = Math.round(toSrgb(g) * 255);
    bl = Math.round(toSrgb(bl) * 255);

    return '#' + [r, g, bl].map(v => Math.max(0, Math.min(255, v)).toString(16).padStart(2, '0')).join('');
};

// Light colors
const lightHex = ref({});
Object.entries(props.lightColors || {}).forEach(([key, val]) => {
    lightHex.value[key] = oklchToHex(val);
});

const lightForm = useForm({ colors: {}, mode: 'light' });

const submitLight = () => {
    lightForm.colors = { ...lightHex.value };
    lightForm.put(route('dev_settings.colors'), {
        preserveScroll: true,
        preserveState: true,
        reset: ['lightColors', 'success', 'error'],
    });
};

// Dark colors
const darkHex = ref({});
Object.entries(props.darkColors || {}).forEach(([key, val]) => {
    darkHex.value[key] = oklchToHex(val);
});

const darkForm = useForm({ colors: {}, mode: 'dark' });

const submitDark = () => {
    darkForm.colors = { ...darkHex.value };
    darkForm.put(route('dev_settings.colors'), {
        preserveScroll: true,
        preserveState: true,
        reset: ['darkColors', 'success', 'error'],
    });
};

// Build
const building = ref(false);
const triggerBuild = () => {
    building.value = true;
    router.post(route('dev_settings.build'), {}, {
        preserveScroll: true,
        onFinish: () => { building.value = false; },
    });
};

// Env toggles
const envToggling = ref({});
const toggleEnv = (key, currentValue) => {
    envToggling.value[key] = true;
    const newValue = !currentValue;
    router.put(route('dev_settings.env'), {
        key: key,
        value: newValue,
    }, {
        preserveScroll: true,
        preserveState: true,
        only: ['envValues', 'success', 'error'],
        onFinish: () => { envToggling.value[key] = false; },
    });
};

const prodEnvToggling = ref({});
const toggleProdEnv = (key, currentValue) => {
    prodEnvToggling.value[key] = true;
    router.put(route('dev_settings.production_env'), {
        key: key,
        value: !currentValue,
    }, {
        preserveScroll: true,
        onFinish: () => { prodEnvToggling.value[key] = false; },
    });
};

const envLabel = (key) => {
    const labels = {
        'APP_USERS': 'App Users Module',
        'APP_GUESTS': 'App Guests Module',
        'HAS_TRANSLATIONS': 'App Translations',
        'HAS_NOTIFICATION_TEMPLATES': 'Notification Templates',
        'HAS_PAGES': 'Pages',
        'HAS_ACTIVITY_LOGS': 'Activity Logs',
        'IS_TESTING': 'Testing Mode',
        'APP_DEBUG': 'Debug Mode',
        'IS_OTP_WHATSAPP': 'OTP via WhatsApp',
    };
    return labels[key] || key;
};

const envDescription = (key) => {
    const desc = {
        'APP_USERS': 'Enable/disable the app users (API guard) module and routes',
        'APP_GUESTS': 'Enable/disable lazy guest user creation in IdentifyDevice middleware. When off, X-Device-Id + X-Platform headers are still required but no guest row is created.',
        'HAS_TRANSLATIONS': 'Enable/disable app translations feature (admin routes, navbar links, API endpoints for translations/languages)',
        'HAS_NOTIFICATION_TEMPLATES': 'Enable/disable the notification templates feature (admin routes and navbar link). Some apps do not need it.',
        'HAS_PAGES': 'Enable/disable the pages feature (admin CRUD, public /p/{slug} page, and API page endpoints).',
        'HAS_ACTIVITY_LOGS': 'Enable/disable the activity logs admin feature (routes + navbar link). Models still record logs; only the admin viewer is hidden.',
        'IS_TESTING': 'Enable/disable testing mode for the application',
        'APP_DEBUG': 'Enable/disable detailed error pages and debug info',
        'IS_OTP_WHATSAPP': 'If true, OTPs sent via WhatsApp. If false, sent via SMS. Only applies when identifier is phone.',
    };
    return desc[key] || '';
};

// Auth Config
const authForm = useForm({
    identifiers: props.authConfig?.identifiers || ['email'],
    has_email_field: props.authConfig?.has_email_field ?? true,
    has_phone_field: props.authConfig?.has_phone_field ?? false,
    has_username_field: props.authConfig?.has_username_field ?? false,
    auth_mode: props.authConfig?.auth_mode || 'password',
});

const toggleIdentifier = (value) => {
    const idx = authForm.identifiers.indexOf(value);
    if (idx > -1) {
        if (authForm.identifiers.length > 1) {
            authForm.identifiers.splice(idx, 1);
        }
    } else {
        authForm.identifiers.push(value);
    }
};

const submitAuth = () => {
    authForm.put(route('dev_settings.auth'), {
        preserveScroll: true,
        preserveState: true,
        reset: ['authConfig', 'success', 'error'],
    });
};

// Social Auth Config
const availableProviders = [
    { id: 'google.com', name: 'Google' },
    { id: 'apple.com', name: 'Apple' },
    { id: 'facebook.com', name: 'Facebook' },
    { id: 'twitter.com', name: 'Twitter' },
    { id: 'github.com', name: 'GitHub' },
];

const socialAuthForm = useForm({
    providers: props.socialAuthConfig?.providers || [],
    max_accounts: props.socialAuthConfig?.max_accounts ?? 0,
});

const toggleProvider = (providerId) => {
    const idx = socialAuthForm.providers.indexOf(providerId);
    if (idx > -1) {
        socialAuthForm.providers.splice(idx, 1);
    } else {
        socialAuthForm.providers.push(providerId);
    }
};

const submitSocialAuth = () => {
    socialAuthForm.put(route('dev_settings.social_auth'), {
        preserveScroll: true,
        preserveState: true,
        reset: ['socialAuthConfig', 'success', 'error'],
    });
};

// Validation Config
const validationForm = useForm({
    allowed_phone_countries: props.validationConfig?.allowed_phone_countries || 'all',
    allowed_email_domains: props.validationConfig?.allowed_email_domains || 'all',
});

// All countries list (ISO 3166-1 alpha-2)
const allCountries = [
    { code: 'AF', name: 'Afghanistan' },
    { code: 'AL', name: 'Albania' },
    { code: 'DZ', name: 'Algeria' },
    { code: 'AD', name: 'Andorra' },
    { code: 'AO', name: 'Angola' },
    { code: 'AG', name: 'Antigua & Barbuda' },
    { code: 'AR', name: 'Argentina' },
    { code: 'AM', name: 'Armenia' },
    { code: 'AU', name: 'Australia' },
    { code: 'AT', name: 'Austria' },
    { code: 'AZ', name: 'Azerbaijan' },
    { code: 'BS', name: 'Bahamas' },
    { code: 'BH', name: 'Bahrain' },
    { code: 'BD', name: 'Bangladesh' },
    { code: 'BB', name: 'Barbados' },
    { code: 'BY', name: 'Belarus' },
    { code: 'BE', name: 'Belgium' },
    { code: 'BZ', name: 'Belize' },
    { code: 'BJ', name: 'Benin' },
    { code: 'BT', name: 'Bhutan' },
    { code: 'BO', name: 'Bolivia' },
    { code: 'BA', name: 'Bosnia' },
    { code: 'BW', name: 'Botswana' },
    { code: 'BR', name: 'Brazil' },
    { code: 'BN', name: 'Brunei' },
    { code: 'BG', name: 'Bulgaria' },
    { code: 'BF', name: 'Burkina Faso' },
    { code: 'BI', name: 'Burundi' },
    { code: 'CV', name: 'Cabo Verde' },
    { code: 'KH', name: 'Cambodia' },
    { code: 'CM', name: 'Cameroon' },
    { code: 'CA', name: 'Canada' },
    { code: 'CF', name: 'Central African Rep.' },
    { code: 'TD', name: 'Chad' },
    { code: 'CL', name: 'Chile' },
    { code: 'CN', name: 'China' },
    { code: 'CO', name: 'Colombia' },
    { code: 'KM', name: 'Comoros' },
    { code: 'CG', name: 'Congo' },
    { code: 'CD', name: 'Congo (DRC)' },
    { code: 'CR', name: 'Costa Rica' },
    { code: 'CI', name: 'Côte d\'Ivoire' },
    { code: 'HR', name: 'Croatia' },
    { code: 'CU', name: 'Cuba' },
    { code: 'CY', name: 'Cyprus' },
    { code: 'CZ', name: 'Czechia' },
    { code: 'DK', name: 'Denmark' },
    { code: 'DJ', name: 'Djibouti' },
    { code: 'DM', name: 'Dominica' },
    { code: 'DO', name: 'Dominican Rep.' },
    { code: 'EC', name: 'Ecuador' },
    { code: 'EG', name: 'Egypt' },
    { code: 'SV', name: 'El Salvador' },
    { code: 'GQ', name: 'Equatorial Guinea' },
    { code: 'ER', name: 'Eritrea' },
    { code: 'EE', name: 'Estonia' },
    { code: 'SZ', name: 'Eswatini' },
    { code: 'ET', name: 'Ethiopia' },
    { code: 'FJ', name: 'Fiji' },
    { code: 'FI', name: 'Finland' },
    { code: 'FR', name: 'France' },
    { code: 'GA', name: 'Gabon' },
    { code: 'GM', name: 'Gambia' },
    { code: 'GE', name: 'Georgia' },
    { code: 'DE', name: 'Germany' },
    { code: 'GH', name: 'Ghana' },
    { code: 'GR', name: 'Greece' },
    { code: 'GD', name: 'Grenada' },
    { code: 'GT', name: 'Guatemala' },
    { code: 'GN', name: 'Guinea' },
    { code: 'GW', name: 'Guinea-Bissau' },
    { code: 'GY', name: 'Guyana' },
    { code: 'HT', name: 'Haiti' },
    { code: 'HN', name: 'Honduras' },
    { code: 'HU', name: 'Hungary' },
    { code: 'IS', name: 'Iceland' },
    { code: 'IN', name: 'India' },
    { code: 'ID', name: 'Indonesia' },
    { code: 'IR', name: 'Iran' },
    { code: 'IQ', name: 'Iraq' },
    { code: 'IE', name: 'Ireland' },
    { code: 'IL', name: 'Israel' },
    { code: 'IT', name: 'Italy' },
    { code: 'JM', name: 'Jamaica' },
    { code: 'JP', name: 'Japan' },
    { code: 'JO', name: 'Jordan' },
    { code: 'KZ', name: 'Kazakhstan' },
    { code: 'KE', name: 'Kenya' },
    { code: 'KI', name: 'Kiribati' },
    { code: 'KP', name: 'North Korea' },
    { code: 'KR', name: 'South Korea' },
    { code: 'KW', name: 'Kuwait' },
    { code: 'KG', name: 'Kyrgyzstan' },
    { code: 'LA', name: 'Laos' },
    { code: 'LV', name: 'Latvia' },
    { code: 'LB', name: 'Lebanon' },
    { code: 'LS', name: 'Lesotho' },
    { code: 'LR', name: 'Liberia' },
    { code: 'LY', name: 'Libya' },
    { code: 'LI', name: 'Liechtenstein' },
    { code: 'LT', name: 'Lithuania' },
    { code: 'LU', name: 'Luxembourg' },
    { code: 'MG', name: 'Madagascar' },
    { code: 'MW', name: 'Malawi' },
    { code: 'MY', name: 'Malaysia' },
    { code: 'MV', name: 'Maldives' },
    { code: 'ML', name: 'Mali' },
    { code: 'MT', name: 'Malta' },
    { code: 'MH', name: 'Marshall Islands' },
    { code: 'MR', name: 'Mauritania' },
    { code: 'MU', name: 'Mauritius' },
    { code: 'MX', name: 'Mexico' },
    { code: 'FM', name: 'Micronesia' },
    { code: 'MD', name: 'Moldova' },
    { code: 'MC', name: 'Monaco' },
    { code: 'MN', name: 'Mongolia' },
    { code: 'ME', name: 'Montenegro' },
    { code: 'MA', name: 'Morocco' },
    { code: 'MZ', name: 'Mozambique' },
    { code: 'MM', name: 'Myanmar' },
    { code: 'NA', name: 'Namibia' },
    { code: 'NR', name: 'Nauru' },
    { code: 'NP', name: 'Nepal' },
    { code: 'NL', name: 'Netherlands' },
    { code: 'NZ', name: 'New Zealand' },
    { code: 'NI', name: 'Nicaragua' },
    { code: 'NE', name: 'Niger' },
    { code: 'NG', name: 'Nigeria' },
    { code: 'MK', name: 'North Macedonia' },
    { code: 'NO', name: 'Norway' },
    { code: 'OM', name: 'Oman' },
    { code: 'PK', name: 'Pakistan' },
    { code: 'PW', name: 'Palau' },
    { code: 'PS', name: 'Palestine' },
    { code: 'PA', name: 'Panama' },
    { code: 'PG', name: 'Papua New Guinea' },
    { code: 'PY', name: 'Paraguay' },
    { code: 'PE', name: 'Peru' },
    { code: 'PH', name: 'Philippines' },
    { code: 'PL', name: 'Poland' },
    { code: 'PT', name: 'Portugal' },
    { code: 'QA', name: 'Qatar' },
    { code: 'RO', name: 'Romania' },
    { code: 'RU', name: 'Russia' },
    { code: 'RW', name: 'Rwanda' },
    { code: 'KN', name: 'Saint Kitts & Nevis' },
    { code: 'LC', name: 'Saint Lucia' },
    { code: 'VC', name: 'Saint Vincent' },
    { code: 'WS', name: 'Samoa' },
    { code: 'SM', name: 'San Marino' },
    { code: 'ST', name: 'São Tomé & Príncipe' },
    { code: 'SA', name: 'Saudi Arabia' },
    { code: 'SN', name: 'Senegal' },
    { code: 'RS', name: 'Serbia' },
    { code: 'SC', name: 'Seychelles' },
    { code: 'SL', name: 'Sierra Leone' },
    { code: 'SG', name: 'Singapore' },
    { code: 'SK', name: 'Slovakia' },
    { code: 'SI', name: 'Slovenia' },
    { code: 'SB', name: 'Solomon Islands' },
    { code: 'SO', name: 'Somalia' },
    { code: 'ZA', name: 'South Africa' },
    { code: 'SS', name: 'South Sudan' },
    { code: 'ES', name: 'Spain' },
    { code: 'LK', name: 'Sri Lanka' },
    { code: 'SD', name: 'Sudan' },
    { code: 'SR', name: 'Suriname' },
    { code: 'SE', name: 'Sweden' },
    { code: 'CH', name: 'Switzerland' },
    { code: 'SY', name: 'Syria' },
    { code: 'TW', name: 'Taiwan' },
    { code: 'TJ', name: 'Tajikistan' },
    { code: 'TZ', name: 'Tanzania' },
    { code: 'TH', name: 'Thailand' },
    { code: 'TL', name: 'Timor-Leste' },
    { code: 'TG', name: 'Togo' },
    { code: 'TO', name: 'Tonga' },
    { code: 'TT', name: 'Trinidad & Tobago' },
    { code: 'TN', name: 'Tunisia' },
    { code: 'TR', name: 'Turkey' },
    { code: 'TM', name: 'Turkmenistan' },
    { code: 'TV', name: 'Tuvalu' },
    { code: 'UG', name: 'Uganda' },
    { code: 'UA', name: 'Ukraine' },
    { code: 'AE', name: 'UAE' },
    { code: 'GB', name: 'United Kingdom' },
    { code: 'US', name: 'United States' },
    { code: 'UY', name: 'Uruguay' },
    { code: 'UZ', name: 'Uzbekistan' },
    { code: 'VU', name: 'Vanuatu' },
    { code: 'VA', name: 'Vatican City' },
    { code: 'VE', name: 'Venezuela' },
    { code: 'VN', name: 'Vietnam' },
    { code: 'YE', name: 'Yemen' },
    { code: 'ZM', name: 'Zambia' },
    { code: 'ZW', name: 'Zimbabwe' },
];

// Common email domains
const commonEmailDomains = [
    'gmail.com',
    'yahoo.com',
    'outlook.com',
    'hotmail.com',
    'icloud.com',
    'protonmail.com',
];

const isAllCountries = () => validationForm.allowed_phone_countries === 'all';
const isAllDomains = () => validationForm.allowed_email_domains === 'all';

// Country search
const countrySearch = ref('');
const filteredCountries = computed(() => {
    if (!countrySearch.value.trim()) return allCountries;
    const search = countrySearch.value.toLowerCase().trim();
    return allCountries.filter(c =>
        c.name.toLowerCase().includes(search) ||
        c.code.toLowerCase().includes(search)
    );
});

const getSelectedCountries = () => {
    if (isAllCountries()) return [];
    return validationForm.allowed_phone_countries.split(',').map(c => c.trim()).filter(Boolean);
};

const getSelectedDomains = () => {
    if (isAllDomains()) return [];
    return validationForm.allowed_email_domains.split(',').map(d => d.trim()).filter(Boolean);
};

const toggleAllCountries = () => {
    if (isAllCountries()) {
        validationForm.allowed_phone_countries = '';
    } else {
        validationForm.allowed_phone_countries = 'all';
    }
};

const toggleCountry = (code) => {
    if (isAllCountries()) {
        validationForm.allowed_phone_countries = code;
        return;
    }
    const selected = getSelectedCountries();
    const idx = selected.indexOf(code);
    if (idx > -1) {
        selected.splice(idx, 1);
    } else {
        selected.push(code);
    }
    validationForm.allowed_phone_countries = selected.length > 0 ? selected.join(',') : 'all';
};

const isCountrySelected = (code) => {
    if (isAllCountries()) return false;
    return getSelectedCountries().includes(code);
};

const toggleAllDomains = () => {
    if (isAllDomains()) {
        validationForm.allowed_email_domains = '';
    } else {
        validationForm.allowed_email_domains = 'all';
    }
};

const addDomain = (domain) => {
    if (isAllDomains()) {
        validationForm.allowed_email_domains = domain;
        return;
    }
    const selected = getSelectedDomains();
    if (!selected.includes(domain)) {
        selected.push(domain);
        validationForm.allowed_email_domains = selected.join(',');
    }
};

const removeDomain = (domain) => {
    const selected = getSelectedDomains().filter(d => d !== domain);
    validationForm.allowed_email_domains = selected.length > 0 ? selected.join(',') : 'all';
};

const isDomainSelected = (domain) => {
    if (isAllDomains()) return false;
    return getSelectedDomains().includes(domain);
};

const customDomain = ref('');
const addCustomDomain = () => {
    if (!customDomain.value) return;
    const domain = customDomain.value.trim().toLowerCase();
    if (domain && !isDomainSelected(domain)) {
        addDomain(domain);
    }
    customDomain.value = '';
};

const submitValidation = () => {
    validationForm.put(route('dev_settings.validation'), {
        preserveScroll: true,
        preserveState: true,
        reset: ['validationConfig', 'success', 'error'],
    });
};

// Rate Limiting Config
const rateLimitForm = useForm({
    api_limit: props.rateLimitConfig?.api?.limit || 60,
    api_decay: props.rateLimitConfig?.api?.decay || 1,
    auth_limit: props.rateLimitConfig?.auth?.limit || 5,
    auth_decay: props.rateLimitConfig?.auth?.decay || 1,
    otp_limit: props.rateLimitConfig?.otp?.limit || 3,
    otp_decay: props.rateLimitConfig?.otp?.decay || 5,
});

const submitRateLimiting = () => {
    rateLimitForm.put(route('dev_settings.rate_limiting'), {
        preserveScroll: true,
        preserveState: true,
        reset: ['rateLimitConfig', 'success', 'error'],
    });
};

// Account Deletion Retention
const accountDeletionForm = useForm({
    retention_days: props.accountDeletionConfig?.retention_days || 30,
});

const submitAccountDeletion = () => {
    accountDeletionForm.put(route('dev_settings.account_deletion'), {
        preserveScroll: true,
        preserveState: true,
        reset: ['accountDeletionConfig', 'success', 'error'],
    });
};

// Multi-session toggle
const sessionsForm = useForm({
    multi_session_enabled: Boolean(props.sessionsConfig?.multi_session_enabled),
});

const submitSessions = () => {
    sessionsForm.put(route('dev_settings.sessions'), {
        preserveScroll: true,
        preserveState: true,
        reset: ['sessionsConfig', 'success', 'error'],
    });
};

// FCM Topics chip editor
const topicsForm = useForm({
    topics: [...(props.topicsConfig?.topics ?? [])],
});

const newTopic = ref('');

const addTopic = () => {
    const value = (newTopic.value || '').toLowerCase().trim();
    if (!value) return;
    if (!/^[a-z0-9_-]+$/.test(value)) return;
    if (topicsForm.topics.includes(value)) return;
    topicsForm.topics.push(value);
    newTopic.value = '';
};

const removeTopic = (topic) => {
    topicsForm.topics = topicsForm.topics.filter((t) => t !== topic);
};

const submitTopics = () => {
    topicsForm.put(route('dev_settings.topics'), {
        preserveScroll: true,
        preserveState: true,
        reset: ['topicsConfig', 'success', 'error'],
    });
};

const testTopicForm = useForm({
    topic: props.topicsConfig?.topics?.[0] || '',
    title: 'Test',
    body: 'Test broadcast from DevSettings',
});

// Reviewer accounts (App Store + Play Store reviewers)
const reviewerForm = useForm({
    apple: {
        email: props.reviewerAccounts?.apple?.email || '',
        password: props.reviewerAccounts?.apple?.password || '',
    },
    google: {
        email: props.reviewerAccounts?.google?.email || '',
        password: props.reviewerAccounts?.google?.password || '',
    },
});

const submitReviewerAccounts = () => {
    reviewerForm.put(route('dev_settings.reviewer_accounts'), {
        preserveScroll: true,
        preserveState: true,
        reset: ['reviewerAccounts', 'success', 'error'],
    });
};

const copyToClipboard = (text) => {
    if (!text) return;
    navigator.clipboard?.writeText(text);
};

const sendTestTopic = () => {
    testTopicForm.post(route('dev_settings.test_topic'), {
        preserveScroll: true,
        preserveState: true,
        reset: ['success', 'error'],
    });
};

// Pusher Config - Local
const localPusherForm = useForm({
    app_id: props.pusherConfig?.local?.app_id || '',
    app_key: props.pusherConfig?.local?.app_key || '',
    app_secret: props.pusherConfig?.local?.app_secret || '',
    app_cluster: props.pusherConfig?.local?.app_cluster || 'eu',
});

const submitLocalPusher = () => {
    localPusherForm.put(route('dev_settings.pusher'), {
        preserveScroll: true,
        preserveState: true,
        reset: ['pusherConfig', 'success', 'error'],
    });
};

// Pusher Config - Production
const productionPusherForm = useForm({
    app_id: props.pusherConfig?.production?.app_id || '',
    app_key: props.pusherConfig?.production?.app_key || '',
    app_secret: props.pusherConfig?.production?.app_secret || '',
    app_cluster: props.pusherConfig?.production?.app_cluster || 'eu',
});

const submitProductionPusher = () => {
    productionPusherForm.put(route('dev_settings.production_pusher'), {
        preserveScroll: true,
        preserveState: true,
        reset: ['pusherConfig', 'success', 'error'],
    });
};

// Test Broadcast
const testBroadcastForm = useForm({
    user_id: '',
});

const submitTestBroadcast = () => {
    testBroadcastForm.post(route('dev_settings.test_broadcast'), {
        preserveScroll: true,
        preserveState: true,
        reset: ['success', 'error'],
    });
};

// GitHub
const gitForm = useForm({ url: props.git?.remote_url || '' });
const submitGit = () => {
    gitForm.post(route('dev_settings.git'), {
        preserveScroll: true,
    });
};

const disconnectingGit = ref(false);
const disconnectGit = () => {
    disconnectingGit.value = true;
    router.delete(route('dev_settings.git_disconnect'), {
        preserveScroll: true,
        onFinish: () => { disconnectingGit.value = false; },
    });
};

// Git Pull
const pulling = ref(false);
const pullFromGithub = () => {
    pulling.value = true;
    router.post(route('dev_settings.pull'), {}, {
        preserveScroll: true,
        onFinish: () => { pulling.value = false; },
    });
};

// Git Fetch
const fetching = ref(false);
const fetchRemote = () => {
    fetching.value = true;
    router.post(route('dev_settings.fetch'), {}, {
        preserveScroll: true,
        onFinish: () => { fetching.value = false; },
    });
};

// Git Commit
const commitForm = useForm({ message: '' });
const submitCommit = () => {
    commitForm.post(route('dev_settings.commit'), {
        preserveScroll: true,
        onSuccess: () => { commitForm.reset('message'); },
    });
};

// Git Commit & Push
const commitAndPush = () => {
    commitForm.post(route('dev_settings.commit'), {
        preserveScroll: true,
        onSuccess: () => {
            commitForm.reset('message');
            // After successful commit, push
            router.post(route('dev_settings.push'), {}, {
                preserveScroll: true,
            });
        },
    });
};

// Switch Branch
const switchBranchForm = useForm({ branch: '' });
const switching = ref(false);
const switchBranch = (branch) => {
    switching.value = true;
    router.post(route('dev_settings.branch_switch'), { branch }, {
        preserveScroll: true,
        onFinish: () => { switching.value = false; },
    });
};

// Create Branch
const createBranchForm = useForm({ name: '' });
const submitCreateBranch = () => {
    createBranchForm.post(route('dev_settings.branch_create'), {
        preserveScroll: true,
        onSuccess: () => { createBranchForm.reset('name'); },
    });
};

// File Diff
const expandedFile = ref(null);
const fileDiff = ref('');
const loadingDiff = ref(false);

const toggleFileDiff = async (file, type) => {
    const key = `${type}:${file}`;
    if (expandedFile.value === key) {
        expandedFile.value = null;
        fileDiff.value = '';
        return;
    }

    loadingDiff.value = true;
    expandedFile.value = key;

    try {
        const response = await fetch(route('dev_settings.diff') + `?file=${encodeURIComponent(file)}&type=${type}`);
        const data = await response.json();
        fileDiff.value = data.diff || '';
    } catch (e) {
        fileDiff.value = 'Error loading diff';
    } finally {
        loadingDiff.value = false;
    }
};

// Branches dropdown
const showBranchDropdown = ref(false);

// Changes panel
const showChanges = ref(true);
const showCommits = ref(false);

// SSH Deploy Config
const sshForm = useForm({
    ssh_host: props.deployConfig?.ssh_host || '',
    ssh_port: props.deployConfig?.ssh_port || 65002,
    ssh_username: props.deployConfig?.ssh_username || '',
    ssh_password: props.deployConfig?.ssh_password || '',
    domain: props.deployConfig?.domain || '',
});

const submitSsh = () => {
    sshForm.put(route('dev_settings.deploy_config'), {
        preserveScroll: true,
        preserveState: true,
        reset: ['deployConfig', 'success', 'error'],
    });
};

// Push to GitHub
const pushing = ref(false);

const pushToGithub = () => {
    pushing.value = true;
    router.post(route('dev_settings.push'), {}, {
        preserveScroll: true,
        onFinish: () => { pushing.value = false; },
    });
};

// Deploy
const deploying = ref(false);
const showDeployModal = ref(false);
const showLogModal = ref(false);
const deployOptions = ref({
    migration_option: 'migrate', // Default to safe option
    run_seeders: false,
    safe_storage_deploy: true, // Default on — preserve uploaded files.
});

const openDeployModal = () => {
    showDeployModal.value = true;
};

const closeDeployModal = () => {
    showDeployModal.value = false;
};

const deployToProduction = () => {
    deploying.value = true;
    showDeployModal.value = false;
    router.post(route('dev_settings.deploy'), deployOptions.value, {
        preserveScroll: true,
        onFinish: () => { deploying.value = false; },
    });
};

// Production DB
const prodDbForm = useForm({
    DB_HOST: props.productionDb?.DB_HOST || '',
    DB_PORT: props.productionDb?.DB_PORT || '3306',
    DB_DATABASE: props.productionDb?.DB_DATABASE || '',
    DB_USERNAME: props.productionDb?.DB_USERNAME || '',
    DB_PASSWORD: props.productionDb?.DB_PASSWORD || '',
});

const submitProdDb = () => {
    prodDbForm.put(route('dev_settings.production_db'), {
        preserveScroll: true,
        preserveState: true,
        reset: ['productionDb', 'success', 'error'],
    });
};

// App Name
const appNameForm = useForm({
    APP_NAME: props.appName || '',
});
const submitAppName = () => {
    appNameForm.put(route('dev_settings.app_name'), {
        preserveScroll: true,
        preserveState: true,
        reset: ['appName', 'success', 'error'],
    });
};

// API Token
const tokenGenerating = ref('');
const generateToken = (target) => {
    tokenGenerating.value = target;
    router.post(route('dev_settings.api_token'), { target }, {
        preserveScroll: true,
        onFinish: () => { tokenGenerating.value = ''; },
    });
};

// Admin Credentials
const localAdminForm = useForm({
    target: 'local',
    ADMIN_EMAIL: props.adminCredentials?.local?.ADMIN_EMAIL || '',
    ADMIN_PASSWORD: props.adminCredentials?.local?.ADMIN_PASSWORD || '',
});
const prodAdminForm = useForm({
    target: 'production',
    ADMIN_EMAIL: props.adminCredentials?.production?.ADMIN_EMAIL || '',
    ADMIN_PASSWORD: props.adminCredentials?.production?.ADMIN_PASSWORD || '',
});

const submitLocalAdmin = () => {
    localAdminForm.put(route('dev_settings.admin_credentials'), {
        preserveScroll: true,
        preserveState: true,
        reset: ['adminCredentials', 'success', 'error'],
    });
};
const submitProdAdmin = () => {
    prodAdminForm.put(route('dev_settings.admin_credentials'), {
        preserveScroll: true,
        preserveState: true,
        reset: ['adminCredentials', 'success', 'error'],
    });
};

// URLs
const localUrlsForm = useForm({
    target: 'local',
    APP_URL: props.urls?.local?.APP_URL || 'http://localhost',
    FRONTEND_URL: props.urls?.local?.FRONTEND_URL || 'http://localhost:5173',
});
const prodUrlsForm = useForm({
    target: 'production',
    APP_URL: props.urls?.production?.APP_URL || '',
    FRONTEND_URL: props.urls?.production?.FRONTEND_URL || '',
});

const submitLocalUrls = () => {
    localUrlsForm.put(route('dev_settings.urls'), {
        preserveScroll: true,
        preserveState: true,
        reset: ['urls', 'success', 'error'],
    });
};
const submitProdUrls = () => {
    prodUrlsForm.put(route('dev_settings.urls'), {
        preserveScroll: true,
        preserveState: true,
        reset: ['urls', 'success', 'error'],
    });
};

// Local Mail
const localMailForm = useForm({
    MAIL_MAILER: props.localMail?.MAIL_MAILER || 'smtp',
    MAIL_HOST: props.localMail?.MAIL_HOST || '',
    MAIL_PORT: props.localMail?.MAIL_PORT || '465',
    MAIL_USERNAME: props.localMail?.MAIL_USERNAME || '',
    MAIL_PASSWORD: props.localMail?.MAIL_PASSWORD || '',
    MAIL_ENCRYPTION: props.localMail?.MAIL_ENCRYPTION || 'ssl',
    MAIL_FROM_ADDRESS: props.localMail?.MAIL_FROM_ADDRESS || '',
});

const submitLocalMail = () => {
    localMailForm.put(route('dev_settings.local_mail'), {
        preserveScroll: true,
        preserveState: true,
        reset: ['localMail', 'success', 'error'],
    });
};

// Production Mail
const prodMailForm = useForm({
    MAIL_MAILER: props.productionMail?.MAIL_MAILER || 'smtp',
    MAIL_HOST: props.productionMail?.MAIL_HOST || '',
    MAIL_PORT: props.productionMail?.MAIL_PORT || '465',
    MAIL_USERNAME: props.productionMail?.MAIL_USERNAME || '',
    MAIL_PASSWORD: props.productionMail?.MAIL_PASSWORD || '',
    MAIL_ENCRYPTION: props.productionMail?.MAIL_ENCRYPTION || 'ssl',
    MAIL_FROM_ADDRESS: props.productionMail?.MAIL_FROM_ADDRESS || '',
});

const submitProdMail = () => {
    prodMailForm.put(route('dev_settings.production_mail'), {
        preserveScroll: true,
        preserveState: true,
        reset: ['productionMail', 'success', 'error'],
    });
};

// Firebase
const firebaseForm = useForm({ firebase_json: null });
const handleFirebaseFile = (e) => {
    firebaseForm.firebase_json = e.target.files[0] || null;
};
const uploadFirebase = () => {
    firebaseForm.post(route('dev_settings.firebase'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => { firebaseForm.reset(); },
    });
};

// Branding
const logoForm = useForm({ logo: null });
const faviconForm = useForm({ favicon: null });
const logoCopied = ref(false);

const uploadLogo = () => {
    logoForm.post(route('dev_settings.logo'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => { logoForm.reset(); },
    });
};

const uploadFavicon = () => {
    faviconForm.post(route('dev_settings.favicon'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => { faviconForm.reset(); },
    });
};

const copyLogoPath = async () => {
    await navigator.clipboard.writeText(window.location.origin + '/images/logo.png');
    logoCopied.value = true;
    setTimeout(() => { logoCopied.value = false; }, 2000);
};

// Test FCM
const fcmToken = ref('');
const testingFcm = ref(false);
const sendTestFcm = () => {
    testingFcm.value = true;
    router.post(route('dev_settings.test_fcm'), { token: fcmToken.value }, {
        preserveScroll: true,
        onFinish: () => { testingFcm.value = false; },
    });
};
</script>

<template>

    <Head :title="t('developer_settings')" />

    <div class="h-full min-h-[100dvh] w-full bg-background">
        <div class="mx-auto flex w-full max-w-[1300px] gap-6 px-4 py-10 md:py-20 text-start">
            <!-- Sidebar -->
            <aside class="sticky top-20 h-fit w-64 shrink-0 rounded-2xl border bg-card p-3 hidden lg:block">
                <div class="flex items-center gap-3 px-3 py-2 mb-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-accent">
                        <Hammer class="size-4 text-accent-foreground" />
                    </div>
                    <span class="font-semibold text-foreground text-sm">{{ t('developer_settings') }}</span>
                </div>
                <nav class="space-y-1">
                    <button v-for="item in menuItems" :key="item.id" @click="activeSection = item.id"
                        class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm transition-colors" :class="activeSection === item.id
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:bg-muted hover:text-foreground'">
                        <component :is="item.icon" class="size-4" />
                        {{ t(item.label) }}
                    </button>
                </nav>
            </aside>

            <!-- Mobile Menu -->
            <div class="lg:hidden fixed bottom-4 start-4 end-4 z-50">
                <div class="rounded-2xl border bg-card/95 backdrop-blur-sm p-2 shadow-lg">
                    <div class="flex gap-1 overflow-x-auto">
                        <button v-for="item in menuItems" :key="item.id" @click="activeSection = item.id"
                            class="flex shrink-0 flex-col items-center gap-1 rounded-lg px-3 py-2 text-xs transition-colors"
                            :class="activeSection === item.id
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground'">
                            <component :is="item.icon" class="size-4" />
                            <span class="whitespace-nowrap">{{ t(item.label) }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 space-y-5 pb-20 lg:pb-0">
                <!-- Section Header -->
                <div class="flex items-center gap-3 rounded-2xl border bg-card p-4">
                    <component :is="menuItems.find(m => m.id === activeSection)?.icon || Settings"
                        class="size-5 text-primary" />
                    <div>
                        <h1 class="text-lg font-semibold text-foreground">
                            {{t(menuItems.find(m => m.id === activeSection)?.label || 'general_settings')}}
                        </h1>
                    </div>
                </div>

                <!-- App Name -->
                <div v-if="activeSection === 'general'"
                    class="flex w-full items-center gap-3 rounded-xl border bg-card p-4">
                    <label class="text-sm font-medium text-foreground shrink-0">{{ t('app_name') }}</label>
                    <form @submit.prevent="submitAppName" class="flex flex-1 items-center gap-3">
                        <Input v-model="appNameForm.APP_NAME" type="text" placeholder="My App" class="flex-1" />
                        <Button type="submit" :disabled="appNameForm.processing">
                            <Loader2 v-if="appNameForm.processing" class="me-2 h-4 w-4 animate-spin" />
                            {{ t('save') }}
                        </Button>
                    </form>
                </div>

                <!-- Action Buttons -->
                <div v-if="activeSection === 'general'"
                    class="flex w-full flex-wrap items-center gap-3 rounded-xl border bg-card p-4">

                    <Button :disabled="deploying" variant="outline"
                        class="border-emerald-500 text-emerald-500 hover:bg-emerald-500 hover:text-white"
                        @click="openDeployModal">
                        <Loader2 v-if="deploying" class="me-2 h-4 w-4 animate-spin" />
                        <Rocket v-else class="me-2 size-4" />
                        {{ deploying ? t('deploying') : t('deploy') }}
                    </Button>
                    <Button v-if="deployLog" variant="outline"
                        class="border-sky-500 text-sky-500 hover:bg-sky-500 hover:text-white"
                        @click="showLogModal = true">
                        <FileCode class="me-2 size-4" />
                        {{ t('view_deploy_log') }}
                    </Button>
                    <a :href="route('dev_settings.postman')" download>
                        <Button variant="outline"
                            class="border-orange-500 text-orange-500 hover:bg-orange-500 hover:text-white">
                            <Download class="me-2 size-4" />
                            {{ t('download_postman') }}
                        </Button>
                    </a>
                </div>

                <!-- GitHub -->
                <div v-if="activeSection === 'deployment'" class="rounded-3xl border bg-card p-6">
                    <!-- Header with actions -->
                    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <GitBranch class="size-5 text-foreground" />
                            <div>
                                <h2 class="text-lg font-semibold text-foreground">{{ t('github') }}</h2>
                                <p class="text-sm text-muted-foreground">{{ t('github_desc') }}</p>
                            </div>
                        </div>
                        <div v-if="git?.is_repo && git?.remote_url" class="flex items-center gap-2">
                            <Button size="sm" variant="outline" :disabled="fetching" @click="fetchRemote">
                                <Loader2 v-if="fetching" class="me-1 h-3 w-3 animate-spin" />
                                <RefreshCw v-else class="me-1 size-3" />
                                {{ fetching ? t('fetching') : t('fetch_remote') }}
                            </Button>
                            <Button size="sm" variant="outline" :disabled="pulling" @click="pullFromGithub">
                                <Loader2 v-if="pulling" class="me-1 h-3 w-3 animate-spin" />
                                <ArrowDownCircle v-else class="me-1 size-3" />
                                {{ pulling ? t('pulling') : t('pull_from_github') }}
                            </Button>
                            <Button size="sm" variant="outline"
                                class="border-primary text-primary hover:bg-primary hover:text-primary-foreground"
                                :disabled="pushing" @click="pushToGithub">
                                <Loader2 v-if="pushing" class="me-1 h-3 w-3 animate-spin" />
                                <ArrowUpCircle v-else class="me-1 size-3" />
                                {{ pushing ? t('pushing') : t('push_to_github') }}
                            </Button>
                        </div>
                    </div>

                    <!-- Status Row -->
                    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
                        <div class="flex items-center gap-2">
                            <CheckCircle v-if="git?.remote_url" class="size-4 text-emerald-500" />
                            <XCircle v-else class="size-4 text-muted-foreground" />
                            <span class="text-sm"
                                :class="git?.remote_url ? 'text-emerald-600' : 'text-muted-foreground'">
                                {{ git?.remote_url ? t('github_connected') : t('github_not_connected') }}
                            </span>
                            <a v-if="git?.remote_url" :href="git.remote_url" target="_blank"
                                class="text-xs font-mono text-primary hover:underline ms-1">
                                {{ git.remote_url }}
                            </a>
                        </div>

                        <!-- Branch Selector -->
                        <div v-if="git?.is_repo && git?.current_branch" class="relative">
                            <Button size="sm" variant="outline" @click="showBranchDropdown = !showBranchDropdown"
                                class="min-w-[140px] justify-between">
                                <span class="flex items-center gap-2">
                                    <GitBranch class="size-3" />
                                    {{ git.current_branch }}
                                </span>
                                <ChevronDown class="size-3 ms-2" />
                            </Button>
                            <!-- Branch dropdown -->
                            <div v-if="showBranchDropdown"
                                class="absolute end-0 top-full z-10 mt-1 w-56 rounded-lg border bg-card shadow-lg"
                                @click.stop>
                                <div class="max-h-60 overflow-y-auto p-1">
                                    <div class="px-2 py-1 text-xs font-medium text-muted-foreground">{{ t('branches') }}
                                    </div>
                                    <button v-for="branch in git.branches" :key="branch"
                                        @click="switchBranch(branch); showBranchDropdown = false"
                                        :disabled="switching || branch === git.current_branch"
                                        class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-sm text-start hover:bg-muted disabled:opacity-50"
                                        :class="{ 'bg-muted': branch === git.current_branch }">
                                        <CheckCircle v-if="branch === git.current_branch"
                                            class="size-3 text-emerald-500" />
                                        <span v-else class="size-3"></span>
                                        {{ branch }}
                                    </button>
                                    <div v-if="git.remote_branches?.length" class="mt-1 border-t border-border pt-1">
                                        <div class="px-2 py-1 text-xs font-medium text-muted-foreground">Remote</div>
                                        <button
                                            v-for="branch in git.remote_branches.filter(b => !git.branches.includes(b))"
                                            :key="'remote-' + branch"
                                            @click="switchBranch(branch); showBranchDropdown = false"
                                            :disabled="switching"
                                            class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-sm text-start text-muted-foreground hover:bg-muted">
                                            <span class="size-3"></span>
                                            origin/{{ branch }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Ahead/Behind indicator -->
                    <div v-if="git?.is_repo && git?.remote_url && (git.ahead > 0 || git.behind > 0)"
                        class="mb-4 flex items-center gap-4 text-sm">
                        <span v-if="git.ahead > 0" class="flex items-center gap-1 text-emerald-600">
                            <ArrowUpCircle class="size-4" />
                            {{ git.ahead }} {{ t('ahead') }}
                        </span>
                        <span v-if="git.behind > 0" class="flex items-center gap-1 text-amber-600">
                            <ArrowDownCircle class="size-4" />
                            {{ git.behind }} {{ t('behind') }}
                        </span>
                    </div>

                    <!-- Init Form (when no repo) -->
                    <form v-if="!git?.is_repo" @submit.prevent="submitGit" class="flex items-center gap-3">
                        <Input v-model="gitForm.url" type="url" :placeholder="t('github_url_placeholder')"
                            class="flex-1" />
                        <Button type="submit" :disabled="gitForm.processing || !gitForm.url">
                            <Loader2 v-if="gitForm.processing" class="me-2 h-4 w-4 animate-spin" />
                            {{ gitForm.processing ? t('initializing') : t('initialize_push') }}
                        </Button>
                    </form>
                    <div v-if="gitForm.errors.url" class="mt-2 text-sm text-red-600">{{ gitForm.errors.url }}</div>

                    <!-- Changes Panel (when repo exists) -->
                    <div v-if="git?.is_repo" class="space-y-4">
                        <!-- Changes Section -->
                        <div class="rounded-xl border border-border">
                            <button @click="showChanges = !showChanges"
                                class="flex w-full items-center justify-between p-3 text-start hover:bg-muted/50">
                                <span class="flex items-center gap-2 text-sm font-medium">
                                    <ChevronRight :class="{ 'rotate-90': showChanges }"
                                        class="size-4 transition-transform" />
                                    {{ t('changes') }}
                                    <span
                                        v-if="(git.modified?.length || 0) + (git.staged?.length || 0) + (git.untracked?.length || 0) > 0"
                                        class="rounded-full bg-primary px-2 py-0.5 text-xs text-primary-foreground">
                                        {{ (git.modified?.length || 0) + (git.staged?.length || 0) +
                                        (git.untracked?.length || 0) }}
                                    </span>
                                </span>
                            </button>
                            <div v-if="showChanges" class="border-t border-border p-3">
                                <div v-if="(git.modified?.length || 0) + (git.staged?.length || 0) + (git.untracked?.length || 0) === 0"
                                    class="text-sm text-muted-foreground">
                                    {{ t('no_changes') }}
                                </div>
                                <div v-else class="space-y-3">
                                    <!-- Staged files -->
                                    <div v-if="git.staged?.length">
                                        <div class="mb-1 text-xs font-medium text-emerald-600">{{ t('staged_files') }}
                                        </div>
                                        <div v-for="file in git.staged" :key="'staged-' + file" class="group">
                                            <button @click="toggleFileDiff(file, 'staged')"
                                                class="flex w-full items-center gap-2 rounded px-2 py-1 text-start text-sm hover:bg-muted">
                                                <FileEdit class="size-3 text-emerald-500" />
                                                <span class="flex-1 font-mono text-xs">{{ file }}</span>
                                                <ChevronRight
                                                    :class="{ 'rotate-90': expandedFile === `staged:${file}` }"
                                                    class="size-3 text-muted-foreground transition-transform" />
                                            </button>
                                            <div v-if="expandedFile === `staged:${file}`"
                                                class="mt-1 ms-5 rounded bg-muted p-2">
                                                <Loader2 v-if="loadingDiff" class="h-4 w-4 animate-spin" />
                                                <pre v-else
                                                    class="max-h-40 overflow-auto whitespace-pre-wrap font-mono text-xs">{{ fileDiff }}</pre>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Modified files -->
                                    <div v-if="git.modified?.length">
                                        <div class="mb-1 text-xs font-medium text-amber-600">{{ t('modified_files') }}
                                        </div>
                                        <div v-for="file in git.modified" :key="'modified-' + file" class="group">
                                            <button @click="toggleFileDiff(file, 'modified')"
                                                class="flex w-full items-center gap-2 rounded px-2 py-1 text-start text-sm hover:bg-muted">
                                                <FileCode class="size-3 text-amber-500" />
                                                <span class="flex-1 font-mono text-xs">{{ file }}</span>
                                                <ChevronRight
                                                    :class="{ 'rotate-90': expandedFile === `modified:${file}` }"
                                                    class="size-3 text-muted-foreground transition-transform" />
                                            </button>
                                            <div v-if="expandedFile === `modified:${file}`"
                                                class="mt-1 ms-5 rounded bg-muted p-2">
                                                <Loader2 v-if="loadingDiff" class="h-4 w-4 animate-spin" />
                                                <pre v-else
                                                    class="max-h-40 overflow-auto whitespace-pre-wrap font-mono text-xs">{{ fileDiff }}</pre>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Untracked files -->
                                    <div v-if="git.untracked?.length">
                                        <div class="mb-1 text-xs font-medium text-blue-600">{{ t('untracked_files') }}
                                        </div>
                                        <div v-for="file in git.untracked" :key="'untracked-' + file" class="group">
                                            <button @click="toggleFileDiff(file, 'untracked')"
                                                class="flex w-full items-center gap-2 rounded px-2 py-1 text-start text-sm hover:bg-muted">
                                                <FilePlus class="size-3 text-blue-500" />
                                                <span class="flex-1 font-mono text-xs">{{ file }}</span>
                                                <ChevronRight
                                                    :class="{ 'rotate-90': expandedFile === `untracked:${file}` }"
                                                    class="size-3 text-muted-foreground transition-transform" />
                                            </button>
                                            <div v-if="expandedFile === `untracked:${file}`"
                                                class="mt-1 ms-5 rounded bg-muted p-2">
                                                <Loader2 v-if="loadingDiff" class="h-4 w-4 animate-spin" />
                                                <pre v-else
                                                    class="max-h-40 overflow-auto whitespace-pre-wrap font-mono text-xs">{{ fileDiff }}</pre>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Commit Form -->
                        <div v-if="(git.modified?.length || 0) + (git.staged?.length || 0) + (git.untracked?.length || 0) > 0"
                            class="flex flex-col gap-2 sm:flex-row">
                            <Input v-model="commitForm.message" :placeholder="t('commit_message_placeholder')"
                                class="flex-1" />
                            <div class="flex gap-2">
                                <Button :disabled="commitForm.processing || !commitForm.message" @click="submitCommit">
                                    <Loader2 v-if="commitForm.processing" class="me-2 h-4 w-4 animate-spin" />
                                    <GitCommit v-else class="me-2 size-4" />
                                    {{ commitForm.processing ? t('committing') : t('commit_changes') }}
                                </Button>
                                <Button variant="outline"
                                    class="border-primary text-primary hover:bg-primary hover:text-primary-foreground"
                                    :disabled="commitForm.processing || !commitForm.message" @click="commitAndPush">
                                    {{ t('commit_and_push') }}
                                </Button>
                            </div>
                        </div>
                        <div v-if="commitForm.errors.message" class="text-sm text-red-600">{{ commitForm.errors.message
                            }}</div>

                        <!-- Create Branch -->
                        <div class="flex flex-col gap-2 sm:flex-row">
                            <Input v-model="createBranchForm.name" :placeholder="t('new_branch_placeholder')"
                                class="flex-1 sm:max-w-xs" />
                            <Button variant="outline" :disabled="createBranchForm.processing || !createBranchForm.name"
                                @click="submitCreateBranch">
                                <Loader2 v-if="createBranchForm.processing" class="me-2 h-4 w-4 animate-spin" />
                                <Plus v-else class="me-2 size-4" />
                                {{ createBranchForm.processing ? t('creating') : t('create_branch') }}
                            </Button>
                        </div>
                        <div v-if="createBranchForm.errors.name" class="text-sm text-red-600">{{
                            createBranchForm.errors.name }}</div>

                        <!-- Recent Commits -->
                        <div class="rounded-xl border border-border">
                            <button @click="showCommits = !showCommits"
                                class="flex w-full items-center justify-between p-3 text-start hover:bg-muted/50">
                                <span class="flex items-center gap-2 text-sm font-medium">
                                    <ChevronRight :class="{ 'rotate-90': showCommits }"
                                        class="size-4 transition-transform" />
                                    {{ t('recent_commits') }}
                                </span>
                            </button>
                            <div v-if="showCommits" class="border-t border-border p-3">
                                <div v-if="!git.commits?.length" class="text-sm text-muted-foreground">
                                    {{ t('no_commits') }}
                                </div>
                                <div v-else class="space-y-1">
                                    <div v-for="commit in git.commits" :key="commit.hash"
                                        class="flex items-center gap-2 rounded px-2 py-1 text-sm hover:bg-muted">
                                        <GitCommit class="size-3 text-muted-foreground" />
                                        <span class="font-mono text-xs text-primary">{{ commit.hash }}</span>
                                        <span class="flex-1 truncate">{{ commit.message }}</span>
                                        <span class="text-xs text-muted-foreground">{{ commit.date }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Disconnect -->
                        <div class="border-t border-border pt-4">
                            <Button variant="outline"
                                class="border-red-500 text-red-500 hover:bg-red-500 hover:text-white"
                                :disabled="disconnectingGit" @click="disconnectGit">
                                <Loader2 v-if="disconnectingGit" class="me-2 h-4 w-4 animate-spin" />
                                <XCircle v-else class="me-2 size-4" />
                                {{ disconnectingGit ? t('removing') : t('remove_git') }}
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Server SSH Config -->
                <div v-if="activeSection === 'deployment'" class="rounded-3xl border bg-card p-6">
                    <div class="mb-6 flex items-center gap-3">
                        <Server class="size-5 text-orange-500" />
                        <div>
                            <h2 class="text-lg font-semibold text-foreground">{{ t('ssh_config') }}</h2>
                            <p class="text-sm text-muted-foreground">{{ t('ssh_config_desc') }}</p>
                        </div>
                    </div>

                    <!-- Status -->
                    <div class="mb-6 flex items-center gap-2">
                        <CheckCircle v-if="deployConfig?.has_config" class="size-4 text-emerald-500" />
                        <XCircle v-else class="size-4 text-muted-foreground" />
                        <span class="text-sm"
                            :class="deployConfig?.has_config ? 'text-emerald-600' : 'text-muted-foreground'">
                            {{ deployConfig?.has_config ? t('ssh_configured') : t('ssh_not_configured') }}
                        </span>
                        <span v-if="deployConfig?.has_config && deployConfig?.ssh_host"
                            class="text-xs font-mono text-muted-foreground ms-1">
                            {{ deployConfig.ssh_username }}@{{ deployConfig.ssh_host }}:{{ deployConfig.ssh_port }} → {{
                            deployConfig.domain }}
                        </span>
                    </div>

                    <form @submit.prevent="submitSsh" class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">{{ t('ssh_host') }}</label>
                                <Input v-model="sshForm.ssh_host" type="text"
                                    placeholder="us-bos-web1568.main-hosting.eu" />
                            </div>
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">{{ t('ssh_port') }}</label>
                                <Input v-model="sshForm.ssh_port" type="number" placeholder="65002" />
                            </div>
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">{{ t('ssh_username') }}</label>
                                <Input v-model="sshForm.ssh_username" type="text" placeholder="u983470049" />
                            </div>
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">{{ t('ssh_password') }}</label>
                                <Input v-model="sshForm.ssh_password" type="password" placeholder="••••••••" />
                            </div>
                            <div class="space-y-2 sm:col-span-2">
                                <label class="block text-sm font-medium text-foreground">{{ t('domain') }}</label>
                                <Input v-model="sshForm.domain" type="text" placeholder="example.hostingersite.com" />
                            </div>
                        </div>

                        <div class="pt-2">
                            <Button type="submit" :disabled="sshForm.processing">
                                <Loader2 v-if="sshForm.processing" class="me-2 h-4 w-4 animate-spin" />
                                {{ sshForm.processing ? t('saving') : t('save_ssh_config') }}
                            </Button>
                        </div>
                    </form>
                </div>

                <!-- App URLs -->
                <div v-if="activeSection === 'general'" class="rounded-3xl border bg-card p-6">
                    <div class="mb-6 flex items-center gap-3">
                        <Link2 class="size-5 text-blue-500" />
                        <div>
                            <h2 class="text-lg font-semibold text-foreground">{{ t('urls') }}</h2>
                            <p class="text-sm text-muted-foreground">{{ t('urls_desc') }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <!-- Local URLs -->
                        <form @submit.prevent="submitLocalUrls" class="space-y-4 rounded-2xl border border-border p-4">
                            <h3 class="text-sm font-medium text-foreground">{{ t('local') }}</h3>
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-muted-foreground">{{ t('app_url')
                                    }}</label>
                                <Input v-model="localUrlsForm.APP_URL" type="url" placeholder="http://localhost" />
                            </div>
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-muted-foreground">{{ t('frontend_url')
                                    }}</label>
                                <Input v-model="localUrlsForm.FRONTEND_URL" type="url"
                                    placeholder="http://localhost:5173" />
                            </div>
                            <Button type="submit" :disabled="localUrlsForm.processing">
                                <Loader2 v-if="localUrlsForm.processing" class="me-2 h-4 w-4 animate-spin" />
                                {{ localUrlsForm.processing ? t('saving') : t('save_urls') }}
                            </Button>
                        </form>

                        <!-- Production URLs -->
                        <form @submit.prevent="submitProdUrls" class="space-y-4 rounded-2xl border border-border p-4">
                            <h3 class="text-sm font-medium text-foreground">{{ t('production') }}</h3>
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-muted-foreground">{{ t('app_url')
                                    }}</label>
                                <Input v-model="prodUrlsForm.APP_URL" type="url" placeholder="https://yourdomain.com" />
                            </div>
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-muted-foreground">{{ t('frontend_url')
                                    }}</label>
                                <Input v-model="prodUrlsForm.FRONTEND_URL" type="url"
                                    placeholder="https://app.yourdomain.com" />
                            </div>
                            <Button type="submit" :disabled="prodUrlsForm.processing">
                                <Loader2 v-if="prodUrlsForm.processing" class="me-2 h-4 w-4 animate-spin" />
                                {{ prodUrlsForm.processing ? t('saving') : t('save_urls') }}
                            </Button>
                        </form>
                    </div>
                </div>

                <!-- Admin Credentials -->
                <div v-if="activeSection === 'authentication'" class="rounded-3xl border bg-card p-6">
                    <div class="mb-6 flex items-center gap-3">
                        <KeyRound class="size-5 text-rose-500" />
                        <div>
                            <h2 class="text-lg font-semibold text-foreground">{{ t('admin_credentials') }}</h2>
                            <p class="text-sm text-muted-foreground">{{ t('admin_credentials_desc') }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <!-- Local -->
                        <form @submit.prevent="submitLocalAdmin" class="space-y-4 rounded-2xl border border-border p-4">
                            <h3 class="text-sm font-medium text-foreground">{{ t('local') }}</h3>
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-muted-foreground">{{ t('admin_email')
                                    }}</label>
                                <Input v-model="localAdminForm.ADMIN_EMAIL" type="email"
                                    placeholder="admin@example.com" />
                            </div>
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-muted-foreground">{{ t('admin_password')
                                    }}</label>
                                <Input v-model="localAdminForm.ADMIN_PASSWORD" type="password" placeholder="••••••••" />
                            </div>
                            <Button type="submit" :disabled="localAdminForm.processing">
                                <Loader2 v-if="localAdminForm.processing" class="me-2 h-4 w-4 animate-spin" />
                                {{ localAdminForm.processing ? t('saving') : t('save_admin') }}
                            </Button>
                        </form>

                        <!-- Production -->
                        <form @submit.prevent="submitProdAdmin" class="space-y-4 rounded-2xl border border-border p-4">
                            <h3 class="text-sm font-medium text-foreground">{{ t('production') }}</h3>
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-muted-foreground">{{ t('admin_email')
                                    }}</label>
                                <Input v-model="prodAdminForm.ADMIN_EMAIL" type="email"
                                    placeholder="admin@example.com" />
                            </div>
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-muted-foreground">{{ t('admin_password')
                                    }}</label>
                                <Input v-model="prodAdminForm.ADMIN_PASSWORD" type="password" placeholder="••••••••" />
                            </div>
                            <Button type="submit" :disabled="prodAdminForm.processing">
                                <Loader2 v-if="prodAdminForm.processing" class="me-2 h-4 w-4 animate-spin" />
                                {{ prodAdminForm.processing ? t('saving') : t('save_admin') }}
                            </Button>
                        </form>
                    </div>
                </div>

                <!-- API Token -->
                <div v-if="activeSection === 'authentication'" class="rounded-3xl border bg-card p-6">
                    <div class="mb-6 flex items-center gap-3">
                        <RefreshCw class="size-5 text-cyan-500" />
                        <div>
                            <h2 class="text-lg font-semibold text-foreground">{{ t('api_token') }}</h2>
                            <p class="text-sm text-muted-foreground">{{ t('api_token_desc') }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <!-- Local -->
                        <div class="space-y-3 rounded-2xl border border-border p-4">
                            <h3 class="text-sm font-medium text-foreground">{{ t('local') }}</h3>
                            <code
                                class="block break-all rounded-lg bg-muted p-3 text-xs text-muted-foreground">{{ apiToken?.local || '—' }}</code>
                            <Button :disabled="!!tokenGenerating" @click="generateToken('local')">
                                <Loader2 v-if="tokenGenerating === 'local'" class="me-2 h-4 w-4 animate-spin" />
                                <RefreshCw v-else class="me-2 size-4" />
                                {{ tokenGenerating === 'local' ? t('generating') : t('generate_local') }}
                            </Button>
                        </div>

                        <!-- Production -->
                        <div class="space-y-3 rounded-2xl border border-border p-4">
                            <h3 class="text-sm font-medium text-foreground">{{ t('production') }}</h3>
                            <code
                                class="block break-all rounded-lg bg-muted p-3 text-xs text-muted-foreground">{{ apiToken?.production || '—' }}</code>
                            <Button :disabled="!!tokenGenerating" @click="generateToken('production')">
                                <Loader2 v-if="tokenGenerating === 'production'" class="me-2 h-4 w-4 animate-spin" />
                                <RefreshCw v-else class="me-2 size-4" />
                                {{ tokenGenerating === 'production' ? t('generating') : t('generate_production') }}
                            </Button>
                        </div>
                    </div>

                    <!-- Generate Both -->
                    <div class="mt-4 border-t border-border pt-4">
                        <Button variant="outline" :disabled="!!tokenGenerating" @click="generateToken('both')">
                            <Loader2 v-if="tokenGenerating === 'both'" class="me-2 h-4 w-4 animate-spin" />
                            <RefreshCw v-else class="me-2 size-4" />
                            {{ tokenGenerating === 'both' ? t('generating') : t('generate_both') }}
                        </Button>
                    </div>
                </div>

                <!-- Sessions / Multi-Device -->
                <div v-if="activeSection === 'sessions'" class="rounded-3xl border bg-card p-6">
                    <div class="mb-6 flex items-center gap-3">
                        <KeyRound class="size-5 text-emerald-500" />
                        <div>
                            <h2 class="text-lg font-semibold text-foreground">{{ t('sessions_settings') }}</h2>
                            <p class="text-sm text-muted-foreground">{{ t('sessions_settings_desc') }}</p>
                        </div>
                    </div>

                    <form @submit.prevent="submitSessions" class="space-y-6">
                        <label class="flex cursor-pointer items-start gap-4 rounded-xl border bg-muted/30 p-4">
                            <input
                                type="checkbox"
                                v-model="sessionsForm.multi_session_enabled"
                                class="mt-1 size-4 cursor-pointer accent-primary"
                            />
                            <div class="flex-1">
                                <p class="text-sm font-medium text-foreground">{{ t('multi_session_enabled') }}</p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ sessionsForm.multi_session_enabled ? t('multi_session_desc') : t('single_session_desc') }}
                                </p>
                            </div>
                        </label>

                        <div class="pt-2">
                            <Button type="submit" :disabled="sessionsForm.processing">
                                <Loader2 v-if="sessionsForm.processing" class="me-2 h-4 w-4 animate-spin" />
                                {{ sessionsForm.processing ? t('saving') : t('save_sessions_config') }}
                            </Button>
                        </div>
                    </form>
                </div>

                <!-- Reviewer Accounts (App Store + Play Store reviewers) -->
                <div v-if="activeSection === 'reviewers'" class="rounded-3xl border bg-card p-6">
                    <div class="mb-6 flex items-center gap-3">
                        <Shield class="size-5 text-blue-500" />
                        <div>
                            <h2 class="text-lg font-semibold text-foreground">{{ t('reviewer_accounts') }}</h2>
                            <p class="text-sm text-muted-foreground">{{ t('reviewer_accounts_desc') }}</p>
                        </div>
                    </div>

                    <form @submit.prevent="submitReviewerAccounts" class="space-y-6">
                        <div v-for="slot in ['apple', 'google']" :key="slot" class="rounded-xl border bg-muted/30 p-4">
                            <div class="mb-3 flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-foreground">{{ t(slot === 'apple' ? 'apple_reviewer' : 'google_reviewer') }}</h3>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    @click="copyToClipboard(`${reviewerForm[slot].email}\n${reviewerForm[slot].password}`)"
                                    :disabled="!reviewerForm[slot].email || !reviewerForm[slot].password"
                                >
                                    {{ t('copy_credentials') }}
                                </Button>
                            </div>
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div class="space-y-2">
                                    <label class="block text-xs font-medium text-foreground">{{ t('email') }}</label>
                                    <Input v-model="reviewerForm[slot].email" type="email" placeholder="reviewer@yourapp.com" />
                                </div>
                                <div class="space-y-2">
                                    <label class="block text-xs font-medium text-foreground">{{ t('password') }}</label>
                                    <Input v-model="reviewerForm[slot].password" type="text" placeholder="••••••••" />
                                </div>
                            </div>
                        </div>

                        <div class="pt-2">
                            <Button type="submit" :disabled="reviewerForm.processing">
                                <Loader2 v-if="reviewerForm.processing" class="me-2 h-4 w-4 animate-spin" />
                                {{ reviewerForm.processing ? t('saving') : t('save_reviewer_accounts') }}
                            </Button>
                        </div>
                    </form>
                </div>

                <!-- FCM Topics Section -->
                <div v-if="activeSection === 'topics'" class="rounded-3xl border bg-card p-6">
                    <div class="mb-6 flex items-center gap-3">
                        <Hash class="size-5 text-cyan-500" />
                        <div>
                            <h2 class="text-lg font-semibold text-foreground">{{ t('fcm_topics') }}</h2>
                            <p class="text-sm text-muted-foreground">{{ t('fcm_topics_desc') }}</p>
                        </div>
                    </div>

                    <form @submit.prevent="submitTopics" class="space-y-6">
                        <div class="rounded-xl border bg-muted/30 p-4">
                            <p class="mb-3 text-xs font-medium text-muted-foreground">{{ t('topics_list') }}</p>
                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="topic in topicsForm.topics"
                                    :key="topic"
                                    class="inline-flex items-center gap-2 rounded-full bg-primary/10 px-3 py-1 text-sm font-medium text-primary"
                                >
                                    {{ topic }}
                                    <button
                                        type="button"
                                        class="rounded-full p-0.5 transition-colors hover:bg-primary/20"
                                        @click="removeTopic(topic)"
                                    >
                                        <X class="size-3" />
                                    </button>
                                </span>
                                <span v-if="topicsForm.topics.length === 0" class="text-sm text-muted-foreground">
                                    {{ t('no_topics_yet') }}
                                </span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <Input
                                v-model="newTopic"
                                :placeholder="t('topic_name_placeholder')"
                                class="flex-1"
                                @keydown.enter.prevent="addTopic"
                            />
                            <Button type="button" variant="outline" @click="addTopic">
                                <Plus class="me-2 size-4" />
                                {{ t('add_topic') }}
                            </Button>
                        </div>

                        <p class="text-xs text-muted-foreground">{{ t('topic_name_hint') }}</p>

                        <div class="pt-2">
                            <Button type="submit" :disabled="topicsForm.processing">
                                <Loader2 v-if="topicsForm.processing" class="me-2 h-4 w-4 animate-spin" />
                                {{ topicsForm.processing ? t('saving') : t('save_topics') }}
                            </Button>
                        </div>
                    </form>

                    <!-- Test broadcast -->
                    <div class="mt-8 rounded-xl border border-dashed bg-muted/20 p-4">
                        <div class="mb-3 flex items-center gap-2">
                            <Send class="size-4 text-cyan-500" />
                            <h3 class="text-sm font-semibold text-foreground">{{ t('test_topic_broadcast') }}</h3>
                        </div>
                        <p class="mb-4 text-xs text-muted-foreground">{{ t('test_topic_broadcast_desc') }}</p>

                        <form @submit.prevent="sendTestTopic" class="space-y-3">
                            <div class="space-y-2">
                                <label class="block text-xs font-medium text-foreground">{{ t('topic') }}</label>
                                <select
                                    v-model="testTopicForm.topic"
                                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:ring-2 focus:ring-primary focus:outline-none"
                                >
                                    <option v-for="topic in topicsForm.topics" :key="topic" :value="topic">{{ topic }}</option>
                                </select>
                            </div>

                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div class="space-y-2">
                                    <label class="block text-xs font-medium text-foreground">{{ t('title') }}</label>
                                    <Input v-model="testTopicForm.title" type="text" />
                                </div>
                                <div class="space-y-2">
                                    <label class="block text-xs font-medium text-foreground">{{ t('body') }}</label>
                                    <Input v-model="testTopicForm.body" type="text" />
                                </div>
                            </div>

                            <Button type="submit" :disabled="testTopicForm.processing || !testTopicForm.topic">
                                <Loader2 v-if="testTopicForm.processing" class="me-2 h-4 w-4 animate-spin" />
                                <Send v-else class="me-2 h-4 w-4" />
                                {{ testTopicForm.processing ? t('sending') : t('send_test_broadcast') }}
                            </Button>
                        </form>
                    </div>
                </div>

                <!-- Production Database -->
                <div v-if="activeSection === 'deployment'" class="rounded-3xl border bg-card p-6">
                    <div class="mb-6 flex items-center gap-3">
                        <Database class="size-5 text-amber-500" />
                        <div>
                            <h2 class="text-lg font-semibold text-foreground">{{ t('production_db') }}</h2>
                            <p class="text-sm text-muted-foreground">{{ t('production_db_desc') }}</p>
                        </div>
                    </div>

                    <form @submit.prevent="submitProdDb" class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">{{ t('db_host') }}</label>
                                <div class="flex items-center gap-2">
                                    <Input v-model="prodDbForm.DB_HOST" type="text" placeholder="127.0.0.1"
                                        class="flex-1" />
                                    <Button type="button" variant="outline" size="sm"
                                        @click="prodDbForm.DB_HOST = '127.0.0.1'"
                                        class="shrink-0 text-xs">localhost</Button>
                                </div>
                            </div>
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">{{ t('db_port') }}</label>
                                <Input v-model="prodDbForm.DB_PORT" type="text" placeholder="3306" />
                            </div>
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">{{ t('db_database') }}</label>
                                <Input v-model="prodDbForm.DB_DATABASE" type="text" placeholder="production_db" />
                            </div>
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">{{ t('db_username') }}</label>
                                <Input v-model="prodDbForm.DB_USERNAME" type="text" placeholder="root" />
                            </div>
                            <div class="space-y-2 sm:col-span-2">
                                <label class="block text-sm font-medium text-foreground">{{ t('db_password') }}</label>
                                <Input v-model="prodDbForm.DB_PASSWORD" type="password" placeholder="••••••••" />
                            </div>
                        </div>

                        <div class="pt-2">
                            <Button type="submit" :disabled="prodDbForm.processing">
                                <Loader2 v-if="prodDbForm.processing" class="me-2 h-4 w-4 animate-spin" />
                                {{ prodDbForm.processing ? t('saving') : t('save_production_db') }}
                            </Button>
                        </div>
                    </form>
                </div>

                <!-- Local Mail -->
                <div v-if="activeSection === 'mail'" class="rounded-3xl border bg-card p-6">
                    <div class="mb-6 flex items-center gap-3">
                        <Mail class="size-5 text-emerald-500" />
                        <div>
                            <h2 class="text-lg font-semibold text-foreground">{{ t('local_mail') }}</h2>
                            <p class="text-sm text-muted-foreground">{{ t('local_mail_desc') }}</p>
                        </div>
                    </div>

                    <form @submit.prevent="submitLocalMail" class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">{{ t('mail_mailer') }}</label>
                                <Input v-model="localMailForm.MAIL_MAILER" type="text" placeholder="smtp" />
                            </div>
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">{{ t('mail_host') }}</label>
                                <Input v-model="localMailForm.MAIL_HOST" type="text" placeholder="smtp.example.com" />
                            </div>
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">{{ t('mail_port') }}</label>
                                <Input v-model="localMailForm.MAIL_PORT" type="text" placeholder="465" />
                            </div>
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">{{ t('mail_encryption')
                                    }}</label>
                                <Input v-model="localMailForm.MAIL_ENCRYPTION" type="text" placeholder="ssl" />
                            </div>
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">{{ t('mail_username')
                                    }}</label>
                                <Input v-model="localMailForm.MAIL_USERNAME" type="text"
                                    placeholder="user@example.com" />
                            </div>
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">{{ t('mail_password')
                                    }}</label>
                                <Input v-model="localMailForm.MAIL_PASSWORD" type="password" placeholder="••••••••" />
                            </div>
                            <div class="space-y-2 sm:col-span-2">
                                <label class="block text-sm font-medium text-foreground">{{ t('mail_from_address')
                                    }}</label>
                                <Input v-model="localMailForm.MAIL_FROM_ADDRESS" type="email"
                                    placeholder="no-reply@example.com" />
                            </div>
                        </div>

                        <div class="pt-2">
                            <Button type="submit" :disabled="localMailForm.processing">
                                <Loader2 v-if="localMailForm.processing" class="me-2 h-4 w-4 animate-spin" />
                                {{ localMailForm.processing ? t('saving') : t('save_local_mail') }}
                            </Button>
                        </div>
                    </form>
                </div>

                <!-- Production Mail -->
                <div v-if="activeSection === 'mail'" class="rounded-3xl border bg-card p-6">
                    <div class="mb-6 flex items-center gap-3">
                        <Mail class="size-5 text-sky-500" />
                        <div>
                            <h2 class="text-lg font-semibold text-foreground">{{ t('production_mail') }}</h2>
                            <p class="text-sm text-muted-foreground">{{ t('production_mail_desc') }}</p>
                        </div>
                    </div>

                    <form @submit.prevent="submitProdMail" class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">{{ t('mail_mailer') }}</label>
                                <Input v-model="prodMailForm.MAIL_MAILER" type="text" placeholder="smtp" />
                            </div>
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">{{ t('mail_host') }}</label>
                                <Input v-model="prodMailForm.MAIL_HOST" type="text" placeholder="smtp.example.com" />
                            </div>
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">{{ t('mail_port') }}</label>
                                <Input v-model="prodMailForm.MAIL_PORT" type="text" placeholder="465" />
                            </div>
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">{{ t('mail_encryption')
                                    }}</label>
                                <Input v-model="prodMailForm.MAIL_ENCRYPTION" type="text" placeholder="ssl" />
                            </div>
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">{{ t('mail_username')
                                    }}</label>
                                <Input v-model="prodMailForm.MAIL_USERNAME" type="text"
                                    placeholder="user@example.com" />
                            </div>
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">{{ t('mail_password')
                                    }}</label>
                                <Input v-model="prodMailForm.MAIL_PASSWORD" type="password" placeholder="••••••••" />
                            </div>
                            <div class="space-y-2 sm:col-span-2">
                                <label class="block text-sm font-medium text-foreground">{{ t('mail_from_address')
                                    }}</label>
                                <Input v-model="prodMailForm.MAIL_FROM_ADDRESS" type="email"
                                    placeholder="no-reply@example.com" />
                            </div>
                        </div>

                        <div class="pt-2">
                            <Button type="submit" :disabled="prodMailForm.processing">
                                <Loader2 v-if="prodMailForm.processing" class="me-2 h-4 w-4 animate-spin" />
                                {{ prodMailForm.processing ? t('saving') : t('save_production_mail') }}
                            </Button>
                        </div>
                    </form>
                </div>

                <!-- Branding -->
                <div v-if="activeSection === 'general'" class="rounded-3xl border bg-card p-6">
                    <div class="mb-6 flex items-center gap-3">
                        <ImageIcon class="size-5 text-primary" />
                        <div>
                            <h2 class="text-lg font-semibold text-foreground">{{ t('branding') }}</h2>
                            <p class="text-sm text-muted-foreground">{{ t('branding_desc') }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <!-- Logo -->
                        <div class="space-y-4">
                            <h3 class="text-sm font-medium text-foreground">{{ t('logo') }}</h3>
                            <div class="flex items-center gap-4">
                                <div
                                    class="flex h-16 w-40 items-center justify-center overflow-hidden rounded-lg border bg-muted/50 p-2">
                                    <img :src="'/images/logo.png?' + Date.now()" alt="Logo"
                                        class="max-h-full max-w-full object-contain" />
                                </div>
                                <button @click="copyLogoPath"
                                    class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground">
                                    <Check v-if="logoCopied" class="size-4 text-emerald-500" />
                                    <Clipboard v-else class="size-4" />
                                    {{ logoCopied ? t('copied') : t('copy_logo_path') }}
                                </button>
                            </div>
                            <form @submit.prevent="uploadLogo" class="space-y-3">
                                <ImageUpload v-model="logoForm.logo" :removable="false" :error="logoForm.errors.logo" />
                                <Button type="submit" :disabled="logoForm.processing || !logoForm.logo" class="w-full sm:w-auto">
                                    <Loader2 v-if="logoForm.processing" class="me-2 h-4 w-4 animate-spin" />
                                    {{ logoForm.processing ? t('uploading') : t('upload_logo') }}
                                </Button>
                            </form>
                        </div>

                        <!-- Favicon -->
                        <div class="space-y-4">
                            <h3 class="text-sm font-medium text-foreground">{{ t('favicon') }}</h3>
                            <div class="flex items-center gap-4">
                                <div
                                    class="flex h-16 w-16 items-center justify-center overflow-hidden rounded-lg border bg-muted/50 p-2">
                                    <img :src="'/favicon.ico?' + Date.now()" alt="Favicon"
                                        class="max-h-full max-w-full object-contain" />
                                </div>
                            </div>
                            <form @submit.prevent="uploadFavicon" class="space-y-3">
                                <ImageUpload v-model="faviconForm.favicon" :removable="false" accept="image/*,.ico" :error="faviconForm.errors.favicon" />
                                <Button type="submit" :disabled="faviconForm.processing || !faviconForm.favicon" class="w-full sm:w-auto">
                                    <Loader2 v-if="faviconForm.processing" class="me-2 h-4 w-4 animate-spin" />
                                    {{ faviconForm.processing ? t('uploading') : t('upload_favicon') }}
                                </Button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Firebase Configuration -->
                <div v-if="activeSection === 'notifications'" class="rounded-3xl border bg-card p-6">
                    <div class="mb-6 flex items-center gap-3">
                        <Flame class="size-5 text-orange-500" />
                        <div>
                            <h2 class="text-lg font-semibold text-foreground">{{ t('firebase_config') }}</h2>
                            <p class="text-sm text-muted-foreground">{{ t('firebase_config_desc') }}</p>
                        </div>
                    </div>

                    <!-- Status -->
                    <div class="mb-6 flex items-center gap-2">
                        <CheckCircle v-if="firebaseConfigExists" class="size-4 text-emerald-500" />
                        <XCircle v-else class="size-4 text-muted-foreground" />
                        <span class="text-sm"
                            :class="firebaseConfigExists ? 'text-emerald-600' : 'text-muted-foreground'">
                            {{ firebaseConfigExists ? t('firebase_configured') : t('firebase_not_configured') }}
                        </span>
                        <span v-if="firebaseCredentialsPath" class="text-xs font-mono text-muted-foreground ms-2">
                            {{ firebaseCredentialsPath }}
                        </span>
                    </div>

                    <!-- Upload -->
                    <form @submit.prevent="uploadFirebase" class="space-y-4">
                        <div>
                            <input type="file" accept=".json" @change="handleFirebaseFile"
                                class="block w-full text-sm text-muted-foreground file:me-4 file:rounded-full file:border-0 file:bg-primary/10 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-primary hover:file:bg-primary/20" />
                        </div>
                        <Button type="submit" :disabled="firebaseForm.processing || !firebaseForm.firebase_json">
                            <Loader2 v-if="firebaseForm.processing" class="me-2 h-4 w-4 animate-spin" />
                            {{ firebaseForm.processing ? t('uploading') : t('upload_firebase_json') }}
                        </Button>
                    </form>

                    <!-- Test FCM -->
                    <div v-if="firebaseConfigExists" class="mt-6 border-t border-border pt-6">
                        <h3 class="mb-3 text-sm font-medium text-foreground">{{ t('test_fcm') }}</h3>
                        <div class="flex items-center gap-3">
                            <Input v-model="fcmToken" type="text" :placeholder="t('fcm_token_placeholder')"
                                class="flex-1" />
                            <Button :disabled="testingFcm || !fcmToken" @click="sendTestFcm">
                                <Loader2 v-if="testingFcm" class="me-2 h-4 w-4 animate-spin" />
                                <Send v-else class="me-2 size-4" />
                                {{ t('send') }}
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Light Theme Colors -->
                <div v-if="activeSection === 'appearance'" class="rounded-3xl border bg-card p-6">
                    <div class="mb-6 flex items-center gap-3">
                        <Sun class="size-5 text-yellow-500" />
                        <div>
                            <h2 class="text-lg font-semibold text-foreground">{{ t('light_theme_colors') }}</h2>
                            <p class="text-sm text-muted-foreground">{{ t('theme_colors_desc') }}</p>
                        </div>
                    </div>

                    <form @submit.prevent="submitLight" class="space-y-6">
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                            <div v-for="(value, varName) in lightColors" :key="varName" class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">{{ varName }}</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" :value="lightHex[varName]"
                                        @input="lightHex[varName] = $event.target.value"
                                        class="h-10 w-14 cursor-pointer rounded-lg border border-input p-1" />
                                    <div class="flex flex-1 items-center rounded-lg border border-input px-3 py-2">
                                        <span class="font-mono text-sm text-foreground">{{ lightHex[varName] }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="pt-4">
                            <Button type="submit" :disabled="lightForm.processing">
                                <Loader2 v-if="lightForm.processing" class="me-2 h-4 w-4 animate-spin" />
                                {{ lightForm.processing ? t('saving') : t('save_colors') }}
                            </Button>
                        </div>
                    </form>
                </div>

                <!-- Dark Theme Colors -->
                <div v-if="activeSection === 'appearance'" class="rounded-3xl border bg-card p-6">
                    <div class="mb-6 flex items-center gap-3">
                        <Moon class="size-5 text-indigo-500" />
                        <div>
                            <h2 class="text-lg font-semibold text-foreground">{{ t('dark_theme_colors') }}</h2>
                            <p class="text-sm text-muted-foreground">{{ t('theme_colors_desc') }}</p>
                        </div>
                    </div>

                    <form @submit.prevent="submitDark" class="space-y-6">
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                            <div v-for="(value, varName) in darkColors" :key="varName" class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">{{ varName }}</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" :value="darkHex[varName]"
                                        @input="darkHex[varName] = $event.target.value"
                                        class="h-10 w-14 cursor-pointer rounded-lg border border-input p-1" />
                                    <div class="flex flex-1 items-center rounded-lg border border-input px-3 py-2">
                                        <span class="font-mono text-sm text-foreground">{{ darkHex[varName] }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="pt-4">
                            <Button type="submit" :disabled="darkForm.processing">
                                <Loader2 v-if="darkForm.processing" class="me-2 h-4 w-4 animate-spin" />
                                {{ darkForm.processing ? t('saving') : t('save_colors') }}
                            </Button>
                        </div>
                    </form>
                </div>

                <!-- Auth Configuration -->
                <div v-if="activeSection === 'authentication'" class="rounded-3xl border bg-card p-6">
                    <div class="mb-6 flex items-center gap-3">
                        <ShieldCheck class="size-5 text-violet-500" />
                        <div>
                            <h2 class="text-lg font-semibold text-foreground">{{ t('auth_config') }}</h2>
                            <p class="text-sm text-muted-foreground">{{ t('auth_config_desc') }}</p>
                        </div>
                    </div>

                    <form @submit.prevent="submitAuth" class="space-y-6">
                        <!-- Identifier Selection -->
                        <div>
                            <h3 class="mb-1 text-sm font-medium text-foreground">{{ t('login_identifiers') }}</h3>
                            <p class="mb-3 text-xs text-muted-foreground">{{ t('login_identifiers_desc') }}</p>
                            <div class="flex w-max gap-2 rounded-xl bg-muted p-2">
                                <Button type="button" @click="toggleIdentifier('email')"
                                    :class="authForm.identifiers.includes('email') ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground hover:bg-muted/80'">
                                    {{ t('email') }}
                                </Button>
                                <Button type="button" @click="toggleIdentifier('phone')"
                                    :class="authForm.identifiers.includes('phone') ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground hover:bg-muted/80'">
                                    {{ t('phone') }}
                                </Button>
                            </div>
                        </div>

                        <!-- Additional Fields -->
                        <div>
                            <h3 class="mb-1 text-sm font-medium text-foreground">{{ t('additional_fields') }}</h3>
                            <p class="mb-3 text-xs text-muted-foreground">{{ t('additional_fields_desc') }}</p>
                            <div class="flex flex-wrap gap-6">
                                <label v-if="!authForm.identifiers.includes('email')"
                                    class="flex items-center gap-2 text-sm text-foreground">
                                    <Checkbox v-model="authForm.has_email_field" />
                                    {{ t('email') }}
                                </label>
                                <label v-if="!authForm.identifiers.includes('phone')"
                                    class="flex items-center gap-2 text-sm text-foreground">
                                    <Checkbox v-model="authForm.has_phone_field" />
                                    {{ t('phone') }}
                                </label>
                                <label class="flex flex-col gap-1 text-sm text-foreground">
                                    <span class="flex items-center gap-2">
                                        <Checkbox v-model="authForm.has_username_field" />
                                        {{ t('username') }}
                                    </span>
                                    <span class="ms-7 text-xs text-muted-foreground">{{ t('username_login_alias_note') }}</span>
                                </label>
                            </div>
                        </div>

                        <!-- Login Method -->
                        <div>
                            <h3 class="mb-1 text-sm font-medium text-foreground">{{ t('login_method') }}</h3>
                            <p class="mb-3 text-xs text-muted-foreground">{{ t('login_method_desc') }}</p>
                            <div class="flex flex-wrap gap-3">
                                <label
                                    class="flex flex-1 cursor-pointer items-start gap-3 rounded-xl border bg-muted/30 p-4"
                                    :class="authForm.auth_mode === 'password' ? 'border-primary bg-primary/5' : ''"
                                >
                                    <input
                                        type="radio"
                                        value="password"
                                        v-model="authForm.auth_mode"
                                        class="mt-1 size-4 cursor-pointer accent-primary"
                                    />
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-foreground">{{ t('login_with_password') }}</p>
                                        <p class="mt-1 text-xs text-muted-foreground">{{ t('login_with_password_desc') }}</p>
                                    </div>
                                </label>
                                <label
                                    class="flex flex-1 cursor-pointer items-start gap-3 rounded-xl border bg-muted/30 p-4"
                                    :class="authForm.auth_mode === 'otp' ? 'border-primary bg-primary/5' : ''"
                                >
                                    <input
                                        type="radio"
                                        value="otp"
                                        v-model="authForm.auth_mode"
                                        class="mt-1 size-4 cursor-pointer accent-primary"
                                    />
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-foreground">{{ t('login_with_otp') }}</p>
                                        <p class="mt-1 text-xs text-muted-foreground">{{ t('login_with_otp_desc') }}</p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div class="pt-2">
                            <Button type="submit" :disabled="authForm.processing">
                                <Loader2 v-if="authForm.processing" class="me-2 h-4 w-4 animate-spin" />
                                {{ authForm.processing ? t('saving') : t('save_auth_config') }}
                            </Button>
                        </div>
                    </form>
                </div>

                <!-- Social Auth Configuration -->
                <div v-if="activeSection === 'authentication'" class="rounded-3xl border bg-card p-6">
                    <div class="mb-6 flex items-center gap-3">
                        <ShieldCheck class="size-5 text-pink-500" />
                        <div>
                            <h2 class="text-lg font-semibold text-foreground">{{ t('social_auth_config') }}</h2>
                            <p class="text-sm text-muted-foreground">{{ t('social_auth_config_desc') }}</p>
                        </div>
                    </div>

                    <form @submit.prevent="submitSocialAuth" class="space-y-6">
                        <!-- Allowed Providers -->
                        <div>
                            <h3 class="mb-1 text-sm font-medium text-foreground">{{ t('allowed_providers') }}</h3>
                            <p class="mb-3 text-xs text-muted-foreground">{{ t('allowed_providers_desc') }}</p>
                            <div class="flex flex-wrap gap-2">
                                <Button v-for="provider in availableProviders" :key="provider.id" type="button"
                                    @click="toggleProvider(provider.id)"
                                    :class="socialAuthForm.providers.includes(provider.id) ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground hover:bg-muted/80'">
                                    {{ provider.name }}
                                </Button>
                            </div>
                        </div>

                        <!-- Max Social Accounts -->
                        <div>
                            <h3 class="mb-1 text-sm font-medium text-foreground">{{ t('max_social_accounts') }}</h3>
                            <p class="mb-3 text-xs text-muted-foreground">{{ t('max_social_accounts_desc') }}</p>
                            <div class="flex w-max gap-2 rounded-xl bg-muted p-2">
                                <Button type="button" @click="socialAuthForm.max_accounts = 1"
                                    :class="socialAuthForm.max_accounts === 1 ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground hover:bg-muted/80'">
                                    {{ t('one_account') }}
                                </Button>
                                <Button type="button" @click="socialAuthForm.max_accounts = 0"
                                    :class="socialAuthForm.max_accounts === 0 ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground hover:bg-muted/80'">
                                    {{ t('unlimited') }}
                                </Button>
                            </div>
                        </div>

                        <div class="pt-2">
                            <Button type="submit" :disabled="socialAuthForm.processing">
                                <Loader2 v-if="socialAuthForm.processing" class="me-2 h-4 w-4 animate-spin" />
                                {{ socialAuthForm.processing ? t('saving') : t('save_social_auth_config') }}
                            </Button>
                        </div>
                    </form>
                </div>

                <!-- Validation Settings -->
                <div v-if="activeSection === 'validation'" class="rounded-3xl border bg-card p-6">
                    <div class="mb-6 flex items-center gap-3">
                        <Shield class="size-5 text-teal-500" />
                        <div>
                            <h2 class="text-lg font-semibold text-foreground">{{ t('validation_settings') }}</h2>
                            <p class="text-sm text-muted-foreground">{{ t('validation_settings_desc') }}</p>
                        </div>
                    </div>

                    <form @submit.prevent="submitValidation" class="space-y-6">
                        <!-- Allowed Phone Countries -->
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <label class="block text-sm font-medium text-foreground">{{ t('allowed_phone_countries')
                                    }}</label>
                                <Button type="button" size="sm" @click="toggleAllCountries"
                                    :class="isAllCountries() ? 'bg-primary text-primary-foreground' : 'bg-muted text-foreground hover:bg-muted/80'">
                                    {{ isAllCountries() ? t('all_selected') : t('select_all') }}
                                </Button>
                            </div>
                            <div class="relative">
                                <Search
                                    class="absolute start-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
                                <input v-model="countrySearch" type="text" :placeholder="t('search_countries')"
                                    class="w-full rounded-lg border border-input bg-background py-2 ps-9 pe-3 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring" />
                            </div>
                            <div
                                class="flex flex-wrap gap-2 max-h-[300px] overflow-y-auto rounded-xl border border-border p-3">
                                <button v-for="country in filteredCountries" :key="country.code" type="button"
                                    @click="toggleCountry(country.code)"
                                    class="flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-sm transition-colors"
                                    :class="isCountrySelected(country.code) ? 'bg-primary text-primary-foreground border-primary' : 'bg-card text-foreground border-border hover:bg-muted'">
                                    <span class="font-medium">{{ country.code }}</span>
                                    <span class="text-xs opacity-70">{{ country.name }}</span>
                                </button>
                            </div>
                            <p class="text-xs text-muted-foreground">{{ t('allowed_phone_countries_hint') }}</p>
                        </div>

                        <!-- Allowed Email Domains -->
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <label class="block text-sm font-medium text-foreground">{{ t('allowed_email_domains')
                                    }}</label>
                                <Button type="button" size="sm" @click="toggleAllDomains"
                                    :class="isAllDomains() ? 'bg-primary text-primary-foreground' : 'bg-muted text-foreground hover:bg-muted/80'">
                                    {{ isAllDomains() ? t('all_selected') : t('select_all') }}
                                </Button>
                            </div>
                            <!-- Selected domains chips -->
                            <div v-if="!isAllDomains() && getSelectedDomains().length > 0" class="flex flex-wrap gap-2">
                                <span v-for="domain in getSelectedDomains()" :key="domain"
                                    class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-3 py-1 text-sm text-primary">
                                    {{ domain }}
                                    <button type="button" @click="removeDomain(domain)"
                                        class="rounded-full p-0.5 hover:bg-primary/20">
                                        <X class="size-3" />
                                    </button>
                                </span>
                            </div>
                            <!-- Quick add domain chips -->
                            <div class="flex flex-wrap gap-2">
                                <button v-for="domain in commonEmailDomains" :key="domain" type="button"
                                    @click="addDomain(domain)" :disabled="isDomainSelected(domain)"
                                    class="rounded-lg border px-3 py-1.5 text-sm transition-colors"
                                    :class="isDomainSelected(domain) ? 'bg-primary/10 text-primary border-primary/30 cursor-not-allowed' : 'bg-card text-foreground border-border hover:bg-muted hover:border-primary'">
                                    {{ domain }}
                                </button>
                            </div>
                            <!-- Custom domain input -->
                            <div class="flex items-center gap-2">
                                <Input v-model="customDomain" type="text" :placeholder="t('custom_domain_placeholder')"
                                    class="flex-1" @keydown.enter.prevent="addCustomDomain" />
                                <Button type="button" variant="outline" size="sm" @click="addCustomDomain"
                                    :disabled="!customDomain">
                                    <Plus class="size-4" />
                                </Button>
                            </div>
                            <p class="text-xs text-muted-foreground">{{ t('allowed_email_domains_hint') }}</p>
                        </div>

                        <div class="pt-2">
                            <Button type="submit" :disabled="validationForm.processing">
                                <Loader2 v-if="validationForm.processing" class="me-2 h-4 w-4 animate-spin" />
                                {{ validationForm.processing ? t('saving') : t('save_validation_config') }}
                            </Button>
                        </div>
                    </form>
                </div>

                <!-- Rate Limiting -->
                <div v-if="activeSection === 'validation'" class="rounded-3xl border bg-card p-6">
                    <div class="mb-6 flex items-center gap-3">
                        <Shield class="size-5 text-blue-500" />
                        <div>
                            <h2 class="text-lg font-semibold text-foreground">{{ t('rate_limiting') }}</h2>
                            <p class="text-sm text-muted-foreground">{{ t('rate_limiting_desc') }}</p>
                        </div>
                    </div>

                    <form @submit.prevent="submitRateLimiting" class="space-y-6">
                        <div class="grid gap-6 lg:grid-cols-3">
                            <!-- API Rate Limit -->
                            <div class="rounded-xl border bg-muted/30 p-4">
                                <h3 class="text-sm font-semibold text-foreground mb-3 flex items-center gap-2">
                                    <span class="size-2 rounded-full bg-blue-500"></span>
                                    {{ t('api_rate_limit') }}
                                </h3>
                                <p class="text-xs text-muted-foreground mb-3">{{ t('api_rate_limit_desc') }}</p>
                                <div class="space-y-3">
                                    <div>
                                        <label class="block text-xs font-medium text-foreground mb-1">{{ t('requests_per_window') }}</label>
                                        <Input v-model.number="rateLimitForm.api_limit" type="number" min="1" max="1000" class="h-9" />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-foreground mb-1">{{ t('decay_minutes') }}</label>
                                        <Input v-model.number="rateLimitForm.api_decay" type="number" min="1" max="60" class="h-9" />
                                    </div>
                                </div>
                            </div>

                            <!-- Auth Rate Limit -->
                            <div class="rounded-xl border bg-muted/30 p-4">
                                <h3 class="text-sm font-semibold text-foreground mb-3 flex items-center gap-2">
                                    <span class="size-2 rounded-full bg-yellow-500"></span>
                                    {{ t('auth_rate_limit') }}
                                </h3>
                                <p class="text-xs text-muted-foreground mb-3">{{ t('auth_rate_limit_desc') }}</p>
                                <div class="space-y-3">
                                    <div>
                                        <label class="block text-xs font-medium text-foreground mb-1">{{ t('requests_per_window') }}</label>
                                        <Input v-model.number="rateLimitForm.auth_limit" type="number" min="1" max="100" class="h-9" />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-foreground mb-1">{{ t('decay_minutes') }}</label>
                                        <Input v-model.number="rateLimitForm.auth_decay" type="number" min="1" max="60" class="h-9" />
                                    </div>
                                </div>
                            </div>

                            <!-- OTP Rate Limit -->
                            <div class="rounded-xl border bg-muted/30 p-4">
                                <h3 class="text-sm font-semibold text-foreground mb-3 flex items-center gap-2">
                                    <span class="size-2 rounded-full bg-red-500"></span>
                                    {{ t('otp_rate_limit') }}
                                </h3>
                                <p class="text-xs text-muted-foreground mb-3">{{ t('otp_rate_limit_desc') }}</p>
                                <div class="space-y-3">
                                    <div>
                                        <label class="block text-xs font-medium text-foreground mb-1">{{ t('requests_per_window') }}</label>
                                        <Input v-model.number="rateLimitForm.otp_limit" type="number" min="1" max="20" class="h-9" />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-foreground mb-1">{{ t('decay_minutes') }}</label>
                                        <Input v-model.number="rateLimitForm.otp_decay" type="number" min="1" max="60" class="h-9" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="pt-2">
                            <Button type="submit" :disabled="rateLimitForm.processing">
                                <Loader2 v-if="rateLimitForm.processing" class="me-2 h-4 w-4 animate-spin" />
                                {{ rateLimitForm.processing ? t('saving') : t('save_rate_limit_config') }}
                            </Button>
                        </div>
                    </form>
                </div>

                <!-- Account Deletion Retention -->
                <div v-if="activeSection === 'validation'" class="rounded-3xl border bg-card p-6">
                    <div class="mb-6 flex items-center gap-3">
                        <Shield class="size-5 text-orange-500" />
                        <div>
                            <h2 class="text-lg font-semibold text-foreground">{{ t('account_deletion_config') }}</h2>
                            <p class="text-sm text-muted-foreground">{{ t('account_deletion_config_desc') }}</p>
                        </div>
                    </div>

                    <form @submit.prevent="submitAccountDeletion" class="space-y-6">
                        <div class="rounded-xl border bg-muted/30 p-4">
                            <label class="block text-sm font-medium text-foreground mb-2">{{ t('retention_days') }}</label>
                            <p class="text-xs text-muted-foreground mb-3">{{ t('retention_days_desc') }}</p>
                            <Input v-model.number="accountDeletionForm.retention_days" type="number" min="1" max="365" class="h-9 max-w-xs" />
                        </div>

                        <div class="pt-2">
                            <Button type="submit" :disabled="accountDeletionForm.processing">
                                <Loader2 v-if="accountDeletionForm.processing" class="me-2 h-4 w-4 animate-spin" />
                                {{ accountDeletionForm.processing ? t('saving') : t('save_account_deletion_config') }}
                            </Button>
                        </div>
                    </form>
                </div>

                <!-- Pusher Broadcasting -->
                <div v-if="activeSection === 'pusher'" class="rounded-3xl border bg-card p-6">
                    <div class="mb-6 flex items-center gap-3">
                        <Send class="size-5 text-purple-500" />
                        <div>
                            <h2 class="text-lg font-semibold text-foreground">{{ t('pusher_settings') }}</h2>
                            <p class="text-sm text-muted-foreground">{{ t('pusher_settings_desc') }}</p>
                        </div>
                    </div>

                    <div class="grid gap-6 lg:grid-cols-2">
                        <!-- Local Pusher Config -->
                        <div class="rounded-xl border bg-muted/30 p-4">
                            <h3 class="text-sm font-semibold text-foreground mb-3 flex items-center gap-2">
                                <span class="size-2 rounded-full bg-green-500"></span>
                                {{ t('local') }}
                            </h3>
                            <form @submit.prevent="submitLocalPusher" class="space-y-3">
                                <div>
                                    <label class="block text-xs font-medium text-foreground mb-1">{{ t('pusher_app_id')
                                        }}</label>
                                    <Input v-model="localPusherForm.app_id" :placeholder="t('pusher_app_id')"
                                        class="h-9" />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-foreground mb-1">{{ t('pusher_app_key')
                                        }}</label>
                                    <Input v-model="localPusherForm.app_key" :placeholder="t('pusher_app_key')"
                                        class="h-9" />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-foreground mb-1">{{
                                        t('pusher_app_secret') }}</label>
                                    <Input v-model="localPusherForm.app_secret" type="password"
                                        :placeholder="t('pusher_app_secret')" class="h-9" />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-foreground mb-1">{{
                                        t('pusher_app_cluster') }}</label>
                                    <Input v-model="localPusherForm.app_cluster" :placeholder="t('pusher_app_cluster')"
                                        class="h-9" />
                                </div>
                                <Button type="submit" size="sm" :disabled="localPusherForm.processing">
                                    <Loader2 v-if="localPusherForm.processing" class="me-2 h-4 w-4 animate-spin" />
                                    {{ localPusherForm.processing ? t('saving') : t('save') }}
                                </Button>
                            </form>
                        </div>

                        <!-- Production Pusher Config -->
                        <div class="rounded-xl border bg-muted/30 p-4">
                            <h3 class="text-sm font-semibold text-foreground mb-3 flex items-center gap-2">
                                <span class="size-2 rounded-full bg-orange-500"></span>
                                {{ t('production') }}
                            </h3>
                            <form @submit.prevent="submitProductionPusher" class="space-y-3">
                                <div>
                                    <label class="block text-xs font-medium text-foreground mb-1">{{ t('pusher_app_id')
                                        }}</label>
                                    <Input v-model="productionPusherForm.app_id" :placeholder="t('pusher_app_id')"
                                        class="h-9" />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-foreground mb-1">{{ t('pusher_app_key')
                                        }}</label>
                                    <Input v-model="productionPusherForm.app_key" :placeholder="t('pusher_app_key')"
                                        class="h-9" />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-foreground mb-1">{{
                                        t('pusher_app_secret') }}</label>
                                    <Input v-model="productionPusherForm.app_secret" type="password"
                                        :placeholder="t('pusher_app_secret')" class="h-9" />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-foreground mb-1">{{
                                        t('pusher_app_cluster') }}</label>
                                    <Input v-model="productionPusherForm.app_cluster"
                                        :placeholder="t('pusher_app_cluster')" class="h-9" />
                                </div>
                                <Button type="submit" size="sm" :disabled="productionPusherForm.processing">
                                    <Loader2 v-if="productionPusherForm.processing" class="me-2 h-4 w-4 animate-spin" />
                                    {{ productionPusherForm.processing ? t('saving') : t('save') }}
                                </Button>
                            </form>
                        </div>
                    </div>

                    <!-- Test Broadcast -->
                    <div class="mt-6 pt-6 border-t border-border">
                        <h3 class="text-sm font-medium text-foreground mb-1">{{ t('test_broadcast') }}</h3>
                        <p class="text-xs text-muted-foreground mb-3">{{ t('test_broadcast_desc') }}</p>
                        <form @submit.prevent="submitTestBroadcast" class="flex items-end gap-3">
                            <div class="flex-1">
                                <label class="block text-sm font-medium text-foreground mb-1">{{ t('select_user')
                                    }}</label>
                                <Input v-model="testBroadcastForm.user_id" type="number" placeholder="User ID" />
                            </div>
                            <Button type="submit"
                                :disabled="testBroadcastForm.processing || !testBroadcastForm.user_id">
                                <Loader2 v-if="testBroadcastForm.processing" class="me-2 h-4 w-4 animate-spin" />
                                <Send v-else class="me-2 h-4 w-4" />
                                {{ t('send_test_broadcast') }}
                            </Button>
                        </form>
                    </div>
                </div>

                <!-- Environment Toggles -->
                <div v-if="activeSection === 'environment'" class="rounded-3xl border bg-card p-6">
                    <h2 class="mb-1 text-lg font-semibold text-foreground">{{ t('env_toggles') }}</h2>
                    <p class="mb-6 text-sm text-muted-foreground">{{ t('env_toggles_desc') }}</p>

                    <div class="divide-y divide-border">
                        <div v-for="key in envToggles" :key="key" class="flex items-center justify-between py-4">
                            <div>
                                <p class="text-sm font-medium text-foreground">{{ envLabel(key) }}</p>
                                <p class="text-xs text-muted-foreground">{{ envDescription(key) }}</p>
                                <p class="mt-1 font-mono text-xs text-muted-foreground">{{ key }}</p>
                            </div>
                            <div class="flex items-center gap-4">
                                <!-- Local toggle -->
                                <div v-if="key === 'IS_TESTING'" class="flex items-center gap-2">
                                    <span class="text-xs text-muted-foreground">{{ t('local') }}</span>
                                </div>
                                <button
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                    :class="envValues[key] ? 'bg-primary' : 'bg-muted'"
                                    :disabled="envToggling[key]"
                                    @click="toggleEnv(key, envValues[key])">
                                    <span
                                        class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                        :class="envValues[key] ? 'translate-x-6 rtl:-translate-x-6' : 'translate-x-1 rtl:-translate-x-1'" />
                                </button>
                                <!-- Production toggle (IS_TESTING only) -->
                                <template v-if="key === 'IS_TESTING' && productionTesting !== null">
                                    <div class="flex items-center gap-2 border-s border-border ps-4">
                                        <span class="text-xs text-muted-foreground">{{ t('production') }}</span>
                                    </div>
                                    <button
                                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:outline-none"
                                        :class="productionTesting ? 'bg-primary' : 'bg-muted'"
                                        :disabled="prodEnvToggling['IS_TESTING']"
                                        @click="toggleProdEnv('IS_TESTING', productionTesting)">
                                        <span
                                            class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                            :class="productionTesting ? 'translate-x-6 rtl:-translate-x-6' : 'translate-x-1 rtl:-translate-x-1'" />
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Deploy Options Modal -->
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="showDeployModal" class="fixed inset-0 z-50 overflow-y-auto" @click.self="closeDeployModal">
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
                <div class="relative flex min-h-full items-center justify-center p-4">
                    <div class="relative w-full max-w-lg transform overflow-hidden rounded-2xl bg-card p-6 shadow-xl transition-all text-start">
                        <!-- Header -->
                        <div class="mb-6 flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-500/10">
                                <Rocket class="size-5 text-emerald-500" />
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-foreground">{{ t('deploy_options') }}</h3>
                                <p class="text-sm text-muted-foreground">{{ t('deploy_options_desc') }}</p>
                            </div>
                        </div>

                        <!-- Migration Options -->
                        <div class="space-y-4">
                            <label class="block text-sm font-medium text-foreground">{{ t('database_migration') }}</label>

                            <div class="space-y-3">
                                <!-- Migrate only -->
                                <label class="flex items-start gap-3 rounded-xl border border-border p-4 cursor-pointer transition-colors"
                                    :class="deployOptions.migration_option === 'migrate' ? 'border-primary bg-primary/5' : 'hover:bg-muted/50'">
                                    <input type="radio" v-model="deployOptions.migration_option" value="migrate"
                                        class="mt-0.5 h-4 w-4 text-primary focus:ring-primary" />
                                    <div class="flex-1">
                                        <span class="font-medium text-foreground">{{ t('migrate_only') }}</span>
                                        <p class="text-sm text-muted-foreground">{{ t('migrate_only_desc') }}</p>
                                    </div>
                                    <span class="rounded-full bg-emerald-500/10 px-2 py-0.5 text-xs font-medium text-emerald-600">{{ t('recommended') }}</span>
                                </label>

                                <!-- Migrate + Seed -->
                                <label class="flex items-start gap-3 rounded-xl border border-border p-4 cursor-pointer transition-colors"
                                    :class="deployOptions.migration_option === 'migrate_seed' ? 'border-primary bg-primary/5' : 'hover:bg-muted/50'">
                                    <input type="radio" v-model="deployOptions.migration_option" value="migrate_seed"
                                        class="mt-0.5 h-4 w-4 text-primary focus:ring-primary" />
                                    <div class="flex-1">
                                        <span class="font-medium text-foreground">{{ t('migrate_and_seed') }}</span>
                                        <p class="text-sm text-muted-foreground">{{ t('migrate_and_seed_desc') }}</p>
                                    </div>
                                </label>

                                <!-- Fresh + Seed -->
                                <label class="flex items-start gap-3 rounded-xl border border-red-500/50 p-4 cursor-pointer transition-colors"
                                    :class="deployOptions.migration_option === 'fresh_seed' ? 'border-red-500 bg-red-500/5' : 'hover:bg-red-500/5'">
                                    <input type="radio" v-model="deployOptions.migration_option" value="fresh_seed"
                                        class="mt-0.5 h-4 w-4 text-red-500 focus:ring-red-500" />
                                    <div class="flex-1">
                                        <span class="font-medium text-red-600">{{ t('fresh_migrate_seed') }}</span>
                                        <p class="text-sm text-red-500/80">{{ t('fresh_migrate_seed_desc') }}</p>
                                    </div>
                                    <span class="rounded-full bg-red-500/10 px-2 py-0.5 text-xs font-medium text-red-600">{{ t('destructive') }}</span>
                                </label>

                                <!-- No migration -->
                                <label class="flex items-start gap-3 rounded-xl border border-border p-4 cursor-pointer transition-colors"
                                    :class="deployOptions.migration_option === 'none' ? 'border-primary bg-primary/5' : 'hover:bg-muted/50'">
                                    <input type="radio" v-model="deployOptions.migration_option" value="none"
                                        class="mt-0.5 h-4 w-4 text-primary focus:ring-primary" />
                                    <div class="flex-1">
                                        <span class="font-medium text-foreground">{{ t('skip_migrations') }}</span>
                                        <p class="text-sm text-muted-foreground">{{ t('skip_migrations_desc') }}</p>
                                    </div>
                                </label>
                            </div>

                            <!-- Run Seeders Checkbox -->
                            <div v-if="deployOptions.migration_option === 'migrate' || deployOptions.migration_option === 'none'"
                                class="flex items-start gap-3 rounded-xl border border-border p-4">
                                <Checkbox v-model="deployOptions.run_seeders" class="mt-1 shrink-0" />
                                <div class="flex-1 min-w-0">
                                    <span class="font-medium text-foreground">{{ t('run_seeders_separately') }}</span>
                                    <p class="text-sm text-muted-foreground">{{ t('run_seeders_separately_desc') }}</p>
                                </div>
                            </div>

                            <!-- Safe Storage Deploy -->
                            <div class="flex items-start gap-3 rounded-xl border border-border p-4">
                                <Checkbox v-model="deployOptions.safe_storage_deploy" class="mt-1 shrink-0" />
                                <div class="flex-1 min-w-0">
                                    <span class="font-medium text-foreground">{{ t('safe_storage_deploy') }}</span>
                                    <p class="text-sm text-muted-foreground">{{ t('safe_storage_deploy_desc') }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Warning for fresh -->
                        <div v-if="deployOptions.migration_option === 'fresh_seed'"
                            class="mt-4 rounded-xl bg-red-500/10 border border-red-500/30 p-4">
                            <div class="flex items-center gap-2 text-red-600">
                                <svg class="size-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <span class="font-medium">{{ t('warning') }}</span>
                            </div>
                            <p class="mt-2 text-sm text-red-600/80">{{ t('fresh_warning_message') }}</p>
                        </div>

                        <!-- Footer -->
                        <div class="mt-6 flex gap-3">
                            <Button type="button" variant="outline" @click="closeDeployModal" class="flex-1">
                                {{ t('cancel') }}
                            </Button>
                            <Button type="button" @click="deployToProduction" class="flex-1"
                                :class="deployOptions.migration_option === 'fresh_seed'
                                    ? 'bg-red-600 hover:bg-red-700 text-white'
                                    : 'bg-emerald-600 hover:bg-emerald-700 text-white'">
                                <Rocket class="me-2 size-4" />
                                {{ t('deploy_now') }}
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>

    <!-- Deploy Log Modal -->
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="showLogModal" class="fixed inset-0 z-50 overflow-y-auto" @click.self="showLogModal = false">
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
                <div class="relative flex min-h-full items-center justify-center p-4">
                    <div class="relative w-full max-w-3xl transform overflow-hidden rounded-2xl bg-card p-6 shadow-xl transition-all text-start">
                        <!-- Header -->
                        <div class="mb-4 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-sky-500/10">
                                    <FileCode class="size-5 text-sky-500" />
                                </div>
                                <h3 class="text-lg font-semibold text-foreground">{{ t('deploy_log') }}</h3>
                            </div>
                            <button @click="showLogModal = false"
                                class="rounded-full p-1 text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground">
                                <X class="h-5 w-5" />
                            </button>
                        </div>

                        <pre
                            class="max-h-[60vh] overflow-auto rounded-xl bg-muted p-4 text-xs text-muted-foreground font-mono whitespace-pre-wrap">{{ deployLog }}</pre>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
