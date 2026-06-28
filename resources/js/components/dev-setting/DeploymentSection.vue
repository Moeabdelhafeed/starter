<script setup>
import { useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    Loader2, CheckCircle, XCircle, GitBranch, Rocket, Mail, Link2, Server,
    RefreshCw, GitCommit, Plus, ChevronDown, ChevronRight, FileCode, FilePlus,
    FileEdit, ArrowUpCircle, ArrowDownCircle, X, Upload,
} from 'lucide-vue-next';
import Checkbox from '@/components/ui/checkbox/Checkbox.vue';
import Input from '@/components/ui/input/Input.vue';
import Button from '@/components/ui/button/Button.vue';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import SettingsCard from '@/components/dev-setting/SettingsCard.vue';

const { t } = useI18n();

const props = defineProps({
    git: Object,
    deployConfig: Object,
    deployLog: { type: String, default: null },
    baseMail: Object,
    pusherConfig: Object,
    urls: Object,
});

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
    router.post(route('dev_settings.git_disconnect'), { _method: 'DELETE' }, {
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

// Push to GitHub
const pushing = ref(false);
const pushToGithub = () => {
    pushing.value = true;
    router.post(route('dev_settings.push'), {}, {
        preserveScroll: true,
        onFinish: () => { pushing.value = false; },
    });
};

// Deployment Targets (dev / staging / uat / production)
const deployFlavors = ['dev', 'staging', 'uat', 'production'];

const blankSsh = (src) => ({
    host: src?.host || '',
    port: src?.port || 65002,
    username: src?.username || '',
    password: src?.password || '',
});
const blankDb = (src) => ({
    DB_HOST: src?.DB_HOST || '',
    DB_PORT: src?.DB_PORT || '3306',
    DB_DATABASE: src?.DB_DATABASE || '',
    DB_USERNAME: src?.DB_USERNAME || '',
    DB_PASSWORD: src?.DB_PASSWORD || '',
});
const blankFlavorEnv = (src) => ({
    APP_DEBUG: src?.APP_DEBUG || 'inherit',
    IS_TESTING: src?.IS_TESTING || 'inherit',
    FRONTEND_URL: src?.FRONTEND_URL || '',
});

// Base (.env.production) values shown as placeholders so it's obvious what each
// flavor inherits when a field is left blank.
const baseMailVal = (key) => props.baseMail?.[key] || '';
const basePusher = (key) => props.pusherConfig?.base?.[key] || '';
const blankPusher = (src) => ({
    PUSHER_APP_ID: src?.PUSHER_APP_ID || '',
    PUSHER_APP_KEY: src?.PUSHER_APP_KEY || '',
    PUSHER_APP_SECRET: src?.PUSHER_APP_SECRET || '',
    PUSHER_APP_CLUSTER: src?.PUSHER_APP_CLUSTER || '',
});
const blankMail = (src) => ({
    MAIL_MAILER: src?.MAIL_MAILER || '',
    MAIL_HOST: src?.MAIL_HOST || '',
    MAIL_PORT: src?.MAIL_PORT || '',
    MAIL_USERNAME: src?.MAIL_USERNAME || '',
    MAIL_PASSWORD: src?.MAIL_PASSWORD || '',
    MAIL_ENCRYPTION: src?.MAIL_ENCRYPTION || '',
    MAIL_FROM_ADDRESS: src?.MAIL_FROM_ADDRESS || '',
});

const buildFlavors = () => {
    const out = {};
    for (const f of deployFlavors) {
        const stored = props.deployConfig?.flavors?.[f] || {};
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

const hasFirebase = (flavor) => Boolean(props.deployConfig?.flavors?.[flavor]?.has_firebase);

const uploadFlavorFirebase = (flavor, event) => {
    const file = event.target.files?.[0];
    if (!file) return;
    router.post(route('dev_settings.flavor_firebase'), { flavor, firebase_json: file }, {
        forceFormData: true,
        preserveScroll: true,
        preserveState: true,
        reset: ['deployConfig', 'success', 'error'],
    });
    event.target.value = '';
};

const deleteFlavorFirebase = (flavor) => {
    router.post(route('dev_settings.flavor_firebase_delete'), { flavor }, {
        preserveScroll: true,
        preserveState: true,
        reset: ['deployConfig', 'success', 'error'],
    });
};

const deployTargetsForm = useForm({
    share_ssh: props.deployConfig?.share_ssh ?? true,
    ssh: blankSsh(props.deployConfig?.ssh),
    flavors: buildFlavors(),
});

const activeFlavorTab = ref('production');

// FRONTEND_URL per flavor: inherit base vs custom. Empty string = inherit.
const frontendUrlMode = ref(
    Object.fromEntries(deployFlavors.map((f) => [f, deployTargetsForm.flavors[f]?.env?.FRONTEND_URL ? 'custom' : 'inherit'])),
);
const setFrontendUrlMode = (flavor, mode) => {
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
const deployOptions = ref({
    flavor: 'production',
    migration_option: 'migrate', // Default to safe option
    run_seeders: false,
    safe_storage_deploy: true, // Default on — preserve uploaded files.
});

const flavorDomain = (flavor) => deployTargetsForm.flavors[flavor]?.domain || '';

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
const flavorSsh = (flavor) => {
    const shared = deployTargetsForm.ssh;
    const own = deployTargetsForm.flavors[flavor]?.ssh;
    if (deployTargetsForm.share_ssh) return shared?.host ? shared : own;
    return own?.host ? own : shared;
};

const flavorReady = (flavor) => flavorMissing(flavor).length === 0;

// Human-readable list of what a flavor still needs before it can deploy.
const flavorMissing = (flavor) => {
    const f = deployTargetsForm.flavors[flavor];
    const ssh = flavorSsh(flavor);
    const missing = [];
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
        onFinish: () => { deploying.value = false; },
    });
};
</script>

<template>
    <div>
        <div class="space-y-5">
            <!-- GitHub -->
            <SettingsCard :title="t('github')" :description="t('github_desc')">
                <template #icon><GitBranch class="size-5 text-foreground" /></template>
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
                        <Button size="sm" variant="outline"
                            class="border-primary text-primary hover:bg-primary hover:text-primary-foreground"
                            :disabled="pushing" @click="pushToGithub">
                            <Loader2 v-if="pushing" class="me-1 h-3 w-3 animate-spin" />
                            <ArrowUpCircle v-else class="me-1 size-3" />
                            {{ pushing ? t('pushing') : t('push_to_github') }}
                        </Button>
                    </div>
                </template>

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
            </SettingsCard>

            <!-- Deployment Targets -->
            <SettingsCard :title="t('deployment_targets')" :description="t('deployment_targets_desc')">
                <template #icon><Server class="size-5 text-orange-500" /></template>
                <template #actions>
                    <div class="flex items-center gap-2">
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
                    </div>
                </template>

                <form @submit.prevent="submitDeployTargets" class="space-y-6">
                    <!-- Shared SSH toggle -->
                    <div class="flex items-start gap-3 rounded-xl border border-border p-4">
                        <Checkbox v-model="deployTargetsForm.share_ssh" class="mt-1 shrink-0" />
                        <div class="flex-1 min-w-0">
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
                                <Input v-model="deployTargetsForm.ssh.host" type="text"
                                    placeholder="us-bos-web1568.main-hosting.eu" />
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
                        <button v-for="flavor in deployFlavors" :key="flavor" type="button"
                            @click="activeFlavorTab = flavor"
                            class="flex items-center gap-2 rounded-lg border px-3 py-1.5 text-sm font-medium capitalize transition-colors"
                            :class="activeFlavorTab === flavor
                                ? 'border-primary bg-primary/5 text-foreground'
                                : 'border-border text-muted-foreground hover:bg-muted'">
                            <span class="size-2 rounded-full"
                                :class="flavorReady(flavor) ? 'bg-emerald-500' : 'bg-muted-foreground/40'"></span>
                            {{ t('flavor_' + flavor) }}
                            <span v-if="flavorDomain(flavor)" class="text-xs font-normal lowercase text-muted-foreground">· {{ flavorDomain(flavor) }}</span>
                        </button>
                    </div>

                    <!-- Per-flavor config — single block bound to the active tab -->
                    <div :key="activeFlavorTab" class="space-y-4 rounded-xl border border-border p-4">
                        <!-- Domain -->
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-foreground">{{ t('domain') }} — {{ t('flavor_' + activeFlavorTab) }}</label>
                            <div class="flex items-stretch overflow-hidden rounded-md border border-input bg-transparent transition-[color,box-shadow] focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50">
                                <span class="flex select-none items-center border-e border-input bg-muted px-3 text-sm text-muted-foreground">https://</span>
                                <Input v-model="deployTargetsForm.flavors[activeFlavorTab].domain" type="text"
                                    placeholder="example.hostingersite.com"
                                    class="h-9 flex-1 rounded-none border-0 shadow-none focus-visible:ring-0" />
                            </div>
                            <p class="text-xs text-muted-foreground">{{ t('domain_https_hint') }}</p>
                        </div>

                        <!-- Per-flavor SSH (only when not shared) -->
                        <div v-if="!deployTargetsForm.share_ssh" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">{{ t('ssh_host') }}</label>
                                <Input v-model="deployTargetsForm.flavors[activeFlavorTab].ssh.host" type="text"
                                    placeholder="us-bos-web1568.main-hosting.eu" />
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
                                    <Input v-model="deployTargetsForm.flavors[activeFlavorTab].db.DB_DATABASE" type="text" :placeholder="activeFlavorTab + '_db'" />
                                </div>
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-foreground">{{ t('db_username') }}</label>
                                    <Input v-model="deployTargetsForm.flavors[activeFlavorTab].db.DB_USERNAME" type="text" placeholder="root" />
                                </div>
                                <div class="space-y-2 sm:col-span-2">
                                    <label class="block text-sm font-medium text-foreground">{{ t('db_password') }}</label>
                                    <Input v-model="deployTargetsForm.flavors[activeFlavorTab].db.DB_PASSWORD" type="password" placeholder="••••••••" />
                                </div>
                            </div>
                        </div>

                        <!-- Advanced per-flavor overrides -->
                        <div class="border-t border-border pt-4">
                            <!-- Inheritance explainer -->
                            <div class="mb-4 flex items-start gap-2 rounded-xl border border-sky-500/30 bg-sky-500/5 p-3">
                                <Link2 class="mt-0.5 size-4 shrink-0 text-sky-500" />
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
                                <button v-for="panel in ['env', 'pusher', 'mail', 'firebase']" :key="panel"
                                    type="button" @click="flavorPanel = panel"
                                    class="rounded-lg border px-3 py-1 text-xs font-medium transition-colors"
                                    :class="flavorPanel === panel
                                        ? 'border-primary bg-primary/5 text-foreground'
                                        : 'border-border text-muted-foreground hover:bg-muted'">
                                    {{ t('flavor_panel_' + panel) }}
                                </button>
                            </div>

                            <!-- Environment -->
                            <div v-show="flavorPanel === 'env'" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div class="space-y-2 sm:col-span-2">
                                    <label class="block text-sm font-medium text-foreground">FRONTEND_URL</label>
                                    <Select :model-value="frontendUrlMode[activeFlavorTab]" @update:model-value="(v) => setFrontendUrlMode(activeFlavorTab, v)">
                                        <SelectTrigger><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="inherit">{{ t('inherit_base') }} ({{ props.urls?.base?.FRONTEND_URL || '—' }})</SelectItem>
                                            <SelectItem value="custom">{{ t('custom') }}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <Input v-if="frontendUrlMode[activeFlavorTab] === 'custom'"
                                        v-model="deployTargetsForm.flavors[activeFlavorTab].env.FRONTEND_URL" type="text"
                                        placeholder="https://app.example.com" />
                                </div>
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
                            </div>

                            <!-- Pusher -->
                            <div v-show="flavorPanel === 'pusher'" class="space-y-4">
                                <label class="flex items-start gap-3 rounded-xl border border-border p-3">
                                    <Checkbox v-model="deployTargetsForm.flavors[activeFlavorTab].inherit_pusher" class="mt-0.5 shrink-0" />
                                    <span class="flex-1 min-w-0">
                                        <span class="text-sm font-medium text-foreground">{{ t('inherit_pusher_from_base') }}</span>
                                        <span class="block text-xs text-muted-foreground">{{ t('inherit_group_hint') }}</span>
                                    </span>
                                </label>
                                <p v-if="deployTargetsForm.flavors[activeFlavorTab].inherit_pusher" class="flex items-center gap-2 text-xs text-sky-600">
                                    <Link2 class="size-3.5" /> {{ t('inheriting_base_now') }}
                                </p>
                                <template v-else>
                                <p class="text-xs text-amber-600">{{ t('flavor_pusher_note') }}</p>
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div class="space-y-2">
                                        <label class="block text-sm font-medium text-foreground">{{ t('pusher_app_id') }}</label>
                                        <Input v-model="deployTargetsForm.flavors[activeFlavorTab].pusher.PUSHER_APP_ID" type="text"
                                            :placeholder="t('base_value') + ': ' + (basePusher('app_id') || '—')" />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="block text-sm font-medium text-foreground">{{ t('pusher_app_key') }}</label>
                                        <Input v-model="deployTargetsForm.flavors[activeFlavorTab].pusher.PUSHER_APP_KEY" type="text"
                                            :placeholder="t('base_value') + ': ' + (basePusher('app_key') || '—')" />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="block text-sm font-medium text-foreground">{{ t('pusher_app_secret') }}</label>
                                        <Input v-model="deployTargetsForm.flavors[activeFlavorTab].pusher.PUSHER_APP_SECRET" type="password"
                                            :placeholder="basePusher('app_secret') ? t('base_value') + ': ••••••' : t('base_value') + ': —'" />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="block text-sm font-medium text-foreground">{{ t('pusher_app_cluster') }}</label>
                                        <Input v-model="deployTargetsForm.flavors[activeFlavorTab].pusher.PUSHER_APP_CLUSTER" type="text"
                                            :placeholder="t('base_value') + ': ' + (basePusher('app_cluster') || '—')" />
                                    </div>
                                </div>
                                </template>
                            </div>

                            <!-- Mail -->
                            <div v-show="flavorPanel === 'mail'" class="space-y-4">
                                <label class="flex items-start gap-3 rounded-xl border border-border p-3">
                                    <Checkbox v-model="deployTargetsForm.flavors[activeFlavorTab].inherit_mail" class="mt-0.5 shrink-0" />
                                    <span class="flex-1 min-w-0">
                                        <span class="text-sm font-medium text-foreground">{{ t('inherit_mail_from_base') }}</span>
                                        <span class="block text-xs text-muted-foreground">{{ t('inherit_group_hint') }}</span>
                                    </span>
                                </label>
                                <p v-if="deployTargetsForm.flavors[activeFlavorTab].inherit_mail" class="flex items-center gap-2 text-xs text-sky-600">
                                    <Link2 class="size-3.5" /> {{ t('inheriting_base_now') }}
                                </p>
                                <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-foreground">{{ t('mail_mailer') }}</label>
                                    <Input v-model="deployTargetsForm.flavors[activeFlavorTab].mail.MAIL_MAILER" type="text"
                                        :placeholder="t('base_value') + ': ' + (baseMailVal('MAIL_MAILER') || '—')" />
                                </div>
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-foreground">{{ t('mail_host') }}</label>
                                    <Input v-model="deployTargetsForm.flavors[activeFlavorTab].mail.MAIL_HOST" type="text"
                                        :placeholder="t('base_value') + ': ' + (baseMailVal('MAIL_HOST') || '—')" />
                                </div>
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-foreground">{{ t('mail_port') }}</label>
                                    <Input v-model="deployTargetsForm.flavors[activeFlavorTab].mail.MAIL_PORT" type="text"
                                        :placeholder="t('base_value') + ': ' + (baseMailVal('MAIL_PORT') || '—')" />
                                </div>
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-foreground">{{ t('mail_encryption') }}</label>
                                    <Input v-model="deployTargetsForm.flavors[activeFlavorTab].mail.MAIL_ENCRYPTION" type="text"
                                        :placeholder="t('base_value') + ': ' + (baseMailVal('MAIL_ENCRYPTION') || '—')" />
                                </div>
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-foreground">{{ t('mail_username') }}</label>
                                    <Input v-model="deployTargetsForm.flavors[activeFlavorTab].mail.MAIL_USERNAME" type="text"
                                        :placeholder="t('base_value') + ': ' + (baseMailVal('MAIL_USERNAME') || '—')" />
                                </div>
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-foreground">{{ t('mail_password') }}</label>
                                    <Input v-model="deployTargetsForm.flavors[activeFlavorTab].mail.MAIL_PASSWORD" type="password"
                                        :placeholder="baseMailVal('MAIL_PASSWORD') ? t('base_value') + ': ••••••' : t('base_value') + ': —'" />
                                </div>
                                <div class="space-y-2 sm:col-span-2">
                                    <label class="block text-sm font-medium text-foreground">{{ t('mail_from_address') }}</label>
                                    <Input v-model="deployTargetsForm.flavors[activeFlavorTab].mail.MAIL_FROM_ADDRESS" type="text"
                                        :placeholder="t('base_value') + ': ' + (baseMailVal('MAIL_FROM_ADDRESS') || '—')" />
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
                                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-md border border-input bg-transparent px-3 py-2 text-sm hover:bg-muted">
                                        <Upload class="size-4" />
                                        <span>{{ t('upload_firebase_json') }}</span>
                                        <input type="file" accept="application/json,.json" class="hidden"
                                            @change="(e) => uploadFlavorFirebase(activeFlavorTab, e)" />
                                    </label>
                                    <Button v-if="hasFirebase(activeFlavorTab)" type="button" variant="outline"
                                        class="border-red-500 text-red-500 hover:bg-red-500 hover:text-white"
                                        @click="deleteFlavorFirebase(activeFlavorTab)">
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

                            <!-- Target selector — always selectable; readiness shown below -->
                            <div class="mb-4 space-y-2">
                                <label class="block text-sm font-medium text-foreground">{{ t('deploy_target') }}</label>
                                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                    <button v-for="flavor in deployFlavors" :key="flavor" type="button"
                                        @click="deployOptions.flavor = flavor"
                                        class="flex flex-col items-center gap-1 rounded-xl border p-3 text-center text-sm font-medium capitalize transition-colors"
                                        :class="deployOptions.flavor === flavor
                                            ? 'border-primary bg-primary/5 text-foreground'
                                            : 'border-border text-muted-foreground hover:bg-muted/50'">
                                        <span class="size-2 rounded-full"
                                            :class="flavorReady(flavor) ? 'bg-emerald-500' : 'bg-amber-500'"></span>
                                        {{ t('flavor_' + flavor) }}
                                        <span class="max-w-full truncate text-[10px] font-normal lowercase"
                                            :class="flavorDomain(flavor) ? 'text-muted-foreground' : 'text-amber-600'">
                                            {{ flavorDomain(flavor) || t('no_domain_short') }}
                                        </span>
                                    </button>
                                </div>
                                <!-- Readiness for the chosen target -->
                                <p v-if="flavorReady(deployOptions.flavor)" class="flex items-center gap-1.5 text-xs text-emerald-600">
                                    <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                    {{ t('deploy_target_ready') }}
                                </p>
                                <p v-else class="flex items-start gap-1.5 text-xs text-amber-600">
                                    <span class="mt-1 size-1.5 shrink-0 rounded-full bg-amber-500"></span>
                                    <span>{{ t('deploy_target_missing') }}: {{ flavorMissing(deployOptions.flavor).join(', ') }}</span>
                                </p>
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
                                <Button type="button" @click="runDeploy" class="flex-1"
                                    :disabled="!flavorReady(deployOptions.flavor)"
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
    </div>
</template>
