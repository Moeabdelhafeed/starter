<script setup>
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Database, Shield, Search, Plus, X, Loader2 } from 'lucide-vue-next';
import SettingsCard from '@/components/dev-setting/SettingsCard.vue';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';

const { t } = useI18n();

const props = defineProps({
    baseDb: { type: Object, default: () => ({}) },
    validationConfig: { type: Object, default: () => ({}) },
    rateLimitConfig: { type: Object, default: () => ({}) },
    accountDeletionConfig: { type: Object, default: () => ({}) },
});

// Base DB (writes .env.production — inherited by deploy targets)
const prodDbForm = useForm({
    DB_HOST: props.baseDb?.DB_HOST || '',
    DB_PORT: props.baseDb?.DB_PORT || '3306',
    DB_DATABASE: props.baseDb?.DB_DATABASE || '',
    DB_USERNAME: props.baseDb?.DB_USERNAME || '',
    DB_PASSWORD: props.baseDb?.DB_PASSWORD || '',
});

const submitProdDb = () => {
    prodDbForm.put(route('dev_settings.production_db'), {
        preserveScroll: true,
        preserveState: true,
        reset: ['baseDb', 'success', 'error'],
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
</script>

<template>
    <div class="space-y-5">
        <!-- Base Database -->
        <SettingsCard :title="t('base_db')" :description="t('base_db_desc')">
            <template #icon><Database class="size-5 text-amber-500" /></template>

            <div class="mb-4 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                <span>{{ t('base_db_note') }}</span>
                <code class="rounded bg-muted px-1.5 py-0.5 font-mono text-foreground">.env.production</code>
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
                        {{ prodDbForm.processing ? t('saving') : t('save_base_db') }}
                    </Button>
                </div>
            </form>
        </SettingsCard>

        <!-- Validation Settings -->
        <SettingsCard :title="t('validation_settings')" :description="t('validation_settings_desc')">
            <template #icon><Shield class="size-5 text-teal-500" /></template>

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
        </SettingsCard>

        <!-- Rate Limiting -->
        <SettingsCard :title="t('rate_limiting')" :description="t('rate_limiting_desc')">
            <template #icon><Shield class="size-5 text-blue-500" /></template>

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
        </SettingsCard>

        <!-- Account Deletion Retention -->
        <SettingsCard :title="t('account_deletion_config')" :description="t('account_deletion_config_desc')">
            <template #icon><Shield class="size-5 text-orange-500" /></template>

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
        </SettingsCard>
    </div>
</template>
