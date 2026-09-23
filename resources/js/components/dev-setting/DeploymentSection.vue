<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowDownCircle,
    ArrowUpCircle,
    CheckCircle,
    ChevronDown,
    ChevronRight,
    FileCode,
    FileEdit,
    FilePlus,
    GitBranch,
    GitCommit,
    Github,
    Link2,
    Loader2,
    Lock,
    Plus,
    RefreshCw,
    Rocket,
    Server,
    Upload,
    Wand2,
    X,
    XCircle,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

import SettingsCard from '@/components/dev-setting/SettingsCard.vue';
import Button from '@/components/ui/button/Button.vue';
import Checkbox from '@/components/ui/checkbox/Checkbox.vue';
import Input from '@/components/ui/input/Input.vue';
import BaseModal from '@/components/ui/modal/BaseModal.vue';
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select';

const { t } = useI18n();

/** The four deploy targets DevSettingController::$deployFlavors allows. */
type DeployFlavor = 'dev' | 'staging' | 'uat' | 'production';

/** SSH credentials for one target — DevSettingController::cleanSsh(). */
type SshConfig = {
    host: string;
    port: number;
    username: string;
    password: string;
};

/** Per-flavor database overrides — DevSettingController::blankDb(). */
type DbConfig = {
    DB_HOST: string;
    DB_PORT: string;
    DB_DATABASE: string;
    DB_USERNAME: string;
    DB_PASSWORD: string;
};

/** Per-flavor scalar env overrides — DevSettingController::blankFlavorEnv(). `'inherit'` = use the base value. */
type FlavorEnv = {
    APP_DEBUG: string;
    IS_TESTING: string;
    ALLOW_CONTENT_SEEDING: string;
    FRONTEND_URL: string;
};

/** Per-flavor Pusher overrides — DevSettingController::blankPusher() (raw env key names). */
type PusherEnv = {
    PUSHER_APP_ID: string;
    PUSHER_APP_KEY: string;
    PUSHER_APP_SECRET: string;
    PUSHER_APP_CLUSTER: string;
};

/** The MAIL_* block of an .env — DevSettingController::blankMail() / ::getProductionMail(). */
type MailConfig = {
    MAIL_MAILER: string;
    MAIL_HOST: string;
    MAIL_PORT: string;
    MAIL_USERNAME: string;
    MAIL_PASSWORD: string;
    MAIL_ENCRYPTION: string;
    MAIL_FROM_ADDRESS: string;
};

/** One deploy target as it is edited in the form (the shape saved back to .deploy.json). */
type FlavorConfig = {
    domain: string;
    ssh: SshConfig;
    db: DbConfig;
    env: FlavorEnv;
    inherit_pusher: boolean;
    pusher: PusherEnv;
    inherit_mail: boolean;
    mail: MailConfig;
};

/** .deploy.json as DevSettingController::getDeployConfig() returns it (read-only extras included). */
type DeployConfig = {
    share_ssh: boolean;
    ssh: SshConfig;
    flavors: Record<DeployFlavor, FlavorConfig & { has_firebase: boolean }>;
    has_config: boolean;
};

/** One runnable seeder as DevSettingController::availableSeeders() discovers it. */
type AvailableSeeder = {
    class: string;
    label: string;
    description: string | null;
};

/** Working-tree + branch state from DevSettingController::getGitStatus(). */
type GitStatus = {
    is_repo: boolean;
    remote_url: string | null;
    current_branch: string | null;
    branches: string[];
    remote_branches: string[];
    modified: string[];
    staged: string[];
    untracked: string[];
    ahead: number;
    behind: number;
    commits: { hash: string; message: string; date: string }[];
    /** Whether the GitHub CLI can be used from here, and as whom. */
    gh?: { installed: boolean; authenticated: boolean; account: string | null };
};

const props = withDefaults(
    defineProps<{
        git?: GitStatus;
        deployConfig?: DeployConfig;
        /** Output of the last SSH deploy, flashed through the session. */
        deployLog?: string | null;
        baseMail?: MailConfig;
        pusherConfig?: { local: PusherCredentials; base: PusherCredentials };
        urls?: { local: UrlPair; base: UrlPair };
        availableSeeders?: AvailableSeeder[];
        /** Whether HOSTINGER_API_TOKEN is set — the Provision buttons hide without it. */
        hostingerConfigured?: boolean;
    }>(),
    {
        deployLog: null,
        availableSeeders: () => [],
        // Mirrors getGitStatus()'s own "no .git directory" return. The controller always
        // sends `git`, so this only spares the template an undefined check.
        git: () => ({
            is_repo: false,
            remote_url: null,
            gh: { installed: false, authenticated: false, account: null },
            current_branch: null,
            branches: [],
            remote_branches: [],
            modified: [],
            staged: [],
            untracked: [],
            ahead: 0,
            behind: 0,
            commits: [],
        }),
    },
);

/** One Pusher app's credentials as `index()` keys them (snake-case, not the raw env names). */
type PusherCredentials = {
    app_id: string;
    app_key: string;
    app_secret: string;
    app_cluster: string;
};

/** The APP_URL / FRONTEND_URL pair of one .env. */
type UrlPair = {
    APP_URL: string;
    FRONTEND_URL: string;
};

// GitHub
const gitForm = useForm({ url: props.git?.remote_url || '' });
const repoForm = useForm({
    name: '',
    visibility: 'private',
    description: '',
});

/**
 * Create the repository through the GitHub CLI instead of making the developer
 * open github.com, create it by hand and paste the URL back in.
 */
const submitRepo = () => {
    repoForm.post(route('dev_settings.github_repo'), {
        preserveScroll: true,
        onSuccess: () => repoForm.reset(),
    });
};

const submitGit = () => {
    gitForm.post(route('dev_settings.git'), {
        preserveScroll: true,
    });
};

const disconnectingGit = ref(false);
const disconnectGit = () => {
    disconnectingGit.value = true;
    router.post(
        route('dev_settings.git_disconnect'),
        { _method: 'DELETE' },
        {
            preserveScroll: true,
            onFinish: () => {
                disconnectingGit.value = false;
            },
        },
    );
};

// Git Pull
const pulling = ref(false);
const pullFromGithub = () => {
    pulling.value = true;
    router.post(
        route('dev_settings.pull'),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                pulling.value = false;
            },
        },
    );
};

// Git Fetch
const fetching = ref(false);
const fetchRemote = () => {
    fetching.value = true;
    router.post(
        route('dev_settings.fetch'),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                fetching.value = false;
            },
        },
    );
};

// Git Commit
const commitForm = useForm({ message: '' });
const submitCommit = () => {
    commitForm.post(route('dev_settings.commit'), {
        preserveScroll: true,
        onSuccess: () => {
            commitForm.reset('message');
        },
    });
};

// Git Commit & Push
const commitAndPush = () => {
    commitForm.post(route('dev_settings.commit'), {
        preserveScroll: true,
        onSuccess: () => {
            commitForm.reset('message');
            // After successful commit, push
            router.post(
                route('dev_settings.push'),
                {},
                {
                    preserveScroll: true,
                },
            );
        },
    });
};

// Switch Branch
const switching = ref(false);
const switchBranch = (branch: string) => {
    switching.value = true;
    router.post(
        route('dev_settings.branch_switch'),
        { branch },
        {
            preserveScroll: true,
            onFinish: () => {
                switching.value = false;
            },
        },
    );
};

// Create Branch
const createBranchForm = useForm({ name: '' });
const submitCreateBranch = () => {
    createBranchForm.post(route('dev_settings.branch_create'), {
        preserveScroll: true,
        onSuccess: () => {
            createBranchForm.reset('name');
        },
    });
};

// File Diff
const expandedFile = ref<string | null>(null);
const fileDiff = ref('');
const loadingDiff = ref(false);

const toggleFileDiff = async (file: string, type: 'modified' | 'staged' | 'untracked') => {
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
        const data = (await response.json()) as { diff?: string; error?: string };
        fileDiff.value = data.diff || '';
    } catch {
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

// Push to GitHub
const pushing = ref(false);
const pushToGithub = () => {
    pushing.value = true;
    router.post(
        route('dev_settings.push'),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                pushing.value = false;
            },
        },
    );
};

// Deployment Targets (dev / staging / uat / production)
const deployFlavors: DeployFlavor[] = ['dev', 'staging', 'uat', 'production'];

const blankSsh = (src?: Partial<SshConfig>): SshConfig => ({
    host: src?.host || '',
    port: src?.port || 65002,
    username: src?.username || '',
    password: src?.password || '',
});
const blankDb = (src?: Partial<DbConfig>): DbConfig => ({
    DB_HOST: src?.DB_HOST || '',
    DB_PORT: src?.DB_PORT || '3306',
    DB_DATABASE: src?.DB_DATABASE || '',
    DB_USERNAME: src?.DB_USERNAME || '',
    DB_PASSWORD: src?.DB_PASSWORD || '',
});
const blankFlavorEnv = (src?: Partial<FlavorEnv>): FlavorEnv => ({
    APP_DEBUG: src?.APP_DEBUG || 'inherit',
    IS_TESTING: src?.IS_TESTING || 'inherit',
    // No `inherit` for this one: the base is always true, so it is on unless explicitly off.
    ALLOW_CONTENT_SEEDING: src?.ALLOW_CONTENT_SEEDING === 'false' ? 'false' : 'true',
    FRONTEND_URL: src?.FRONTEND_URL || '',
});

// Base (.env.production) values shown as placeholders so it's obvious what each
// flavor inherits when a field is left blank.
const baseMailVal = (key: keyof MailConfig): string => props.baseMail?.[key] || '';
const basePusher = (key: keyof PusherCredentials): string => props.pusherConfig?.base?.[key] || '';
const blankPusher = (src?: Partial<PusherEnv>): PusherEnv => ({
    PUSHER_APP_ID: src?.PUSHER_APP_ID || '',
    PUSHER_APP_KEY: src?.PUSHER_APP_KEY || '',
    PUSHER_APP_SECRET: src?.PUSHER_APP_SECRET || '',
    PUSHER_APP_CLUSTER: src?.PUSHER_APP_CLUSTER || '',
});
const blankMail = (src?: Partial<MailConfig>): MailConfig => ({
    MAIL_MAILER: src?.MAIL_MAILER || '',
    MAIL_HOST: src?.MAIL_HOST || '',
    MAIL_PORT: src?.MAIL_PORT || '',
    MAIL_USERNAME: src?.MAIL_USERNAME || '',
    MAIL_PASSWORD: src?.MAIL_PASSWORD || '',
    MAIL_ENCRYPTION: src?.MAIL_ENCRYPTION || '',
    MAIL_FROM_ADDRESS: src?.MAIL_FROM_ADDRESS || '',
});

const buildFlavors = (): Record<DeployFlavor, FlavorConfig> => {
    const out = {} as Record<DeployFlavor, FlavorConfig>;
    for (const f of deployFlavors) {
        const stored: Partial<FlavorConfig> = props.deployConfig?.flavors?.[f] || {};
        out[f] = {
            domain: stored.domain || '',
            ssh: blankSsh(stored.ssh),
            db: blankDb(stored.db),
            env: blankFlavorEnv(stored.env),
            inherit_pusher: stored.inherit_pusher ?? true,
            pusher: blankPusher(stored.pusher),
            inherit_mail: stored.inherit_mail ?? true,
            mail: blankMail(stored.mail),
        };
    }
    return out;
};

// Which advanced subsection is expanded per flavor: '' | 'env' | 'pusher' | 'mail' | 'firebase'
const flavorPanel = ref('env');

/**
 * The production flavor cannot run with debug or testing mode on, so those two overrides
 * are shown locked rather than offered and then ignored.
 *
 * `IS_TESTING` is force-disabled at runtime by AppServiceProvider on a production
 * environment, and both are refused outright by `app:assert-production-safety`, which
 * fails the deploy. Leaving the selects live would mean choosing a value that either
 * silently does nothing or stops the deploy.
 */
const productionEnvLocked = computed(() => activeFlavorTab.value === 'production');

const hasFirebase = (flavor: DeployFlavor): boolean => Boolean(props.deployConfig?.flavors?.[flavor]?.has_firebase);

const uploadFlavorFirebase = (flavor: DeployFlavor, event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;
    router.post(
        route('dev_settings.flavor_firebase'),
        { flavor, firebase_json: file },
        {
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,
            reset: ['deployConfig', 'success', 'error'],
        },
    );
    input.value = '';
};

const deleteFlavorFirebase = (flavor: DeployFlavor) => {
    router.post(
        route('dev_settings.flavor_firebase_delete'),
        { flavor },
        {
            preserveScroll: true,
            preserveState: true,
            reset: ['deployConfig', 'success', 'error'],
        },
    );
};

const deployTargetsForm = useForm({
    share_ssh: props.deployConfig?.share_ssh ?? true,
    ssh: blankSsh(props.deployConfig?.ssh),
    flavors: buildFlavors(),
});

const activeFlavorTab = ref<DeployFlavor>('production');

// FRONTEND_URL per flavor: inherit base vs custom. Empty string = inherit.
const frontendUrlMode = ref(
    Object.fromEntries(deployFlavors.map((f) => [f, deployTargetsForm.flavors[f]?.env?.FRONTEND_URL ? 'custom' : 'inherit'])),
);
const setFrontendUrlMode = (flavor: DeployFlavor, value: unknown) => {
    const mode = value as string;
    frontendUrlMode.value[flavor] = mode;
    if (mode === 'inherit') {
        deployTargetsForm.flavors[flavor].env.FRONTEND_URL = '';
    }
};

const submitDeployTargets = () => {
    deployTargetsForm.put(route('dev_settings.deploy_config'), {
        preserveScroll: true,
        preserveState: true,
        reset: ['deployConfig', 'success', 'error'],
    });
};

// Deploy
const deploying = ref(false);
const showDeployModal = ref(false);
const showLogModal = ref(false);
const deployOptions = ref<{
    flavor: DeployFlavor;
    migration_option: 'migrate' | 'fresh_seed' | 'none';
    /** Seeder class names to run individually after the migration step. */
    seeders: string[];
    safe_storage_deploy: boolean;
    generate_docs: boolean;
}>({
    flavor: 'production',
    migration_option: 'migrate', // Default to safe option
    seeders: [],
    safe_storage_deploy: true, // Default on — preserve uploaded files.
    generate_docs: true, // Default on — turn off per-deploy (e.g. production) to keep /docs unreachable.
});

const selectAllSeeders = () => {
    deployOptions.value.seeders = props.availableSeeders.map((seeder) => seeder.class);
};

const clearSeeders = () => {
    deployOptions.value.seeders = [];
};

// The whole row toggles, so the label text is a hit target too — Checkbox is a div, not
// a real input, so a <label> wrapper would not do it.
const toggleSeeder = (seederClass: string) => {
    const selected = deployOptions.value.seeders;
    const index = selected.indexOf(seederClass);
    if (index === -1) {
        selected.push(seederClass);
    } else {
        selected.splice(index, 1);
    }
};

const flavorDomain = (flavor: DeployFlavor): string => deployTargetsForm.flavors[flavor]?.domain || '';

const openDeployModal = () => {
    // Pre-select the first deployable target so the modal opens on something usable.
    const firstReady = deployFlavors.find((f) => flavorReady(f));
    if (firstReady) deployOptions.value.flavor = firstReady;
    showDeployModal.value = true;
};

const closeDeployModal = () => {
    showDeployModal.value = false;
};

// SSH creds resolved for the selected flavor. Honor the share toggle, but fall
// back to whichever scope actually has a host so a half-filled toggle doesn't
// block deployment.
const flavorSsh = (flavor: DeployFlavor): SshConfig | undefined => {
    const shared = deployTargetsForm.ssh;
    const own = deployTargetsForm.flavors[flavor]?.ssh;
    if (deployTargetsForm.share_ssh) return shared?.host ? shared : own;
    return own?.host ? own : shared;
};

const flavorReady = (flavor: DeployFlavor): boolean => flavorMissing(flavor).length === 0;

// Human-readable list of what a flavor still needs before it can deploy.
const flavorMissing = (flavor: DeployFlavor): string[] => {
    const f = deployTargetsForm.flavors[flavor];
    const ssh = flavorSsh(flavor);
    const missing: string[] = [];
    if (!f?.domain) missing.push(t('domain'));
    if (!ssh?.host) missing.push(t('ssh_host'));
    if (!ssh?.username) missing.push(t('ssh_username'));
    return missing;
};

const runDeploy = () => {
    deploying.value = true;
    showDeployModal.value = false;
    router.post(route('dev_settings.deploy'), deployOptions.value, {
        preserveScroll: true,
        onFinish: () => {
            deploying.value = false;
        },
    });
};

// Hostinger API token. Never sent back to the browser — the server only reports whether
// one is set, so the field starts empty and an empty submit clears it.
const hostingerTokenForm = useForm({ token: '' });

const submitHostingerToken = () => {
    hostingerTokenForm.put(route('dev_settings.hostinger_token'), {
        preserveScroll: true,
        onSuccess: () => {
            hostingerTokenForm.reset();
            // A new token means a different account; drop the cached site list.
            hostingerSites.value = [];
            hostingerTest.value = '';
        },
    });
};

const hostingerTesting = ref(false);
const hostingerTest = ref('');
const hostingerTestOk = ref(false);

/** Calls the real API and reports what it can see, so a bad token fails here. */
const testHostinger = async () => {
    hostingerTesting.value = true;
    hostingerTest.value = '';

    const error = await loadHostingerAccount();
    const sites = hostingerSites.value;

    hostingerTestOk.value = !error && sites.length > 0;
    hostingerTest.value = hostingerTestOk.value
        ? t('hostinger_test_ok', { count: sites.length, account: sites[0].username })
        : error || t('hostinger_no_sites');

    hostingerTesting.value = false;
};

/** A website on the Hostinger account, from DevSettingController::hostingerAccount(). */
type HostingerWebsite = { domain: string; username: string; parent_domain: string | null; is_enabled: boolean };
/** A hosting plan a new website can be created under. */
type HostingerOrder = { id: number; plan: string; status: string };
type HostingerDatacenter = { code: string; title: string };
/** A domain registered in the account's portfolio. */
type HostingerDomain = { domain: string; status: string; expires_at: string | null };

const provisionOpen = ref(false);
const provisionFlavor = ref<DeployFlavor>('dev');
const hostingerSites = ref<HostingerWebsite[]>([]);
const hostingerOrders = ref<HostingerOrder[]>([]);
const hostingerDatacenters = ref<HostingerDatacenter[]>([]);
const hostingerDomains = ref<HostingerDomain[]>([]);

/** Sentinel for "not one of my registered domains" — reveals a free-text field. */
const OTHER_DOMAIN = '__other__';
/** Sentinel for "let Hostinger generate one" — a free `*.hostingersite.com` host. */
const FREE_DOMAIN = '__free__';
/** Base domain chosen for a new website, separate from the composed final domain. */
const newSiteBase = ref('');
const newSiteCustom = ref('');
const newSitePrefix = ref('');
const hostingerLoading = ref(false);
const hostingerError = ref('');

const provisionForm = useForm({
    flavor: 'dev' as DeployFlavor,
    mode: 'website' as 'existing' | 'website',
    username: '',
    domain: '',
    order_id: null as number | null,
    free_subdomain: false,
    datacenter_code: '',
    db_name: '',
    db_user: '',
    scheduler_cron: true,
});

/** The site picked in the modal, which carries the hosting username the API path needs. */
const pickedSite = computed(() => hostingerSites.value.find((site) => site.domain === provisionForm.domain));

/** What the flavour will end up on, so the modal states it before anything is created. */
const provisionResultDomain = computed(() => {
    if (provisionForm.mode === 'website' && usingFreeDomain.value) return t('free_domain_generated');
    return provisionForm.domain || '…';
});

/** Domains already serving a website: creating a second one for them would be rejected. */
const hostedDomains = computed(() => new Set(hostingerSites.value.map((site) => site.domain)));

/**
 * Everything a website can be created under: domains registered in the portfolio, plus the
 * domains of sites already hosted — subdomains included, so `api.crm.example.com` is
 * reachable by putting a prefix on `crm.example.com`.
 *
 * The two sources barely overlap: a domain bought elsewhere and pointed here is hosted but
 * not registered, and a subdomain only ever appears in the hosted list.
 */
const newSiteDomainOptions = computed(() => {
    const registered = new Set(hostingerDomains.value.map((d) => d.domain));
    const all = new Set([...registered, ...hostingerSites.value.map((site) => site.domain)]);

    return [...all].sort().map((domain) => ({
        domain,
        registered: registered.has(domain),
        hosted: hostedDomains.value.has(domain),
    }));
});

/**
 * One picker, not a mode radio plus two pickers.
 *
 * Attaching to a site that exists and creating a new one are the same decision — *which
 * host does this flavour live on* — so they are one grouped dropdown, and the mode is
 * read back off the choice instead of being asked for first. Existing sites are
 * prefixed because the two groups legitimately contain the same domain: `example.com`
 * can be attached to as a website, or used as the base for `dev.example.com`.
 */
const EXISTING_PREFIX = 'existing:';

const pickedExistingDomain = computed(() => (newSiteBase.value.startsWith(EXISTING_PREFIX) ? newSiteBase.value.slice(EXISTING_PREFIX.length) : ''));

/** Nothing chosen yet counts as creating, which is what the extra fields key off. */
const creatingSite = computed(() => !newSiteBase.value.startsWith(EXISTING_PREFIX));

/** The base the new website hangs off: a registered domain, or one typed in. */
const usingFreeDomain = computed(() => newSiteBase.value === FREE_DOMAIN);

const newSiteDomain = computed(() => {
    if (!creatingSite.value || usingFreeDomain.value) return '';
    return newSiteBase.value === OTHER_DOMAIN ? newSiteCustom.value.trim() : newSiteBase.value;
});

// The choice decides the mode; nothing else sets it.
watch(newSiteBase, (value: string) => {
    provisionForm.mode = value.startsWith(EXISTING_PREFIX) ? 'existing' : 'website';

    if (provisionForm.mode === 'existing') {
        provisionForm.domain = pickedExistingDomain.value;
        provisionForm.free_subdomain = false;
    }
});

/**
 * A domain already serving a website can still take a subdomain website, so it stays
 * selectable — but the bare domain itself is taken and Hostinger rejects the second one.
 */
const needsPrefix = computed(
    () => provisionForm.mode === 'website' && !usingFreeDomain.value && hostedDomains.value.has(newSiteDomain.value) && !newSitePrefix.value.trim(),
);

/**
 * `app` + `example.com` -> `app.example.com`; no prefix means the domain itself. Kept in a
 * watcher rather than bound directly so the server still receives one plain `domain`.
 */
watch([newSiteDomain, newSitePrefix, usingFreeDomain, () => provisionForm.mode], () => {
    if (provisionForm.mode !== 'website') return;

    provisionForm.free_subdomain = usingFreeDomain.value;

    if (usingFreeDomain.value) {
        provisionForm.domain = '';
        return;
    }

    // `dev` + `example.com` -> `dev.example.com`, which create-website takes as its own
    // independent site. Empty prefix hosts the domain itself.
    const prefix = newSitePrefix.value.trim().replace(/^\.+|\.+$/g, '');
    provisionForm.domain = prefix && newSiteDomain.value ? `${prefix}.${newSiteDomain.value}` : newSiteDomain.value;
});

/** In `website` mode the site does not exist yet, so nothing is picked from a list. */
const provisionReady = computed(() => {
    if (provisionForm.mode === 'existing') return Boolean(provisionForm.domain);

    // Creating goes through "add a website", which needs a plan to put it on.
    return Boolean(provisionForm.order_id) && !needsPrefix.value && (usingFreeDomain.value || Boolean(provisionForm.domain));
});

/**
 * Loads the pickers' data. `orderId` also pulls that plan's datacenters, which are only
 * needed for the first website on a new plan.
 */
const loadHostingerAccount = async (orderId?: number): Promise<string> => {
    hostingerLoading.value = true;
    try {
        const url = new URL(route('dev_settings.hostinger_account'), window.location.origin);
        if (orderId) url.searchParams.set('order_id', String(orderId));

        const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        const body = await response.json();

        hostingerSites.value = body.websites ?? [];
        hostingerOrders.value = body.orders ?? [];
        hostingerDatacenters.value = body.datacenters ?? [];
        hostingerDomains.value = body.domains ?? [];

        return body.error ?? '';
    } catch {
        return t('hostinger_sites_failed');
    } finally {
        hostingerLoading.value = false;
    }
};

const openProvision = async (flavor: DeployFlavor) => {
    provisionFlavor.value = flavor;
    hostingerError.value = '';
    provisionForm.clearErrors();
    provisionForm.flavor = flavor;
    provisionForm.mode = 'website';
    provisionForm.datacenter_code = '';
    provisionForm.free_subdomain = false;
    newSiteBase.value = '';
    newSiteCustom.value = '';
    newSitePrefix.value = flavor;
    // Hostinger prefixes the account username onto both, so these are just the suffix.
    provisionForm.db_name = flavor;
    provisionForm.db_user = flavor;
    provisionForm.scheduler_cron = true;
    provisionOpen.value = true;

    if (hostingerSites.value.length === 0 && hostingerOrders.value.length === 0) {
        hostingerError.value = await loadHostingerAccount();
    }

    // One site or one plan is the common case; pick it so there is nothing to choose.
    // Selected through the picker, so the mode watcher runs and `existing` is set too.
    if (newSiteBase.value === '' && hostingerSites.value.length === 1) {
        newSiteBase.value = EXISTING_PREFIX + hostingerSites.value[0].domain;
    }
    if (!provisionForm.order_id && hostingerOrders.value.length === 1) {
        provisionForm.order_id = hostingerOrders.value[0].id;
    }
};

/** Switching plan pulls its datacenters, needed only on a plan's first website. */
const onOrderPicked = async (value: unknown) => {
    provisionForm.order_id = value === null || value === '' ? null : Number(value);
    provisionForm.datacenter_code = '';
    if (provisionForm.order_id) {
        await loadHostingerAccount(provisionForm.order_id);
    }
};

const submitProvision = () => {
    // `website` mode has no site to read a username from — the server reads it back
    // after Hostinger creates one.
    provisionForm.username = provisionForm.mode === 'existing' ? (pickedSite.value?.username ?? '') : '';

    provisionForm.post(route('dev_settings.hostinger_provision'), {
        preserveScroll: true,
        onSuccess: () => {
            provisionOpen.value = false;
            // The server wrote .deploy.json; re-seed the form from the fresh prop.
            deployTargetsForm.flavors = buildFlavors();
            // What was just created is a website now (Hostinger models a subdomain as a
            // website with a parent), so the cached lists are a step behind — refetch,
            // or the next flavor cannot pick what this one made.
            loadHostingerAccount();
        },
    });
};
</script>

<template>
    <div>
        <div class="space-y-5">
            <!-- GitHub -->
            <SettingsCard anchor="github" :title="t('github')" :description="t('github_desc')">
                <template #icon><GitBranch class="size-5 text-primary" /></template>
                <template #actions>
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
                        <Button
                            size="sm"
                            variant="outline"
                            class="border-primary text-primary hover:bg-primary hover:text-primary-foreground"
                            :disabled="pushing"
                            @click="pushToGithub"
                        >
                            <Loader2 v-if="pushing" class="me-1 h-3 w-3 animate-spin" />
                            <ArrowUpCircle v-else class="me-1 size-3" />
                            {{ pushing ? t('pushing') : t('push_to_github') }}
                        </Button>
                    </div>
                </template>

                <!-- Status Row -->
                <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <CheckCircle v-if="git?.remote_url" class="size-4 text-emerald-500" aria-hidden="true" />
                        <XCircle v-else class="size-4 text-muted-foreground" aria-hidden="true" />
                        <span class="text-sm" :class="git?.remote_url ? 'text-emerald-600' : 'text-muted-foreground'">
                            {{ git?.remote_url ? t('github_connected') : t('github_not_connected') }}
                        </span>
                        <a
                            v-if="git?.remote_url"
                            :href="git.remote_url"
                            target="_blank"
                            class="ms-1 rounded font-mono text-xs text-primary hover:underline focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        >
                            {{ git.remote_url }}
                        </a>
                    </div>

                    <!-- Branch Selector -->
                    <div v-if="git?.is_repo && git?.current_branch" class="relative">
                        <Button
                            size="sm"
                            variant="outline"
                            type="button"
                            :aria-expanded="showBranchDropdown"
                            @click="showBranchDropdown = !showBranchDropdown"
                            class="min-w-[140px] justify-between"
                        >
                            <span class="flex items-center gap-2">
                                <GitBranch class="size-3" />
                                {{ git.current_branch }}
                            </span>
                            <ChevronDown class="ms-2 size-3" />
                        </Button>
                        <!-- Branch dropdown -->
                        <div v-if="showBranchDropdown" class="absolute end-0 top-full z-10 mt-1 w-56 rounded-lg border bg-card shadow-lg" @click.stop>
                            <div class="max-h-60 overflow-y-auto p-1">
                                <div class="px-2 py-1 text-xs font-medium text-muted-foreground">{{ t('branches') }}</div>
                                <button
                                    v-for="branch in git.branches"
                                    :key="branch"
                                    type="button"
                                    @click="
                                        switchBranch(branch);
                                        showBranchDropdown = false;
                                    "
                                    :disabled="switching || branch === git.current_branch"
                                    class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-start text-sm hover:bg-muted focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none disabled:opacity-50"
                                    :class="{ 'bg-muted': branch === git.current_branch }"
                                >
                                    <CheckCircle v-if="branch === git.current_branch" class="size-3 text-emerald-500" />
                                    <span v-else class="size-3"></span>
                                    {{ branch }}
                                </button>
                                <div v-if="git.remote_branches?.length" class="mt-1 border-t border-border pt-1">
                                    <div class="px-2 py-1 text-xs font-medium text-muted-foreground">Remote</div>
                                    <button
                                        v-for="branch in git.remote_branches.filter((b) => !git.branches.includes(b))"
                                        :key="'remote-' + branch"
                                        type="button"
                                        @click="
                                            switchBranch(branch);
                                            showBranchDropdown = false;
                                        "
                                        :disabled="switching"
                                        class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-start text-sm text-muted-foreground hover:bg-muted focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                    >
                                        <span class="size-3"></span>
                                        origin/{{ branch }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ahead/Behind indicator -->
                <div v-if="git?.is_repo && git?.remote_url && (git.ahead > 0 || git.behind > 0)" class="mb-4 flex items-center gap-4 text-sm">
                    <span v-if="git.ahead > 0" class="flex items-center gap-1 text-emerald-600">
                        <ArrowUpCircle class="size-4" aria-hidden="true" />
                        {{ git.ahead }} {{ t('ahead') }}
                    </span>
                    <span v-if="git.behind > 0" class="flex items-center gap-1 text-amber-600">
                        <ArrowDownCircle class="size-4" aria-hidden="true" />
                        {{ git.behind }} {{ t('behind') }}
                    </span>
                </div>

                <!-- Create the repo through gh, so github.com never has to be opened -->
                <div v-if="!git?.is_repo || !git?.remote_url" class="mb-4 rounded-xl border border-border p-4">
                    <div class="mb-1 flex flex-wrap items-center gap-2">
                        <Github class="size-4 text-muted-foreground" aria-hidden="true" />
                        <h4 class="text-sm font-semibold text-foreground">{{ t('create_github_repo') }}</h4>
                        <span v-if="git?.gh?.authenticated && git?.gh?.account" class="flex items-center gap-1 text-sm text-emerald-600">
                            <CheckCircle class="size-4" aria-hidden="true" />
                            {{ t('signed_in_as') }} {{ git.gh.account }}
                        </span>
                    </div>
                    <p class="mb-3 text-xs text-muted-foreground">{{ t('create_github_repo_hint') }}</p>

                    <div
                        v-if="!git?.gh?.installed || !git?.gh?.authenticated"
                        role="alert"
                        class="flex items-start gap-2 rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 text-sm text-amber-600"
                    >
                        <AlertTriangle class="mt-0.5 size-5 shrink-0" aria-hidden="true" />
                        <p>{{ git?.gh?.installed ? t('gh_not_authenticated') : t('gh_not_installed') }}</p>
                    </div>

                    <form v-else class="space-y-3" @submit.prevent="submitRepo">
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_10rem]">
                            <div>
                                <label for="gh-repo-name" class="mb-1 block text-xs font-medium text-foreground">
                                    {{ t('repository_name') }}
                                </label>
                                <Input id="gh-repo-name" v-model="repoForm.name" type="text" placeholder="my-project" />
                                <p v-if="repoForm.errors.name" class="mt-1 text-xs text-destructive">{{ repoForm.errors.name }}</p>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-foreground">{{ t('visibility') }}</label>
                                <Select v-model="repoForm.visibility">
                                    <SelectTrigger class="w-full"><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="private">{{ t('private') }}</SelectItem>
                                        <SelectItem value="public">{{ t('public') }}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <Button type="submit" :disabled="repoForm.processing || !repoForm.name">
                            <Loader2 v-if="repoForm.processing" class="me-2 h-4 w-4 animate-spin" />
                            {{ repoForm.processing ? t('initializing') : t('create_and_push') }}
                        </Button>
                    </form>
                </div>

                <!-- Init Form (when no repo) -->
                <form v-if="!git?.is_repo" @submit.prevent="submitGit" class="flex items-center gap-3">
                    <Input v-model="gitForm.url" type="url" :placeholder="t('github_url_placeholder')" class="flex-1" />
                    <Button type="submit" :disabled="gitForm.processing || !gitForm.url">
                        <Loader2 v-if="gitForm.processing" class="me-2 h-4 w-4 animate-spin" />
                        {{ gitForm.processing ? t('initializing') : t('initialize_push') }}
                    </Button>
                </form>
                <div v-if="gitForm.errors.url" class="mt-2 text-sm text-destructive">{{ gitForm.errors.url }}</div>

                <!-- Changes Panel (when repo exists) -->
                <div v-if="git?.is_repo" class="space-y-4">
                    <!-- Changes Section -->
                    <div class="rounded-xl border border-border">
                        <button
                            type="button"
                            :aria-expanded="showChanges"
                            @click="showChanges = !showChanges"
                            class="flex w-full items-center justify-between rounded-xl p-3 text-start hover:bg-muted/50 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        >
                            <span class="flex items-center gap-2 text-sm font-medium">
                                <ChevronRight :class="{ 'rotate-90': showChanges }" class="size-4 transition-transform" />
                                {{ t('changes') }}
                                <span
                                    v-if="(git.modified?.length || 0) + (git.staged?.length || 0) + (git.untracked?.length || 0) > 0"
                                    class="rounded-full bg-primary px-2 py-0.5 text-xs text-primary-foreground"
                                >
                                    {{ (git.modified?.length || 0) + (git.staged?.length || 0) + (git.untracked?.length || 0) }}
                                </span>
                            </span>
                        </button>
                        <div v-if="showChanges" class="border-t border-border p-3">
                            <div
                                v-if="(git.modified?.length || 0) + (git.staged?.length || 0) + (git.untracked?.length || 0) === 0"
                                class="text-sm text-muted-foreground"
                            >
                                {{ t('no_changes') }}
                            </div>
                            <div v-else class="space-y-3">
                                <!-- Staged files -->
                                <div v-if="git.staged?.length">
                                    <div class="mb-1 text-xs font-medium text-emerald-600">{{ t('staged_files') }}</div>
                                    <div v-for="file in git.staged" :key="'staged-' + file" class="group">
                                        <button
                                            type="button"
                                            :aria-expanded="expandedFile === `staged:${file}`"
                                            @click="toggleFileDiff(file, 'staged')"
                                            class="flex w-full items-center gap-2 rounded px-2 py-1 text-start text-sm hover:bg-muted focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                        >
                                            <FileEdit class="size-3 text-emerald-500" />
                                            <span class="flex-1 font-mono text-xs">{{ file }}</span>
                                            <ChevronRight
                                                :class="{ 'rotate-90': expandedFile === `staged:${file}` }"
                                                class="size-3 text-muted-foreground transition-transform"
                                            />
                                        </button>
                                        <div v-if="expandedFile === `staged:${file}`" class="ms-5 mt-1 rounded bg-muted p-2">
                                            <Loader2 v-if="loadingDiff" class="h-4 w-4 animate-spin" />
                                            <pre v-else class="max-h-40 overflow-auto font-mono text-xs whitespace-pre-wrap">{{ fileDiff }}</pre>
                                        </div>
                                    </div>
                                </div>
                                <!-- Modified files -->
                                <div v-if="git.modified?.length">
                                    <div class="mb-1 text-xs font-medium text-amber-600">{{ t('modified_files') }}</div>
                                    <div v-for="file in git.modified" :key="'modified-' + file" class="group">
                                        <button
                                            type="button"
                                            :aria-expanded="expandedFile === `modified:${file}`"
                                            @click="toggleFileDiff(file, 'modified')"
                                            class="flex w-full items-center gap-2 rounded px-2 py-1 text-start text-sm hover:bg-muted focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                        >
                                            <FileCode class="size-3 text-amber-500" />
                                            <span class="flex-1 font-mono text-xs">{{ file }}</span>
                                            <ChevronRight
                                                :class="{ 'rotate-90': expandedFile === `modified:${file}` }"
                                                class="size-3 text-muted-foreground transition-transform"
                                            />
                                        </button>
                                        <div v-if="expandedFile === `modified:${file}`" class="ms-5 mt-1 rounded bg-muted p-2">
                                            <Loader2 v-if="loadingDiff" class="h-4 w-4 animate-spin" />
                                            <pre v-else class="max-h-40 overflow-auto font-mono text-xs whitespace-pre-wrap">{{ fileDiff }}</pre>
                                        </div>
                                    </div>
                                </div>
                                <!-- Untracked files -->
                                <div v-if="git.untracked?.length">
                                    <div class="mb-1 text-xs font-medium text-blue-600">{{ t('untracked_files') }}</div>
                                    <div v-for="file in git.untracked" :key="'untracked-' + file" class="group">
                                        <button
                                            type="button"
                                            :aria-expanded="expandedFile === `untracked:${file}`"
                                            @click="toggleFileDiff(file, 'untracked')"
                                            class="flex w-full items-center gap-2 rounded px-2 py-1 text-start text-sm hover:bg-muted focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                        >
                                            <FilePlus class="size-3 text-blue-500" />
                                            <span class="flex-1 font-mono text-xs">{{ file }}</span>
                                            <ChevronRight
                                                :class="{ 'rotate-90': expandedFile === `untracked:${file}` }"
                                                class="size-3 text-muted-foreground transition-transform"
                                            />
                                        </button>
                                        <div v-if="expandedFile === `untracked:${file}`" class="ms-5 mt-1 rounded bg-muted p-2">
                                            <Loader2 v-if="loadingDiff" class="h-4 w-4 animate-spin" />
                                            <pre v-else class="max-h-40 overflow-auto font-mono text-xs whitespace-pre-wrap">{{ fileDiff }}</pre>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Commit Form -->
                    <div
                        v-if="(git.modified?.length || 0) + (git.staged?.length || 0) + (git.untracked?.length || 0) > 0"
                        class="flex flex-col gap-2 sm:flex-row"
                    >
                        <Input v-model="commitForm.message" :placeholder="t('commit_message_placeholder')" class="flex-1" />
                        <div class="flex gap-2">
                            <Button :disabled="commitForm.processing || !commitForm.message" @click="submitCommit">
                                <Loader2 v-if="commitForm.processing" class="me-2 h-4 w-4 animate-spin" />
                                <GitCommit v-else class="me-2 size-4" />
                                {{ commitForm.processing ? t('committing') : t('commit_changes') }}
                            </Button>
                            <Button
                                variant="outline"
                                class="border-primary text-primary hover:bg-primary hover:text-primary-foreground"
                                :disabled="commitForm.processing || !commitForm.message"
                                @click="commitAndPush"
                            >
                                {{ t('commit_and_push') }}
                            </Button>
                        </div>
                    </div>
                    <div v-if="commitForm.errors.message" class="text-sm text-destructive">{{ commitForm.errors.message }}</div>

                    <!-- Create Branch -->
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <Input v-model="createBranchForm.name" :placeholder="t('new_branch_placeholder')" class="flex-1 sm:max-w-xs" />
                        <Button variant="outline" :disabled="createBranchForm.processing || !createBranchForm.name" @click="submitCreateBranch">
                            <Loader2 v-if="createBranchForm.processing" class="me-2 h-4 w-4 animate-spin" />
                            <Plus v-else class="me-2 size-4" />
                            {{ createBranchForm.processing ? t('creating') : t('create_branch') }}
                        </Button>
                    </div>
                    <div v-if="createBranchForm.errors.name" class="text-sm text-destructive">{{ createBranchForm.errors.name }}</div>

                    <!-- Recent Commits -->
                    <div class="rounded-xl border border-border">
                        <button
                            type="button"
                            :aria-expanded="showCommits"
                            @click="showCommits = !showCommits"
                            class="flex w-full items-center justify-between rounded-xl p-3 text-start hover:bg-muted/50 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        >
                            <span class="flex items-center gap-2 text-sm font-medium">
                                <ChevronRight :class="{ 'rotate-90': showCommits }" class="size-4 transition-transform" />
                                {{ t('recent_commits') }}
                            </span>
                        </button>
                        <div v-if="showCommits" class="border-t border-border p-3">
                            <div v-if="!git.commits?.length" class="text-sm text-muted-foreground">
                                {{ t('no_commits') }}
                            </div>
                            <div v-else class="space-y-1">
                                <div
                                    v-for="commit in git.commits"
                                    :key="commit.hash"
                                    class="flex items-center gap-2 rounded px-2 py-1 text-sm hover:bg-muted"
                                >
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
                        <Button
                            variant="outline"
                            type="button"
                            class="border-destructive text-destructive hover:bg-destructive hover:text-destructive-foreground"
                            :disabled="disconnectingGit"
                            @click="disconnectGit"
                        >
                            <Loader2 v-if="disconnectingGit" class="me-2 h-4 w-4 animate-spin" />
                            <XCircle v-else class="me-2 size-4" />
                            {{ disconnectingGit ? t('removing') : t('remove_git') }}
                        </Button>
                    </div>
                </div>
            </SettingsCard>

            <!-- Deployment Targets -->
            <!-- Hostinger API: the token that turns on per-flavor provisioning. -->
            <SettingsCard anchor="hostinger" :title="t('hostinger_api')" :description="t('hostinger_api_desc')">
                <template #icon><Server class="size-5 text-sky-500" /></template>

                <div class="space-y-4">
                    <div class="flex items-center gap-2">
                        <CheckCircle v-if="hostingerConfigured" class="size-4 text-emerald-500" />
                        <XCircle v-else class="size-4 text-muted-foreground" />
                        <span class="text-sm" :class="hostingerConfigured ? 'text-emerald-600' : 'text-muted-foreground'">
                            {{ hostingerConfigured ? t('hostinger_token_set') : t('hostinger_token_missing') }}
                        </span>
                    </div>

                    <form class="space-y-3" @submit.prevent="submitHostingerToken">
                        <div class="space-y-2">
                            <label for="hostinger_token" class="block text-sm font-medium text-foreground">{{ t('api_token') }}</label>
                            <Input
                                id="hostinger_token"
                                v-model="hostingerTokenForm.token"
                                type="password"
                                autocomplete="off"
                                :placeholder="hostingerConfigured ? '••••••••••••' : 'hPanel → API'"
                            />
                            <p v-if="hostingerTokenForm.errors.token" class="text-xs text-destructive">{{ hostingerTokenForm.errors.token }}</p>
                            <p class="text-xs text-muted-foreground">{{ t('hostinger_token_hint') }}</p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <Button type="submit" :disabled="hostingerTokenForm.processing">
                                <Loader2 v-if="hostingerTokenForm.processing" class="me-2 size-4 animate-spin" />
                                {{ hostingerTokenForm.processing ? t('saving') : t('save') }}
                            </Button>
                            <Button v-if="hostingerConfigured" type="button" variant="outline" :disabled="hostingerTesting" @click="testHostinger">
                                <Loader2 v-if="hostingerTesting" class="me-2 size-4 animate-spin" />
                                {{ t('test_connection') }}
                            </Button>
                        </div>

                        <p v-if="hostingerTest" class="text-xs" :class="hostingerTestOk ? 'text-emerald-600' : 'text-destructive'">
                            {{ hostingerTest }}
                        </p>
                    </form>
                </div>
            </SettingsCard>

            <SettingsCard anchor="deploy-targets" :title="t('deployment_targets')" :description="t('deployment_targets_desc')">
                <template #icon><Server class="size-5 text-primary" /></template>
                <template #actions>
                    <div class="flex items-center gap-2">
                        <Button
                            :disabled="deploying"
                            variant="outline"
                            type="button"
                            class="border-emerald-500 text-emerald-500 hover:bg-emerald-500 hover:text-white"
                            @click="openDeployModal"
                        >
                            <Loader2 v-if="deploying" class="me-2 h-4 w-4 animate-spin" />
                            <Rocket v-else class="me-2 size-4" />
                            {{ deploying ? t('deploying') : t('deploy') }}
                        </Button>
                        <Button
                            v-if="deployLog"
                            variant="outline"
                            type="button"
                            class="border-sky-500 text-sky-500 hover:bg-sky-500 hover:text-white"
                            @click="showLogModal = true"
                        >
                            <FileCode class="me-2 size-4" />
                            {{ t('view_deploy_log') }}
                        </Button>
                    </div>
                </template>

                <form @submit.prevent="submitDeployTargets" class="space-y-6">
                    <!-- Shared SSH toggle -->
                    <div class="flex items-start gap-3 rounded-xl border border-border p-4">
                        <Checkbox v-model="deployTargetsForm.share_ssh" :aria-label="t('share_ssh_credentials')" class="mt-1 shrink-0" />
                        <div class="min-w-0 flex-1">
                            <span class="font-medium text-foreground">{{ t('share_ssh_credentials') }}</span>
                            <p class="text-sm text-muted-foreground">{{ t('share_ssh_credentials_desc') }}</p>
                        </div>
                    </div>

                    <!-- Shared SSH fields -->
                    <div v-if="deployTargetsForm.share_ssh" class="rounded-xl border border-border p-4">
                        <p class="mb-3 text-sm font-semibold text-foreground">{{ t('shared_ssh_credentials') }}</p>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">{{ t('ssh_host') }}</label>
                                <Input v-model="deployTargetsForm.ssh.host" type="text" placeholder="us-bos-web1568.main-hosting.eu" />
                            </div>
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">{{ t('ssh_port') }}</label>
                                <Input v-model="deployTargetsForm.ssh.port" type="number" placeholder="65002" />
                            </div>
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">{{ t('ssh_username') }}</label>
                                <Input v-model="deployTargetsForm.ssh.username" type="text" placeholder="u983470049" />
                            </div>
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">{{ t('ssh_password') }}</label>
                                <Input v-model="deployTargetsForm.ssh.password" type="password" placeholder="••••••••" />
                            </div>
                        </div>
                    </div>

                    <!-- Flavor tabs -->
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="flavor in deployFlavors"
                            :key="flavor"
                            type="button"
                            @click="activeFlavorTab = flavor"
                            class="flex items-center gap-2 rounded-lg border px-3 py-1.5 text-sm font-medium capitalize transition-colors"
                            :class="
                                activeFlavorTab === flavor
                                    ? 'border-primary bg-primary/5 text-foreground'
                                    : 'border-border text-muted-foreground hover:bg-muted'
                            "
                        >
                            <span
                                class="size-2 rounded-full"
                                aria-hidden="true"
                                :class="flavorReady(flavor) ? 'bg-emerald-500' : 'bg-muted-foreground/40'"
                            ></span>
                            {{ t('flavor_' + flavor) }}
                            <span v-if="flavorDomain(flavor)" class="text-xs font-normal text-muted-foreground lowercase"
                                >· {{ flavorDomain(flavor) }}</span
                            >
                        </button>
                    </div>

                    <!-- Per-flavor config — single block bound to the active tab -->
                    <div :key="activeFlavorTab" class="space-y-4 rounded-xl border border-border p-4">
                        <!-- Provision this flavor on Hostinger. Hidden without a token,
                             and offered per flavor because most projects use one or two. -->
                        <div
                            v-if="hostingerConfigured"
                            class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-sky-500/30 bg-sky-500/5 p-3"
                        >
                            <div class="text-xs text-muted-foreground">
                                <p class="font-medium text-foreground">{{ t('provision_on_hostinger') }}</p>
                                <p>{{ t('provision_on_hostinger_desc') }}</p>
                            </div>
                            <Button type="button" variant="outline" @click="openProvision(activeFlavorTab)">
                                <Wand2 class="me-2 size-4" />
                                {{ t('provision') }}
                            </Button>
                        </div>

                        <!-- Domain -->
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-foreground">{{ t('domain') }} — {{ t('flavor_' + activeFlavorTab) }}</label>
                            <div
                                class="flex items-stretch overflow-hidden rounded-md border border-input bg-transparent transition-[color,box-shadow] focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50"
                            >
                                <span class="flex items-center border-e border-input bg-muted px-3 text-sm text-muted-foreground select-none"
                                    >https://</span
                                >
                                <Input
                                    v-model="deployTargetsForm.flavors[activeFlavorTab].domain"
                                    type="text"
                                    placeholder="example.hostingersite.com"
                                    class="h-9 flex-1 rounded-none border-0 shadow-none focus-visible:ring-0"
                                />
                            </div>
                            <p class="text-xs text-muted-foreground">{{ t('domain_https_hint') }}</p>
                        </div>

                        <!-- Per-flavor SSH (only when not shared) -->
                        <div v-if="!deployTargetsForm.share_ssh" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">{{ t('ssh_host') }}</label>
                                <Input
                                    v-model="deployTargetsForm.flavors[activeFlavorTab].ssh.host"
                                    type="text"
                                    placeholder="us-bos-web1568.main-hosting.eu"
                                />
                            </div>
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">{{ t('ssh_port') }}</label>
                                <Input v-model="deployTargetsForm.flavors[activeFlavorTab].ssh.port" type="number" placeholder="65002" />
                            </div>
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">{{ t('ssh_username') }}</label>
                                <Input v-model="deployTargetsForm.flavors[activeFlavorTab].ssh.username" type="text" placeholder="u983470049" />
                            </div>
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">{{ t('ssh_password') }}</label>
                                <Input v-model="deployTargetsForm.flavors[activeFlavorTab].ssh.password" type="password" placeholder="••••••••" />
                            </div>
                        </div>

                        <!-- Per-flavor database -->
                        <div>
                            <p class="mb-3 text-sm font-semibold text-foreground">{{ t('database') }}</p>
                            <p class="mb-3 text-xs text-muted-foreground">{{ t('flavor_db_hint') }}</p>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-foreground">{{ t('db_host') }}</label>
                                    <Input v-model="deployTargetsForm.flavors[activeFlavorTab].db.DB_HOST" type="text" placeholder="127.0.0.1" />
                                </div>
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-foreground">{{ t('db_port') }}</label>
                                    <Input v-model="deployTargetsForm.flavors[activeFlavorTab].db.DB_PORT" type="text" placeholder="3306" />
                                </div>
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-foreground">{{ t('db_database') }}</label>
                                    <Input
                                        v-model="deployTargetsForm.flavors[activeFlavorTab].db.DB_DATABASE"
                                        type="text"
                                        :placeholder="activeFlavorTab + '_db'"
                                    />
                                </div>
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-foreground">{{ t('db_username') }}</label>
                                    <Input v-model="deployTargetsForm.flavors[activeFlavorTab].db.DB_USERNAME" type="text" placeholder="root" />
                                </div>
                                <div class="space-y-2 sm:col-span-2">
                                    <label class="block text-sm font-medium text-foreground">{{ t('db_password') }}</label>
                                    <Input
                                        v-model="deployTargetsForm.flavors[activeFlavorTab].db.DB_PASSWORD"
                                        type="password"
                                        placeholder="••••••••"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Advanced per-flavor overrides -->
                        <div class="border-t border-border pt-4">
                            <!-- Inheritance explainer -->
                            <div class="mb-4 flex items-start gap-2 rounded-xl border border-sky-500/30 bg-sky-500/5 p-3">
                                <Link2 class="mt-0.5 size-4 shrink-0 text-sky-600" />
                                <div class="text-xs text-muted-foreground">
                                    <p class="font-medium text-foreground">{{ t('flavor_inherit_title') }}</p>
                                    <p class="mt-0.5">{{ t('flavor_inherit_explainer') }}</p>
                                    <p class="mt-1">
                                        <span class="font-medium text-foreground">APP_ENV</span>
                                        {{ t('flavor_app_env_auto') }}
                                        <code class="rounded bg-muted px-1 py-0.5 text-foreground">{{ activeFlavorTab }}</code>
                                    </p>
                                </div>
                            </div>

                            <div class="mb-4 flex flex-wrap gap-2">
                                <button
                                    v-for="panel in ['env', 'pusher', 'mail', 'firebase']"
                                    :key="panel"
                                    type="button"
                                    @click="flavorPanel = panel"
                                    class="rounded-lg border px-3 py-1 text-xs font-medium transition-colors"
                                    :class="
                                        flavorPanel === panel
                                            ? 'border-primary bg-primary/5 text-foreground'
                                            : 'border-border text-muted-foreground hover:bg-muted'
                                    "
                                >
                                    {{ t('flavor_panel_' + panel) }}
                                </button>
                            </div>

                            <!-- Environment -->
                            <div v-show="flavorPanel === 'env'" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div class="space-y-2 sm:col-span-2">
                                    <label class="block text-sm font-medium text-foreground">FRONTEND_URL</label>
                                    <Select
                                        :model-value="frontendUrlMode[activeFlavorTab]"
                                        @update:model-value="(v) => setFrontendUrlMode(activeFlavorTab, v)"
                                    >
                                        <SelectTrigger><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="inherit"
                                                >{{ t('inherit_base') }} ({{ props.urls?.base?.FRONTEND_URL || '—' }})</SelectItem
                                            >
                                            <SelectItem value="custom">{{ t('custom') }}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <Input
                                        v-if="frontendUrlMode[activeFlavorTab] === 'custom'"
                                        v-model="deployTargetsForm.flavors[activeFlavorTab].env.FRONTEND_URL"
                                        type="text"
                                        placeholder="https://app.example.com"
                                    />
                                </div>
                                <!-- Only production gets a choice: everywhere else this is
                                     always on, since there is no live content to protect. -->
                                <div v-if="activeFlavorTab === 'production'" class="space-y-2 sm:col-span-2">
                                    <label class="block text-sm font-medium text-foreground">ALLOW_CONTENT_SEEDING</label>
                                    <Select v-model="deployTargetsForm.flavors[activeFlavorTab].env.ALLOW_CONTENT_SEEDING">
                                        <SelectTrigger><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="true">{{ t('enabled') }}</SelectItem>
                                            <SelectItem value="false">{{ t('disabled') }}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <!-- One line, not two: whichever state you are in, only one
                                         of these is advice you can act on. -->
                                    <p
                                        v-if="deployTargetsForm.flavors[activeFlavorTab].env.ALLOW_CONTENT_SEEDING !== 'false'"
                                        class="text-xs font-medium text-destructive"
                                    >
                                        {{ t('content_seeding_warning') }}
                                    </p>
                                    <p v-else class="text-xs text-muted-foreground">{{ t('content_seeding_closed') }}</p>
                                </div>

                                <div v-else class="space-y-1 sm:col-span-2">
                                    <p class="text-sm font-medium text-foreground">ALLOW_CONTENT_SEEDING</p>
                                    <p class="text-xs text-muted-foreground">{{ t('content_seeding_always_on') }}</p>
                                </div>

                                <!-- Production cannot run with either of these on; see productionEnvLocked. -->
                                <!-- One line by default. The three paragraphs of reasoning are
                                     still here, behind a disclosure: this is settled, not a
                                     decision, so it should not occupy the panel. -->
                                <div v-if="productionEnvLocked" class="sm:col-span-2">
                                    <details class="group rounded-xl border border-amber-500/30 bg-amber-500/5 p-3">
                                        <summary
                                            class="flex cursor-pointer list-none items-center gap-2 text-xs text-muted-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                        >
                                            <Lock class="size-3.5 shrink-0 text-amber-600" />
                                            <span class="text-foreground">{{ t('production_env_locked') }}</span>
                                            <ChevronDown class="size-3.5 shrink-0 transition-transform group-open:rotate-180" />
                                        </summary>
                                        <div class="mt-2 space-y-1.5 ps-5 text-xs text-muted-foreground">
                                            <p><span class="font-mono text-foreground">APP_DEBUG</span> — {{ t('production_locked_debug') }}</p>
                                            <p><span class="font-mono text-foreground">IS_TESTING</span> — {{ t('production_locked_testing') }}</p>
                                            <p>{{ t('production_locked_hint') }}</p>
                                        </div>
                                    </details>
                                </div>

                                <template v-else>
                                    <div class="space-y-2">
                                        <label class="block text-sm font-medium text-foreground">APP_DEBUG</label>
                                        <Select v-model="deployTargetsForm.flavors[activeFlavorTab].env.APP_DEBUG">
                                            <SelectTrigger><SelectValue /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="inherit">{{ t('inherit_base') }}</SelectItem>
                                                <SelectItem value="true">{{ t('enabled') }}</SelectItem>
                                                <SelectItem value="false">{{ t('disabled') }}</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div class="space-y-2">
                                        <label class="block text-sm font-medium text-foreground">IS_TESTING</label>
                                        <Select v-model="deployTargetsForm.flavors[activeFlavorTab].env.IS_TESTING">
                                            <SelectTrigger><SelectValue /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="inherit">{{ t('inherit_base') }}</SelectItem>
                                                <SelectItem value="true">{{ t('enabled') }}</SelectItem>
                                                <SelectItem value="false">{{ t('disabled') }}</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </template>
                            </div>

                            <!-- Pusher -->
                            <div v-show="flavorPanel === 'pusher'" class="space-y-4">
                                <label class="flex items-start gap-3 rounded-xl border border-border p-3">
                                    <Checkbox
                                        v-model="deployTargetsForm.flavors[activeFlavorTab].inherit_pusher"
                                        :aria-label="t('inherit_pusher_from_base')"
                                        class="mt-0.5 shrink-0"
                                    />
                                    <span class="min-w-0 flex-1">
                                        <span class="text-sm font-medium text-foreground">{{ t('inherit_pusher_from_base') }}</span>
                                        <span class="block text-xs text-muted-foreground">{{ t('inherit_group_hint') }}</span>
                                    </span>
                                </label>
                                <p
                                    v-if="deployTargetsForm.flavors[activeFlavorTab].inherit_pusher"
                                    class="flex items-center gap-2 text-xs text-sky-600"
                                >
                                    <Link2 class="size-3.5" /> {{ t('inheriting_base_now') }}
                                </p>
                                <template v-else>
                                    <p class="text-xs text-amber-600">{{ t('flavor_pusher_note') }}</p>
                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <div class="space-y-2">
                                            <label class="block text-sm font-medium text-foreground">{{ t('pusher_app_id') }}</label>
                                            <Input
                                                v-model="deployTargetsForm.flavors[activeFlavorTab].pusher.PUSHER_APP_ID"
                                                type="text"
                                                :placeholder="t('base_value') + ': ' + (basePusher('app_id') || '—')"
                                            />
                                        </div>
                                        <div class="space-y-2">
                                            <label class="block text-sm font-medium text-foreground">{{ t('pusher_app_key') }}</label>
                                            <Input
                                                v-model="deployTargetsForm.flavors[activeFlavorTab].pusher.PUSHER_APP_KEY"
                                                type="text"
                                                :placeholder="t('base_value') + ': ' + (basePusher('app_key') || '—')"
                                            />
                                        </div>
                                        <div class="space-y-2">
                                            <label class="block text-sm font-medium text-foreground">{{ t('pusher_app_secret') }}</label>
                                            <Input
                                                v-model="deployTargetsForm.flavors[activeFlavorTab].pusher.PUSHER_APP_SECRET"
                                                type="password"
                                                :placeholder="basePusher('app_secret') ? t('base_value') + ': ••••••' : t('base_value') + ': —'"
                                            />
                                        </div>
                                        <div class="space-y-2">
                                            <label class="block text-sm font-medium text-foreground">{{ t('pusher_app_cluster') }}</label>
                                            <Input
                                                v-model="deployTargetsForm.flavors[activeFlavorTab].pusher.PUSHER_APP_CLUSTER"
                                                type="text"
                                                :placeholder="t('base_value') + ': ' + (basePusher('app_cluster') || '—')"
                                            />
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <!-- Mail -->
                            <div v-show="flavorPanel === 'mail'" class="space-y-4">
                                <label class="flex items-start gap-3 rounded-xl border border-border p-3">
                                    <Checkbox
                                        v-model="deployTargetsForm.flavors[activeFlavorTab].inherit_mail"
                                        :aria-label="t('inherit_mail_from_base')"
                                        class="mt-0.5 shrink-0"
                                    />
                                    <span class="min-w-0 flex-1">
                                        <span class="text-sm font-medium text-foreground">{{ t('inherit_mail_from_base') }}</span>
                                        <span class="block text-xs text-muted-foreground">{{ t('inherit_group_hint') }}</span>
                                    </span>
                                </label>
                                <p
                                    v-if="deployTargetsForm.flavors[activeFlavorTab].inherit_mail"
                                    class="flex items-center gap-2 text-xs text-sky-600"
                                >
                                    <Link2 class="size-3.5" /> {{ t('inheriting_base_now') }}
                                </p>
                                <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div class="space-y-2">
                                        <label class="block text-sm font-medium text-foreground">{{ t('mail_mailer') }}</label>
                                        <Input
                                            v-model="deployTargetsForm.flavors[activeFlavorTab].mail.MAIL_MAILER"
                                            type="text"
                                            :placeholder="t('base_value') + ': ' + (baseMailVal('MAIL_MAILER') || '—')"
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="block text-sm font-medium text-foreground">{{ t('mail_host') }}</label>
                                        <Input
                                            v-model="deployTargetsForm.flavors[activeFlavorTab].mail.MAIL_HOST"
                                            type="text"
                                            :placeholder="t('base_value') + ': ' + (baseMailVal('MAIL_HOST') || '—')"
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="block text-sm font-medium text-foreground">{{ t('mail_port') }}</label>
                                        <Input
                                            v-model="deployTargetsForm.flavors[activeFlavorTab].mail.MAIL_PORT"
                                            type="text"
                                            :placeholder="t('base_value') + ': ' + (baseMailVal('MAIL_PORT') || '—')"
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="block text-sm font-medium text-foreground">{{ t('mail_encryption') }}</label>
                                        <Input
                                            v-model="deployTargetsForm.flavors[activeFlavorTab].mail.MAIL_ENCRYPTION"
                                            type="text"
                                            :placeholder="t('base_value') + ': ' + (baseMailVal('MAIL_ENCRYPTION') || '—')"
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="block text-sm font-medium text-foreground">{{ t('mail_username') }}</label>
                                        <Input
                                            v-model="deployTargetsForm.flavors[activeFlavorTab].mail.MAIL_USERNAME"
                                            type="text"
                                            :placeholder="t('base_value') + ': ' + (baseMailVal('MAIL_USERNAME') || '—')"
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="block text-sm font-medium text-foreground">{{ t('mail_password') }}</label>
                                        <Input
                                            v-model="deployTargetsForm.flavors[activeFlavorTab].mail.MAIL_PASSWORD"
                                            type="password"
                                            :placeholder="baseMailVal('MAIL_PASSWORD') ? t('base_value') + ': ••••••' : t('base_value') + ': —'"
                                        />
                                    </div>
                                    <div class="space-y-2 sm:col-span-2">
                                        <label class="block text-sm font-medium text-foreground">{{ t('mail_from_address') }}</label>
                                        <Input
                                            v-model="deployTargetsForm.flavors[activeFlavorTab].mail.MAIL_FROM_ADDRESS"
                                            type="text"
                                            :placeholder="t('base_value') + ': ' + (baseMailVal('MAIL_FROM_ADDRESS') || '—')"
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- Firebase -->
                            <div v-show="flavorPanel === 'firebase'" class="space-y-3">
                                <p class="text-xs text-muted-foreground">{{ t('flavor_firebase_hint') }}</p>
                                <div class="flex items-center gap-2">
                                    <CheckCircle v-if="hasFirebase(activeFlavorTab)" class="size-4 text-emerald-500" />
                                    <Link2 v-else class="size-4 text-sky-500" />
                                    <span class="text-sm" :class="hasFirebase(activeFlavorTab) ? 'text-emerald-600' : 'text-muted-foreground'">
                                        {{ hasFirebase(activeFlavorTab) ? t('firebase_override_active') : t('firebase_inherits_base') }}
                                    </span>
                                </div>
                                <div class="flex flex-wrap items-center gap-3">
                                    <label
                                        class="inline-flex cursor-pointer items-center gap-2 rounded-md border border-input bg-transparent px-3 py-2 text-sm hover:bg-muted"
                                    >
                                        <Upload class="size-4" />
                                        <span>{{ t('upload_firebase_json') }}</span>
                                        <input
                                            type="file"
                                            accept="application/json,.json"
                                            class="hidden"
                                            @change="(e) => uploadFlavorFirebase(activeFlavorTab, e)"
                                        />
                                    </label>
                                    <Button
                                        v-if="hasFirebase(activeFlavorTab)"
                                        type="button"
                                        variant="outline"
                                        class="border-destructive text-destructive hover:bg-destructive hover:text-destructive-foreground"
                                        @click="deleteFlavorFirebase(activeFlavorTab)"
                                    >
                                        {{ t('remove') }}
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="pt-2">
                        <Button type="submit" :disabled="deployTargetsForm.processing">
                            <Loader2 v-if="deployTargetsForm.processing" class="me-2 h-4 w-4 animate-spin" />
                            {{ deployTargetsForm.processing ? t('saving') : t('save_deploy_config') }}
                        </Button>
                    </div>
                </form>
            </SettingsCard>
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
                    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="closeDeployModal"></div>
                    <div class="relative flex min-h-full items-center justify-center p-4" @click.self="closeDeployModal">
                        <div
                            class="relative flex max-h-[90vh] w-full max-w-lg transform flex-col rounded-2xl bg-card text-start shadow-xl transition-all"
                        >
                            <div class="min-h-0 flex-1 overflow-y-auto p-6">
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

                                <!-- Target selector — always selectable; readiness shown below -->
                                <div class="mb-4 space-y-2">
                                    <label class="block text-sm font-medium text-foreground">{{ t('deploy_target') }}</label>
                                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                        <button
                                            v-for="flavor in deployFlavors"
                                            :key="flavor"
                                            type="button"
                                            @click="deployOptions.flavor = flavor"
                                            class="flex flex-col items-center gap-1 rounded-xl border p-3 text-center text-sm font-medium capitalize transition-colors"
                                            :class="
                                                deployOptions.flavor === flavor
                                                    ? 'border-primary bg-primary/5 text-foreground'
                                                    : 'border-border text-muted-foreground hover:bg-muted/50'
                                            "
                                        >
                                            <span
                                                class="size-2 rounded-full"
                                                aria-hidden="true"
                                                :class="flavorReady(flavor) ? 'bg-emerald-500' : 'bg-amber-500'"
                                            ></span>
                                            {{ t('flavor_' + flavor) }}
                                            <span
                                                class="max-w-full truncate text-[10px] font-normal lowercase"
                                                :class="flavorDomain(flavor) ? 'text-muted-foreground' : 'text-amber-600'"
                                            >
                                                {{ flavorDomain(flavor) || t('no_domain_short') }}
                                            </span>
                                        </button>
                                    </div>
                                    <!-- Readiness for the chosen target -->
                                    <p v-if="flavorReady(deployOptions.flavor)" class="flex items-center gap-1.5 text-xs text-emerald-600">
                                        <span class="size-1.5 rounded-full bg-emerald-500" aria-hidden="true"></span>
                                        {{ t('deploy_target_ready') }}
                                    </p>
                                    <p v-else class="flex items-start gap-1.5 text-xs text-amber-600">
                                        <span class="mt-1 size-1.5 shrink-0 rounded-full bg-amber-500" aria-hidden="true"></span>
                                        <span>{{ t('deploy_target_missing') }}: {{ flavorMissing(deployOptions.flavor).join(', ') }}</span>
                                    </p>
                                </div>

                                <!-- Migration Options -->
                                <div class="space-y-4">
                                    <label class="block text-sm font-medium text-foreground">{{ t('database_migration') }}</label>

                                    <div class="space-y-3">
                                        <!-- Migrate only -->
                                        <label
                                            class="flex cursor-pointer items-start gap-3 rounded-xl border border-border p-4 transition-colors"
                                            :class="
                                                deployOptions.migration_option === 'migrate' ? 'border-primary bg-primary/5' : 'hover:bg-muted/50'
                                            "
                                        >
                                            <input
                                                type="radio"
                                                v-model="deployOptions.migration_option"
                                                value="migrate"
                                                class="mt-0.5 h-4 w-4 accent-primary"
                                            />
                                            <div class="flex-1">
                                                <span class="font-medium text-foreground">{{ t('migrate_only') }}</span>
                                                <p class="text-sm text-muted-foreground">{{ t('migrate_only_desc') }}</p>
                                            </div>
                                            <span class="rounded-full bg-emerald-500/10 px-2 py-0.5 text-xs font-medium text-emerald-600">
                                                {{ t('recommended') }}
                                            </span>
                                        </label>

                                        <!-- Fresh + Seed -->
                                        <label
                                            class="flex cursor-pointer items-start gap-3 rounded-xl border border-destructive/50 p-4 transition-colors"
                                            :class="
                                                deployOptions.migration_option === 'fresh_seed'
                                                    ? 'border-destructive bg-destructive/5'
                                                    : 'hover:bg-destructive/5'
                                            "
                                        >
                                            <input
                                                type="radio"
                                                v-model="deployOptions.migration_option"
                                                value="fresh_seed"
                                                class="mt-0.5 h-4 w-4 accent-destructive"
                                            />
                                            <div class="flex-1">
                                                <span class="font-medium text-destructive">{{ t('fresh_migrate_seed') }}</span>
                                                <p class="text-sm text-destructive/80">{{ t('fresh_migrate_seed_desc') }}</p>
                                            </div>
                                            <span class="rounded-full bg-destructive/10 px-2 py-0.5 text-xs font-medium text-destructive">
                                                {{ t('destructive') }}
                                            </span>
                                        </label>

                                        <!-- No migration -->
                                        <label
                                            class="flex cursor-pointer items-start gap-3 rounded-xl border border-border p-4 transition-colors"
                                            :class="deployOptions.migration_option === 'none' ? 'border-primary bg-primary/5' : 'hover:bg-muted/50'"
                                        >
                                            <input
                                                type="radio"
                                                v-model="deployOptions.migration_option"
                                                value="none"
                                                class="mt-0.5 h-4 w-4 accent-primary"
                                            />
                                            <div class="flex-1">
                                                <span class="font-medium text-foreground">{{ t('skip_migrations') }}</span>
                                                <p class="text-sm text-muted-foreground">{{ t('skip_migrations_desc') }}</p>
                                            </div>
                                        </label>
                                    </div>

                                    <!-- Seeder Picker -->
                                    <div class="rounded-xl border border-border p-4">
                                        <div class="flex flex-wrap items-start justify-between gap-2">
                                            <div class="min-w-0">
                                                <span class="font-medium text-foreground">{{ t('run_seeders_separately') }}</span>
                                                <p class="text-sm text-muted-foreground">{{ t('run_seeders_separately_desc') }}</p>
                                            </div>
                                            <div v-if="availableSeeders.length" class="flex shrink-0 items-center gap-2">
                                                <Button type="button" size="sm" variant="outline" @click="selectAllSeeders">
                                                    {{ t('seeders_select_all') }}
                                                </Button>
                                                <Button type="button" size="sm" variant="outline" @click="clearSeeders">
                                                    {{ t('seeders_clear') }}
                                                </Button>
                                            </div>
                                        </div>

                                        <div v-if="availableSeeders.length" class="mt-3 max-h-64 space-y-2 overflow-y-auto">
                                            <div
                                                v-for="seeder in availableSeeders"
                                                :key="seeder.class"
                                                class="flex items-start gap-3 rounded-lg border border-border p-3 transition-colors hover:bg-muted/50"
                                            >
                                                <Checkbox
                                                    v-model="deployOptions.seeders"
                                                    :value="seeder.class"
                                                    :aria-label="seeder.label"
                                                    class="mt-0.5 shrink-0"
                                                />
                                                <div class="min-w-0 flex-1 cursor-pointer" @click="toggleSeeder(seeder.class)">
                                                    <span class="text-sm font-medium text-foreground">{{ seeder.label }}</span>
                                                    <p v-if="seeder.description" class="line-clamp-2 text-xs text-muted-foreground">
                                                        {{ seeder.description }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        <p v-else class="mt-3 text-sm text-muted-foreground">{{ t('no_seeders_found') }}</p>
                                    </div>

                                    <!-- Safe Storage Deploy -->
                                    <div class="flex items-start gap-3 rounded-xl border border-border p-4">
                                        <Checkbox
                                            v-model="deployOptions.safe_storage_deploy"
                                            :aria-label="t('safe_storage_deploy')"
                                            class="mt-1 shrink-0"
                                        />
                                        <div class="min-w-0 flex-1">
                                            <span class="font-medium text-foreground">{{ t('safe_storage_deploy') }}</span>
                                            <p class="text-sm text-muted-foreground">{{ t('safe_storage_deploy_desc') }}</p>
                                        </div>
                                    </div>

                                    <!-- Generate API Docs -->
                                    <div class="flex items-start gap-3 rounded-xl border border-border p-4">
                                        <Checkbox v-model="deployOptions.generate_docs" :aria-label="t('generate_api_docs')" class="mt-1 shrink-0" />
                                        <div class="min-w-0 flex-1">
                                            <span class="font-medium text-foreground">{{ t('generate_api_docs') }}</span>
                                            <p class="text-sm text-muted-foreground">{{ t('generate_api_docs_desc') }}</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Warning for fresh -->
                                <div
                                    v-if="deployOptions.migration_option === 'fresh_seed'"
                                    role="alert"
                                    class="mt-4 rounded-xl border border-destructive/30 bg-destructive/10 p-4"
                                >
                                    <div class="flex items-center gap-2 text-destructive">
                                        <AlertTriangle class="size-5 shrink-0" aria-hidden="true" />
                                        <span class="font-medium">{{ t('warning') }}</span>
                                    </div>
                                    <p class="mt-2 text-sm text-destructive/80">{{ t('fresh_warning_message') }}</p>
                                </div>
                            </div>

                            <!-- Footer -->
                            <div class="flex shrink-0 gap-3 border-t border-border p-6 pt-4">
                                <Button type="button" variant="outline" @click="closeDeployModal" class="flex-1">
                                    {{ t('cancel') }}
                                </Button>
                                <Button
                                    type="button"
                                    @click="runDeploy"
                                    class="flex-1"
                                    :disabled="!flavorReady(deployOptions.flavor)"
                                    :class="
                                        deployOptions.migration_option === 'fresh_seed'
                                            ? 'bg-destructive text-destructive-foreground hover:bg-destructive/90'
                                            : 'bg-emerald-600 text-white hover:bg-emerald-700'
                                    "
                                >
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
                    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showLogModal = false"></div>
                    <div class="relative flex min-h-full items-center justify-center p-4" @click.self="showLogModal = false">
                        <div class="relative w-full max-w-3xl transform overflow-hidden rounded-2xl bg-card p-6 text-start shadow-xl transition-all">
                            <!-- Header -->
                            <div class="mb-4 flex items-center justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-sky-500/10">
                                        <FileCode class="size-5 text-sky-500" />
                                    </div>
                                    <h3 class="text-lg font-semibold text-foreground">{{ t('deploy_log') }}</h3>
                                </div>
                                <button
                                    type="button"
                                    :aria-label="t('close')"
                                    @click="showLogModal = false"
                                    class="rounded-full p-1 text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                >
                                    <X class="h-5 w-5" />
                                </button>
                            </div>

                            <pre
                                class="max-h-[60vh] overflow-auto rounded-xl bg-muted p-4 font-mono text-xs whitespace-pre-wrap text-muted-foreground"
                                >{{ deployLog }}</pre
                            >
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>

    <!-- Provision a flavor's hosting: attach it to a site that already exists, or make
         a subdomain for it, then create its database. -->
    <BaseModal :open="provisionOpen" :title="t('provision_on_hostinger')" size="lg" :busy="provisionForm.processing" @close="provisionOpen = false">
        <form id="hostinger-provision-form" class="space-y-4" @submit.prevent="submitProvision">
            <p class="text-sm text-muted-foreground">
                {{ t('provision_for_flavor', { flavor: t('flavor_' + provisionFlavor) }) }}
            </p>

            <p v-if="hostingerLoading" class="flex items-center gap-2 text-sm text-muted-foreground">
                <Loader2 class="size-4 animate-spin" />
                {{ t('loading') }}
            </p>
            <p v-else-if="hostingerError" class="text-sm text-destructive">{{ hostingerError }}</p>
            <p v-else-if="hostingerSites.length === 0 && hostingerOrders.length === 0" class="text-sm text-destructive">
                {{ t('hostinger_no_sites') }}
            </p>

            <template v-if="!hostingerLoading && (hostingerSites.length > 0 || hostingerOrders.length > 0)">
                <!-- One picker for both: attaching to a site that exists and creating a
                     new one answer the same question, so the mode is read off the choice
                     rather than asked for first. The same domain can appear in both
                     groups — as a website to attach to, and as the base for a subdomain
                     of it — which is why the existing entries are prefixed. -->
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-foreground">{{ t('website') }}</label>
                    <Select v-model="newSiteBase">
                        <SelectTrigger><SelectValue :placeholder="t('select_website')" /></SelectTrigger>
                        <SelectContent>
                            <SelectGroup v-if="hostingerSites.length > 0">
                                <SelectLabel>{{ t('provision_group_existing') }}</SelectLabel>
                                <SelectItem v-for="site in hostingerSites" :key="site.domain" :value="EXISTING_PREFIX + site.domain">
                                    {{ site.domain }}
                                </SelectItem>
                            </SelectGroup>

                            <SelectGroup>
                                <SelectLabel>{{ t('provision_group_create') }}</SelectLabel>
                                <SelectItem :value="FREE_DOMAIN">{{ t('free_hostinger_subdomain') }}</SelectItem>
                                <!-- Never disabled: a hosted domain is still the base for a subdomain website.
                                     The bare-host case is refused inline by `needsPrefix` instead. -->
                                <SelectItem v-for="d in newSiteDomainOptions" :key="d.domain" :value="d.domain">
                                    {{ d.domain }}
                                    <span class="text-xs text-muted-foreground">
                                        <template v-if="d.hosted">— {{ t('already_hosted') }}</template>
                                        <template v-else-if="!d.registered">— {{ t('not_registered_here') }}</template>
                                    </span>
                                </SelectItem>
                                <SelectItem :value="OTHER_DOMAIN">{{ t('other_domain') }}</SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                    <p class="text-xs text-muted-foreground">{{ t('provision_target_hint') }}</p>
                    <p v-if="provisionForm.errors.domain" class="text-xs text-destructive">{{ provisionForm.errors.domain }}</p>
                </div>

                <!-- Creating only: where it hangs and what it is called. -->
                <div v-if="creatingSite && newSiteBase !== ''" class="space-y-3">
                    <p v-if="usingFreeDomain" class="text-xs text-muted-foreground">{{ t('free_hostinger_subdomain_hint') }}</p>

                    <div v-if="newSiteBase === OTHER_DOMAIN" class="space-y-2">
                        <label for="hostinger_custom_domain" class="block text-sm font-medium text-foreground">{{ t('domain') }}</label>
                        <Input id="hostinger_custom_domain" v-model="newSiteCustom" type="text" placeholder="example.com" />
                        <p class="text-xs text-muted-foreground">{{ t('other_domain_hint') }}</p>
                    </div>

                    <div v-if="!usingFreeDomain" class="space-y-2">
                        <label for="hostinger_site_prefix" class="block text-sm font-medium text-foreground">
                            {{ t('subdomain') }} <span class="text-muted-foreground">({{ t('optional') }})</span>
                        </label>
                        <Input id="hostinger_site_prefix" v-model="newSitePrefix" type="text" :placeholder="provisionFlavor" />
                        <p v-if="needsPrefix" class="text-xs text-destructive">{{ t('domain_needs_prefix') }}</p>
                        <p v-else class="text-xs text-muted-foreground">{{ t('new_site_prefix_hint') }}</p>
                    </div>
                </div>

                <div v-if="creatingSite && newSiteBase !== ''" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-foreground">{{ t('hosting_plan') }}</label>
                        <Select :model-value="provisionForm.order_id" @update:model-value="onOrderPicked">
                            <SelectTrigger><SelectValue :placeholder="t('select_hosting_plan')" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="order in hostingerOrders" :key="order.id" :value="order.id">
                                    {{ order.plan }} (#{{ order.id }})
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="provisionForm.errors.order_id" class="text-xs text-destructive">{{ provisionForm.errors.order_id }}</p>
                    </div>

                    <!-- Only the first website on a plan needs one; afterwards it is ignored. -->
                    <div v-if="hostingerDatacenters.length > 0" class="space-y-2">
                        <label class="block text-sm font-medium text-foreground">{{ t('datacenter') }}</label>
                        <Select v-model="provisionForm.datacenter_code">
                            <SelectTrigger><SelectValue :placeholder="t('datacenter_optional')" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="dc in hostingerDatacenters" :key="dc.code" :value="dc.code">
                                    {{ dc.title }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p class="text-xs text-muted-foreground">{{ t('datacenter_hint') }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <label for="hostinger_db_name" class="block text-sm font-medium text-foreground">{{ t('db_database') }}</label>
                        <Input id="hostinger_db_name" v-model="provisionForm.db_name" type="text" :placeholder="provisionFlavor" />
                        <p v-if="provisionForm.errors.db_name" class="text-xs text-destructive">{{ provisionForm.errors.db_name }}</p>
                    </div>
                    <div class="space-y-2">
                        <label for="hostinger_db_user" class="block text-sm font-medium text-foreground">{{ t('db_username') }}</label>
                        <Input id="hostinger_db_user" v-model="provisionForm.db_user" type="text" :placeholder="provisionFlavor" />
                        <p v-if="provisionForm.errors.db_user" class="text-xs text-destructive">{{ provisionForm.errors.db_user }}</p>
                    </div>
                    <p class="text-xs text-muted-foreground sm:col-span-2">{{ t('hostinger_prefix_hint') }}</p>
                </div>

                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-border bg-muted/30 p-3">
                    <Checkbox v-model="provisionForm.scheduler_cron" class="mt-0.5 shrink-0" />
                    <span class="flex-1">
                        <span class="block text-sm font-medium text-foreground">{{ t('scheduler_cron') }}</span>
                        <span class="block text-xs text-muted-foreground">{{ t('scheduler_cron_desc') }}</span>
                    </span>
                </label>

                <div class="rounded-xl border border-amber-500/30 bg-amber-500/5 p-3 text-xs text-muted-foreground">
                    <p class="font-medium text-foreground">{{ t('provision_summary') }}</p>
                    <p class="font-mono">{{ provisionResultDomain }}</p>
                    <p class="mt-2">{{ t('provision_warning') }}</p>
                </div>
            </template>
        </form>

        <template #footer>
            <Button type="button" variant="outline" @click="provisionOpen = false">{{ t('cancel') }}</Button>
            <Button type="submit" form="hostinger-provision-form" :disabled="provisionForm.processing || hostingerLoading || !provisionReady">
                <Loader2 v-if="provisionForm.processing" class="me-2 size-4 animate-spin" />
                {{ provisionForm.processing ? t('creating') : t('create') }}
            </Button>
        </template>
    </BaseModal>
</template>
