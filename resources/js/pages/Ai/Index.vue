<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Bot,
    Check,
    Loader2,
    MessageSquare,
    PanelLeft,
    Plus,
    Send,
    Sparkles,
    Trash2,
    TriangleAlert,
    User as UserIcon,
    Wrench,
    X,
} from 'lucide-vue-next';
import { computed, nextTick, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

import Button from '@/components/ui/button/Button.vue';
import { useDateFormat } from '@/composables/useDateFormat';
import Default from '@/layouts/default.vue';
import { getJson, postJson, streamJson } from '@/lib/json-api';

defineOptions({ layout: Default });

const { t } = useI18n();
const { formatDate } = useDateFormat();

type Conversation = { id: number; title: string | null; updated_at: string | null };

const props = withDefaults(
    defineProps<{
        /** Which model backend is wired up, e.g. "Ollama (llama3.2)". */
        backend?: string;
        backendReady?: boolean;
        conversations?: Conversation[];
    }>(),
    { backend: '', backendReady: false, conversations: () => [] },
);

/** A change the assistant proposed, waiting on this admin to approve it. */
type Pending = { message_id: number; summary: string };

type Turn = {
    role: 'user' | 'assistant';
    content: string;
    /** Tools the assistant called for this answer, shown so it stays auditable. */
    tools?: string[];
    failed?: boolean;
    pending?: Pending | null;
    /** Set while the answer is still arriving, so the caret shows. */
    streaming?: boolean;
};

const chats = ref<Conversation[]>([...props.conversations]);
const activeId = ref<number | null>(null);
const turns = ref<Turn[]>([]);
const draft = ref('');
const sending = ref(false);
const loadingChat = ref(false);
const confirming = ref<number | null>(null);
const railOpen = ref(false);
const log = ref<HTMLElement | null>(null);

// The list is re-sent with every reply, so the sidebar reorders itself.
watch(
    () => props.conversations,
    (value: Conversation[]) => {
        chats.value = [...value];
    },
);

/**
 * Tool names are wire identifiers (`list_records`), not copy. Shown raw they
 * read as a leaked internal — and they are the one part of the answer a
 * non-technical admin most needs to understand, since they are the evidence
 * the reply came from real data. Falls back to the raw name so a tool added
 * without a key still shows something.
 */
const toolLabel = (tool: string): string => {
    const key = `ai_tool_${tool}`;
    const label = t(key);

    return label === key ? tool : label;
};

/** Whether the turn in flight has produced anything yet. */
const streamStarted = computed(() => {
    const last = turns.value[turns.value.length - 1];

    return last?.role === 'assistant' && (last.content !== '' || (last.tools?.length ?? 0) > 0);
});

const activeTitle = computed(() => chats.value.find((c: Conversation) => c.id === activeId.value)?.title ?? t('ai_new_chat'));

const scrollToEnd = async () => {
    await nextTick();
    const el = log.value;
    if (el) {
        el.scrollTop = el.scrollHeight;
    }
};

const newChat = () => {
    activeId.value = null;
    turns.value = [];
    draft.value = '';
    railOpen.value = false;
};

const openChat = async (id: number) => {
    if (id === activeId.value) {
        railOpen.value = false;

        return;
    }

    loadingChat.value = true;
    railOpen.value = false;

    const result = await getJson<{ id: number; messages: Turn[] }>(route('ai.conversation', id), t('ai_failed'));

    if (result.ok) {
        activeId.value = result.data.id;
        turns.value = result.data.messages;
        scrollToEnd();
    }

    loadingChat.value = false;
};

const deleteChat = (id: number) => {
    router.delete(route('ai.conversation.destroy', id), {
        preserveScroll: true,
        onSuccess: () => {
            if (activeId.value === id) {
                newChat();
            }
        },
    });
};

/**
 * Approve a proposed change.
 *
 * Only the message id is sent. The action itself lives on that row server-side,
 * so approving cannot be turned into approving something else.
 */
const confirmAction = async (turn: Turn) => {
    if (!turn.pending || confirming.value !== null) {
        return;
    }

    confirming.value = turn.pending.message_id;

    const result = await postJson<{ reply: string }>(route('ai.confirm', turn.pending.message_id), {}, t('ai_failed'));

    turn.pending = null;
    turns.value.push(result.ok ? { role: 'assistant', content: result.data.reply } : { role: 'assistant', content: result.error, failed: true });

    confirming.value = null;
    scrollToEnd();
};

/** Decline it. Nothing to tell the server: an unconfirmed action never runs. */
const dismissAction = (turn: Turn) => {
    turn.pending = null;
};

const send = async () => {
    const question = draft.value.trim();

    if (question === '' || sending.value) {
        return;
    }

    turns.value.push({ role: 'user', content: question });
    draft.value = '';
    sending.value = true;
    scrollToEnd();

    // The answer is written into this turn as it arrives. A local model takes
    // tens of seconds, so waiting for the whole thing before showing anything
    // is indistinguishable from a hang — and long enough for a proxy to agree.
    const answer = reactive<Turn>({ role: 'assistant', content: '', tools: [], streaming: true });
    turns.value.push(answer);

    // No history in the payload: the server reads it back from the conversation,
    // so the transcript is not the browser's to rewrite.
    const result = await streamJson(route('ai.chat.stream'), { message: question, conversation_id: activeId.value }, t('ai_failed'), (event) => {
        if (event.type === 'token') {
            answer.content += String(event.text ?? '');
        } else if (event.type === 'reset') {
            // That text belonged to a hop that turned out to be a tool call.
            answer.content = '';
        } else if (event.type === 'tool') {
            // Shown as each one runs, so a slow turn says what it is doing.
            answer.tools = [...(answer.tools ?? []), String(event.name ?? '')];
        } else if (event.type === 'error') {
            answer.content = String(event.message ?? t('ai_failed'));
            answer.failed = true;
        } else if (event.type === 'done') {
            // The server's copy wins: the streamed text is the raw model
            // output, while this has been through the same cleanup as a
            // reloaded conversation, and it is what was stored.
            answer.content = String(event.reply ?? answer.content);
            answer.tools = (event.tools_used as string[]) ?? [];
            answer.pending = (event.pending as Pending | null) ?? null;

            const conversation = event.conversation as Conversation;
            activeId.value = conversation.id;
            chats.value = event.conversations as Conversation[];
        }

        scrollToEnd();
    });

    if (!result.ok) {
        answer.content = result.error;
        answer.failed = true;
    }

    answer.streaming = false;
    sending.value = false;
    scrollToEnd();
};
</script>

<template>
    <Head :title="t('ai_assistant')" />

    <div class="h-full min-h-[100dvh] w-full bg-background">
        <div class="mx-auto flex w-full max-w-[1300px] flex-col gap-5 px-4 py-10 text-start md:py-20">
            <div class="flex items-center gap-3 rounded-2xl border bg-card p-4">
                <Sparkles class="size-5 shrink-0 text-primary" />
                <div class="min-w-0 flex-1">
                    <h1 class="truncate text-lg font-semibold text-foreground">{{ t('ai_assistant') }}</h1>
                    <p class="truncate text-sm text-muted-foreground">{{ t('ai_assistant_desc') }}</p>
                </div>

                <Button type="button" variant="outline" size="sm" class="lg:hidden" :aria-label="t('ai_history')" @click="railOpen = !railOpen">
                    <PanelLeft class="size-4" />
                </Button>
            </div>

            <div v-if="!backendReady" class="rounded-xl border border-destructive/30 bg-destructive/5 p-4 text-sm font-medium text-destructive">
                {{ t('ai_backend_missing') }}
            </div>

            <div class="flex gap-5">
                <!-- History rail. Off-canvas below lg, where the chat needs the width. -->
                <aside
                    class="w-64 shrink-0 rounded-2xl border bg-card p-3 max-lg:fixed max-lg:inset-y-0 max-lg:start-0 max-lg:z-50 max-lg:w-72 max-lg:overflow-y-auto max-lg:rounded-none max-lg:shadow-2xl max-lg:transition-transform lg:block"
                    :class="railOpen ? 'max-lg:translate-x-0' : 'max-lg:ltr:-translate-x-full max-lg:rtl:translate-x-full'"
                >
                    <Button type="button" class="mb-3 w-full justify-start gap-2" @click="newChat">
                        <Plus class="size-4" />
                        {{ t('ai_new_chat') }}
                    </Button>

                    <p v-if="chats.length === 0" class="px-2 py-6 text-center text-xs text-muted-foreground">
                        {{ t('ai_no_history') }}
                    </p>

                    <nav v-else class="max-h-[50vh] space-y-1 overflow-y-auto scroll-shadows" :aria-label="t('ai_history')">
                        <div
                            v-for="chat in chats"
                            :key="chat.id"
                            class="group flex items-center gap-1 rounded-lg transition-colors"
                            :class="chat.id === activeId ? 'bg-primary/10' : 'hover:bg-muted'"
                        >
                            <button
                                type="button"
                                class="min-w-0 flex-1 px-3 py-2 text-start focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                @click="openChat(chat.id)"
                            >
                                <span class="block truncate text-sm" :class="chat.id === activeId ? 'text-foreground' : 'text-muted-foreground'">
                                    {{ chat.title || t('ai_untitled') }}
                                </span>
                                <span v-if="chat.updated_at" class="block truncate text-[11px] text-muted-foreground">
                                    {{ formatDate(chat.updated_at) }}
                                </span>
                            </button>

                            <button
                                type="button"
                                :aria-label="t('delete')"
                                class="me-1 rounded p-1.5 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100 hover:text-destructive focus-visible:opacity-100 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                @click="deleteChat(chat.id)"
                            >
                                <Trash2 class="size-3.5" />
                            </button>
                        </div>
                    </nav>
                </aside>

                <div
                    v-if="railOpen"
                    class="fixed inset-0 z-40 bg-black/40 backdrop-blur-sm lg:hidden"
                    aria-hidden="true"
                    @click="railOpen = false"
                ></div>

                <!-- Conversation -->
                <div class="flex min-w-0 flex-1 flex-col rounded-3xl border bg-card">
                    <div class="flex items-center gap-2 border-b px-6 py-3">
                        <MessageSquare class="size-4 shrink-0 text-muted-foreground" />
                        <p class="truncate text-sm font-medium text-foreground">{{ activeTitle }}</p>
                    </div>

                    <div ref="log" class="max-h-[55vh] min-h-[320px] flex-1 space-y-5 overflow-y-auto p-6 scroll-shadows">
                        <div v-if="loadingChat" class="py-10 text-center text-sm text-muted-foreground">
                            <Loader2 class="mx-auto mb-2 size-5 animate-spin" />
                            {{ t('loading') }}
                        </div>

                        <div v-else-if="turns.length === 0" class="py-12 text-center">
                            <Bot class="mx-auto mb-3 size-8 text-muted-foreground" />
                            <p class="text-sm text-muted-foreground">{{ t('ai_empty') }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">{{ t('ai_empty_hint') }}</p>
                        </div>

                        <div v-for="(turn, index) in turns" :key="index" class="flex gap-3">
                            <div
                                class="flex size-8 shrink-0 items-center justify-center rounded-lg"
                                :class="turn.role === 'user' ? 'bg-muted' : 'bg-primary/10'"
                            >
                                <UserIcon v-if="turn.role === 'user'" class="size-4 text-muted-foreground" />
                                <Bot v-else class="size-4 text-primary" />
                            </div>

                            <div class="min-w-0 flex-1">
                                <p class="text-sm whitespace-pre-wrap" :class="turn.failed ? 'text-destructive' : 'text-foreground'">
                                    {{ turn.content
                                    }}<span
                                        v-if="turn.streaming"
                                        class="ms-0.5 inline-block h-4 w-px animate-pulse bg-foreground align-text-bottom"
                                    ></span>
                                </p>

                                <p v-if="turn.tools?.length" class="mt-1.5 flex items-center gap-1.5 text-xs text-muted-foreground">
                                    <Wrench class="size-3" />
                                    {{ turn.tools.map(toolLabel).join(', ') }}
                                </p>

                                <!-- Nothing has happened yet. This button is the
                                     only thing that writes. -->
                                <div v-if="turn.pending" class="mt-3 rounded-xl border border-warning/40 bg-warning/5 p-3">
                                    <p class="flex items-start gap-2 text-sm font-medium text-foreground">
                                        <TriangleAlert class="mt-0.5 size-4 shrink-0 text-warning" />
                                        {{ turn.pending.summary }}
                                    </p>
                                    <p class="mt-1 ps-6 text-xs text-muted-foreground">{{ t('ai_confirm_hint') }}</p>

                                    <div class="mt-3 flex gap-2 ps-6">
                                        <Button type="button" size="sm" :disabled="confirming !== null" @click="confirmAction(turn)">
                                            <Loader2 v-if="confirming === turn.pending.message_id" class="size-4 animate-spin" />
                                            <Check v-else class="size-4" />
                                            {{ t('ai_confirm') }}
                                        </Button>
                                        <Button type="button" variant="outline" size="sm" @click="dismissAction(turn)">
                                            <X class="size-4" />
                                            {{ t('cancel') }}
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Only until the first token or tool lands: after
                             that the answer itself is the progress. -->
                        <div v-if="sending && !streamStarted" class="flex items-center gap-3 text-sm text-muted-foreground">
                            <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                                <Loader2 class="size-4 animate-spin text-primary" />
                            </div>
                            {{ t('ai_thinking') }}
                        </div>
                    </div>

                    <form class="flex items-center gap-2 border-t p-4" @submit.prevent="send">
                        <input
                            v-model="draft"
                            type="text"
                            :placeholder="t('ai_placeholder')"
                            :aria-label="t('ai_placeholder')"
                            :disabled="sending || !backendReady"
                            maxlength="2000"
                            class="h-10 min-w-0 flex-1 rounded-lg border bg-background px-3 text-sm text-foreground placeholder:text-muted-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none disabled:opacity-50"
                        />
                        <Button type="submit" :disabled="sending || !backendReady || draft.trim() === ''">
                            <Loader2 v-if="sending" class="size-4 animate-spin" />
                            <Send v-else class="size-4" />
                            <span class="hidden sm:inline">{{ t('send') }}</span>
                        </Button>
                    </form>
                </div>
            </div>

            <p class="text-xs text-muted-foreground">{{ t('ai_readonly_note', { backend }) }}</p>
        </div>
    </div>
</template>
