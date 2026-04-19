<script setup>
import Default from '@/layouts/default.vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ref, computed, watch } from 'vue';
import {
    Search,
    ChevronDown,
    ChevronRight,
    Rocket,
    Shield,
    Sparkles,
    Code,
    Puzzle,
    Layout,
    Radio,
    Upload,
    BookOpen,
    X,
} from 'lucide-vue-next';
import Input from '@/components/ui/input/Input.vue';

defineOptions({
    layout: Default,
});

const props = defineProps({
    sections: Array,
    currentSection: String,
    content: Object,
});

const { t } = useI18n();

const searchQuery = ref('');
const expandedSections = ref([props.currentSection?.split('-')[0] || 'getting-started']);

const iconMap = {
    Rocket,
    Shield,
    Sparkles,
    Code,
    Puzzle,
    Layout,
    Radio,
    Upload,
};

const toggleSection = (sectionId) => {
    const index = expandedSections.value.indexOf(sectionId);
    if (index > -1) {
        expandedSections.value.splice(index, 1);
    } else {
        expandedSections.value.push(sectionId);
    }
};

const navigateTo = (sectionId) => {
    router.get(route('docs'), { section: sectionId }, {
        preserveState: true,
        preserveScroll: false,
    });
};

// Filter sections based on search
const filteredSections = computed(() => {
    if (!searchQuery.value.trim()) {
        return props.sections;
    }

    const query = searchQuery.value.toLowerCase();
    return props.sections.map(section => {
        const filteredItems = section.items.filter(item =>
            item.title.toLowerCase().includes(query) ||
            section.title.toLowerCase().includes(query)
        );

        if (filteredItems.length > 0 || section.title.toLowerCase().includes(query)) {
            return {
                ...section,
                items: filteredItems.length > 0 ? filteredItems : section.items,
            };
        }
        return null;
    }).filter(Boolean);
});

// Expand sections when searching
watch(searchQuery, (newVal) => {
    if (newVal.trim()) {
        expandedSections.value = filteredSections.value.map(s => s.id);
    }
});

// Escape HTML entities
const escapeHtml = (str) => {
    return str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
};

// Convert markdown to HTML
const renderedContent = computed(() => {
    if (!props.content?.content) return '';

    let html = props.content.content;

    // First, extract and escape code blocks to prevent HTML interpretation
    const codeBlocks = [];
    html = html.replace(/```(\w+)?\n([\s\S]*?)```/g, (match, lang, code) => {
        const index = codeBlocks.length;
        codeBlocks.push(`<pre class="bg-muted rounded-lg p-4 my-4 overflow-x-auto text-sm font-mono"><code class="text-foreground">${escapeHtml(code.trim())}</code></pre>`);
        return `__CODE_BLOCK_${index}__`;
    });

    // Extract inline code
    const inlineCodes = [];
    html = html.replace(/`([^`]+)`/g, (match, code) => {
        const index = inlineCodes.length;
        inlineCodes.push(`<code class="bg-muted px-1.5 py-0.5 rounded text-sm font-mono text-foreground">${escapeHtml(code)}</code>`);
        return `__INLINE_CODE_${index}__`;
    });

    // Headers
    html = html.replace(/^### (.*$)/gim, '<h3 class="text-lg font-semibold mt-6 mb-3 text-foreground">$1</h3>');
    html = html.replace(/^## (.*$)/gim, '<h2 class="text-xl font-bold mt-8 mb-4 text-foreground">$1</h2>');
    html = html.replace(/^# (.*$)/gim, '<h1 class="text-2xl font-bold mt-8 mb-4 text-foreground">$1</h1>');

    // Bold
    html = html.replace(/\*\*([^*]+)\*\*/g, '<strong class="font-semibold">$1</strong>');

    // Tables - process line by line
    const lines = html.split('\n');
    let inTable = false;
    let tableHtml = '';
    const processedLines = [];

    for (let i = 0; i < lines.length; i++) {
        const line = lines[i].trim();

        if (line.startsWith('|') && line.endsWith('|')) {
            const cells = line.slice(1, -1).split('|').map(cell => cell.trim());

            // Skip separator lines
            if (cells.every(cell => /^-+$/.test(cell) || /^:?-+:?$/.test(cell))) {
                continue;
            }

            if (!inTable) {
                inTable = true;
                tableHtml = '<div class="overflow-x-auto my-4"><table class="w-full border border-border rounded-lg overflow-hidden">';
                // First row is header
                tableHtml += '<thead><tr>';
                cells.forEach(cell => {
                    tableHtml += `<th class="px-4 py-2 text-start font-medium text-foreground bg-muted">${cell}</th>`;
                });
                tableHtml += '</tr></thead><tbody>';
            } else {
                tableHtml += '<tr>';
                cells.forEach(cell => {
                    tableHtml += `<td class="px-4 py-2 text-start text-muted-foreground border-t border-border">${cell}</td>`;
                });
                tableHtml += '</tr>';
            }
        } else {
            if (inTable) {
                tableHtml += '</tbody></table></div>';
                processedLines.push(tableHtml);
                inTable = false;
                tableHtml = '';
            }
            processedLines.push(line);
        }
    }

    if (inTable) {
        tableHtml += '</tbody></table></div>';
        processedLines.push(tableHtml);
    }

    html = processedLines.join('\n');

    // Lists
    html = html.replace(/^- (.*$)/gim, '<li class="ms-4 text-muted-foreground list-disc">$1</li>');
    html = html.replace(/^(\d+)\. (.*$)/gim, '<li class="ms-4 text-muted-foreground list-decimal">$2</li>');

    // Wrap consecutive list items
    html = html.replace(/(<li[^>]*>.*?<\/li>\n?)+/g, (match) => {
        if (match.includes('list-decimal')) {
            return `<ol class="my-4 space-y-1 ps-4">${match}</ol>`;
        }
        return `<ul class="my-4 space-y-1 ps-4">${match}</ul>`;
    });

    // Paragraphs (double newlines)
    html = html.replace(/\n\n+/g, '</p><p class="text-muted-foreground mb-4">');

    // Single line breaks within paragraphs
    html = html.replace(/\n/g, '<br>');

    // Restore code blocks
    codeBlocks.forEach((block, index) => {
        html = html.replace(`__CODE_BLOCK_${index}__`, block);
    });

    // Restore inline code
    inlineCodes.forEach((code, index) => {
        html = html.replace(`__INLINE_CODE_${index}__`, code);
    });

    // Clean up empty paragraphs and extra breaks around block elements
    html = html.replace(/<p[^>]*>\s*<\/p>/g, '');
    html = html.replace(/<br>\s*(<(pre|div|ul|ol|h[1-6]))/g, '$1');
    html = html.replace(/(<\/(pre|div|ul|ol|h[1-6])>)\s*<br>/g, '$1');

    return `<div class="prose-content"><p class="text-muted-foreground mb-4">${html}</p></div>`;
});

const clearSearch = () => {
    searchQuery.value = '';
};
</script>

<template>
    <Head :title="t('documentation')" />

    <div class="h-full min-h-dvh w-full bg-background">
        <div class="mx-auto flex w-full max-w-[1300px] gap-6 px-4 py-20 text-start">
            <!-- Sidebar -->
            <aside class="sticky top-20 h-fit w-64 shrink-0 rounded-2xl border bg-card p-3 hidden lg:block">
                <div class="flex items-center gap-3 px-3 py-2 mb-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-accent">
                        <BookOpen class="size-4 text-accent-foreground" />
                    </div>
                    <span class="font-semibold text-foreground text-sm">{{ t('documentation') }}</span>
                </div>

                <!-- Search -->
                <div class="relative mb-3 px-1">
                    <Search class="absolute start-4 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        v-model="searchQuery"
                        type="text"
                        :placeholder="t('search_docs')"
                        class="ps-9 pe-9 h-9 text-sm"
                    />
                    <button
                        v-if="searchQuery"
                        @click="clearSearch"
                        class="absolute end-4 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                    >
                        <X class="size-4" />
                    </button>
                </div>

                <!-- Navigation -->
                <nav class="space-y-1">
                    <template v-for="section in filteredSections" :key="section.id">
                        <button
                            @click="toggleSection(section.id)"
                            class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-sm transition-colors"
                            :class="expandedSections.includes(section.id)
                                ? 'bg-muted text-foreground'
                                : 'text-muted-foreground hover:bg-muted hover:text-foreground'"
                        >
                            <div class="flex items-center gap-3">
                                <component :is="iconMap[section.icon]" class="size-4" />
                                <span>{{ section.title }}</span>
                            </div>
                            <ChevronDown
                                v-if="expandedSections.includes(section.id)"
                                class="size-4"
                            />
                            <ChevronRight
                                v-else
                                class="size-4"
                            />
                        </button>

                        <div
                            v-if="expandedSections.includes(section.id)"
                            class="ms-3 space-y-1"
                        >
                            <button
                                v-for="item in section.items"
                                :key="item.id"
                                @click="navigateTo(item.id)"
                                class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm transition-colors"
                                :class="currentSection === item.id
                                    ? 'bg-primary text-primary-foreground'
                                    : 'text-muted-foreground hover:bg-muted hover:text-foreground'"
                            >
                                {{ item.title }}
                            </button>
                        </div>
                    </template>
                </nav>

                <!-- No results -->
                <div
                    v-if="filteredSections.length === 0"
                    class="py-8 text-center text-sm text-muted-foreground"
                >
                    {{ t('no_results_found') }}
                </div>
            </aside>

            <!-- Mobile Menu -->
            <div class="lg:hidden fixed bottom-4 start-4 end-4 z-50">
                <div class="rounded-2xl border bg-card/95 backdrop-blur-sm p-2 shadow-lg">
                    <div class="flex gap-1 overflow-x-auto">
                        <button
                            v-for="section in sections"
                            :key="section.id"
                            @click="toggleSection(section.id); if (section.items?.length) navigateTo(section.items[0].id)"
                            class="flex shrink-0 flex-col items-center gap-1 rounded-lg px-3 py-2 text-xs transition-colors"
                            :class="currentSection?.startsWith(section.id)
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground'"
                        >
                            <component :is="iconMap[section.icon]" class="size-4" />
                            <span class="whitespace-nowrap">{{ section.title }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 space-y-5 pb-20 lg:pb-0">
                <!-- Section Header -->
                <div class="flex items-center gap-3 rounded-2xl border bg-card p-4">
                    <BookOpen class="size-5 text-primary" />
                    <div>
                        <h1 class="text-lg font-semibold text-foreground">
                            {{ content?.title || t('documentation') }}
                        </h1>
                    </div>
                </div>

                <!-- Main Content Card -->
                <div class="rounded-3xl border border-border bg-card p-8">
                    <!-- Mobile Search -->
                    <div class="mb-6 lg:hidden">
                        <div class="relative">
                            <Search class="absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                v-model="searchQuery"
                                type="text"
                                :placeholder="t('search_docs')"
                                class="ps-9"
                            />
                        </div>
                    </div>

                    <!-- Content -->
                    <article class="prose prose-slate dark:prose-invert max-w-none">
                        <div v-html="renderedContent"></div>
                    </article>

                    <!-- Navigation Footer -->
                    <div class="mt-12 flex items-center justify-between border-t border-border pt-6">
                        <Link :href="route('dev_settings')" class="text-sm text-primary hover:underline">
                            ← {{ t('developer_settings') }}
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
