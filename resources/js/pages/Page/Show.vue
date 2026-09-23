<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import Button from '@/components/ui/button/Button.vue';

interface PublicPage {
    name: string;
    content: string;
    image?: string | null;
}

defineProps<{
    page: PublicPage;
}>();

const inertiaPage = usePage();
const currentLocale = computed(() => inertiaPage.props.locale ?? { code: 'en', dir: 'ltr' as const });

const localeForm = useForm({ locale: '' });

const changeLocale = () => {
    const target = currentLocale.value.code === 'ar' ? 'en' : 'ar';
    localeForm.locale = target;
    localeForm.post(route('locale.post'), { preserveScroll: true });
};
</script>

<template>
    <Head :title="page.name" />

    <div :dir="currentLocale.dir" class="flex min-h-screen flex-col bg-background font-[Cairo]">
        <main class="mx-auto w-full max-w-3xl flex-1 px-4 py-12 text-start md:py-20">
            <article class="flex flex-col gap-6">
                <header class="flex flex-col gap-4">
                    <h1 class="text-3xl font-bold tracking-tight text-foreground md:text-4xl">
                        {{ page.name }}
                    </h1>
                    <img
                        v-if="page.image"
                        :src="page.image"
                        :alt="page.name"
                        class="aspect-video w-full rounded-2xl border border-border bg-muted object-cover"
                    />
                </header>

                <!--
                    v-html is safe here: page content is admin-authored HTML that is sanitised
                    server-side on write in App\Http\Controllers\Admin\Page\PageController before
                    it is ever persisted, so nothing untrusted reaches this directive.
                -->
                <div
                    class="prose prose-neutral rtl:prose-headings:text-end rtl:prose-p:text-end dark:prose-invert max-w-none text-foreground"
                    v-html="page.content"
                ></div>
            </article>
        </main>

        <!-- Floating Locale Switcher for quick language access -->
        <div class="fixed end-6 bottom-6 z-50">
            <Button @click="changeLocale">
                {{ currentLocale.code === 'ar' ? 'English' : 'عربي' }}
            </Button>
        </div>
    </div>
</template>
