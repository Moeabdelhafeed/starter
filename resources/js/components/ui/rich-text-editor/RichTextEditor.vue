<script setup lang="ts">
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import TextAlign from '@tiptap/extension-text-align';
import Underline from '@tiptap/extension-underline';
import Placeholder from '@tiptap/extension-placeholder';
import { watch } from 'vue';
import {
    Bold,
    Italic,
    Underline as UnderlineIcon,
    Strikethrough,
    List,
    ListOrdered,
    AlignLeft,
    AlignCenter,
    AlignRight,
    Link as LinkIcon,
    Unlink,
    Undo,
    Redo,
    Heading1,
    Heading2,
    Heading3,
    Quote,
    Minus,
} from 'lucide-vue-next';

const props = withDefaults(defineProps<{
    modelValue: string;
    placeholder?: string;
    direction?: 'ltr' | 'rtl';
}>(), {
    modelValue: '',
    placeholder: '',
    direction: 'ltr',
});

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit.configure({
            heading: {
                levels: [1, 2, 3],
            },
        }),
        Link.configure({
            openOnClick: false,
            HTMLAttributes: {
                class: 'text-primary underline',
            },
        }),
        TextAlign.configure({
            types: ['heading', 'paragraph'],
        }),
        Underline,
        Placeholder.configure({
            placeholder: props.placeholder,
        }),
    ],
    editorProps: {
        attributes: {
            class: 'prose prose-sm dark:prose-invert max-w-none focus:outline-none min-h-[200px] px-4 py-3',
            dir: props.direction,
        },
    },
    onUpdate: ({ editor }) => {
        emit('update:modelValue', editor.getHTML());
    },
});

watch(() => props.modelValue, (value) => {
    if (editor.value && editor.value.getHTML() !== value) {
        editor.value.commands.setContent(value || '');
    }
});

watch(() => props.direction, (dir) => {
    if (editor.value) {
        editor.value.setOptions({
            editorProps: {
                attributes: {
                    class: 'prose prose-sm dark:prose-invert max-w-none focus:outline-none min-h-[200px] px-4 py-3',
                    dir: dir,
                },
            },
        });
    }
});

const setLink = () => {
    const previousUrl = editor.value?.getAttributes('link').href;
    const url = window.prompt('URL', previousUrl);

    if (url === null) {
        return;
    }

    if (url === '') {
        editor.value?.chain().focus().extendMarkRange('link').unsetLink().run();
        return;
    }

    editor.value?.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
};
</script>

<template>
    <div class="rich-text-editor rounded-lg border border-input bg-background overflow-hidden">
        <!-- Toolbar -->
        <div v-if="editor" class="flex flex-wrap items-center gap-1 border-b border-border bg-muted/30 p-2">
            <!-- Text formatting -->
            <button
                type="button"
                @click="editor.chain().focus().toggleBold().run()"
                :class="{ 'bg-primary/20 text-primary': editor.isActive('bold') }"
                class="rounded p-1.5 hover:bg-muted transition-colors"
                :title="'Bold'"
            >
                <Bold class="h-4 w-4" />
            </button>
            <button
                type="button"
                @click="editor.chain().focus().toggleItalic().run()"
                :class="{ 'bg-primary/20 text-primary': editor.isActive('italic') }"
                class="rounded p-1.5 hover:bg-muted transition-colors"
                :title="'Italic'"
            >
                <Italic class="h-4 w-4" />
            </button>
            <button
                type="button"
                @click="editor.chain().focus().toggleUnderline().run()"
                :class="{ 'bg-primary/20 text-primary': editor.isActive('underline') }"
                class="rounded p-1.5 hover:bg-muted transition-colors"
                :title="'Underline'"
            >
                <UnderlineIcon class="h-4 w-4" />
            </button>
            <button
                type="button"
                @click="editor.chain().focus().toggleStrike().run()"
                :class="{ 'bg-primary/20 text-primary': editor.isActive('strike') }"
                class="rounded p-1.5 hover:bg-muted transition-colors"
                :title="'Strikethrough'"
            >
                <Strikethrough class="h-4 w-4" />
            </button>

            <div class="mx-1 h-6 w-px bg-border"></div>

            <!-- Headings -->
            <button
                type="button"
                @click="editor.chain().focus().toggleHeading({ level: 1 }).run()"
                :class="{ 'bg-primary/20 text-primary': editor.isActive('heading', { level: 1 }) }"
                class="rounded p-1.5 hover:bg-muted transition-colors"
                :title="'Heading 1'"
            >
                <Heading1 class="h-4 w-4" />
            </button>
            <button
                type="button"
                @click="editor.chain().focus().toggleHeading({ level: 2 }).run()"
                :class="{ 'bg-primary/20 text-primary': editor.isActive('heading', { level: 2 }) }"
                class="rounded p-1.5 hover:bg-muted transition-colors"
                :title="'Heading 2'"
            >
                <Heading2 class="h-4 w-4" />
            </button>
            <button
                type="button"
                @click="editor.chain().focus().toggleHeading({ level: 3 }).run()"
                :class="{ 'bg-primary/20 text-primary': editor.isActive('heading', { level: 3 }) }"
                class="rounded p-1.5 hover:bg-muted transition-colors"
                :title="'Heading 3'"
            >
                <Heading3 class="h-4 w-4" />
            </button>

            <div class="mx-1 h-6 w-px bg-border"></div>

            <!-- Lists -->
            <button
                type="button"
                @click="editor.chain().focus().toggleBulletList().run()"
                :class="{ 'bg-primary/20 text-primary': editor.isActive('bulletList') }"
                class="rounded p-1.5 hover:bg-muted transition-colors"
                :title="'Bullet List'"
            >
                <List class="h-4 w-4" />
            </button>
            <button
                type="button"
                @click="editor.chain().focus().toggleOrderedList().run()"
                :class="{ 'bg-primary/20 text-primary': editor.isActive('orderedList') }"
                class="rounded p-1.5 hover:bg-muted transition-colors"
                :title="'Numbered List'"
            >
                <ListOrdered class="h-4 w-4" />
            </button>

            <div class="mx-1 h-6 w-px bg-border"></div>

            <!-- Alignment -->
            <button
                type="button"
                @click="editor.chain().focus().setTextAlign('left').run()"
                :class="{ 'bg-primary/20 text-primary': editor.isActive({ textAlign: 'left' }) }"
                class="rounded p-1.5 hover:bg-muted transition-colors"
                :title="'Align Left'"
            >
                <AlignLeft class="h-4 w-4" />
            </button>
            <button
                type="button"
                @click="editor.chain().focus().setTextAlign('center').run()"
                :class="{ 'bg-primary/20 text-primary': editor.isActive({ textAlign: 'center' }) }"
                class="rounded p-1.5 hover:bg-muted transition-colors"
                :title="'Align Center'"
            >
                <AlignCenter class="h-4 w-4" />
            </button>
            <button
                type="button"
                @click="editor.chain().focus().setTextAlign('right').run()"
                :class="{ 'bg-primary/20 text-primary': editor.isActive({ textAlign: 'right' }) }"
                class="rounded p-1.5 hover:bg-muted transition-colors"
                :title="'Align Right'"
            >
                <AlignRight class="h-4 w-4" />
            </button>

            <div class="mx-1 h-6 w-px bg-border"></div>

            <!-- Blockquote & HR -->
            <button
                type="button"
                @click="editor.chain().focus().toggleBlockquote().run()"
                :class="{ 'bg-primary/20 text-primary': editor.isActive('blockquote') }"
                class="rounded p-1.5 hover:bg-muted transition-colors"
                :title="'Quote'"
            >
                <Quote class="h-4 w-4" />
            </button>
            <button
                type="button"
                @click="editor.chain().focus().setHorizontalRule().run()"
                class="rounded p-1.5 hover:bg-muted transition-colors"
                :title="'Horizontal Rule'"
            >
                <Minus class="h-4 w-4" />
            </button>

            <div class="mx-1 h-6 w-px bg-border"></div>

            <!-- Link -->
            <button
                type="button"
                @click="setLink"
                :class="{ 'bg-primary/20 text-primary': editor.isActive('link') }"
                class="rounded p-1.5 hover:bg-muted transition-colors"
                :title="'Add Link'"
            >
                <LinkIcon class="h-4 w-4" />
            </button>
            <button
                v-if="editor.isActive('link')"
                type="button"
                @click="editor.chain().focus().unsetLink().run()"
                class="rounded p-1.5 hover:bg-muted transition-colors"
                :title="'Remove Link'"
            >
                <Unlink class="h-4 w-4" />
            </button>

            <div class="mx-1 h-6 w-px bg-border"></div>

            <!-- Undo/Redo -->
            <button
                type="button"
                @click="editor.chain().focus().undo().run()"
                :disabled="!editor.can().undo()"
                class="rounded p-1.5 hover:bg-muted transition-colors disabled:opacity-30"
                :title="'Undo'"
            >
                <Undo class="h-4 w-4" />
            </button>
            <button
                type="button"
                @click="editor.chain().focus().redo().run()"
                :disabled="!editor.can().redo()"
                class="rounded p-1.5 hover:bg-muted transition-colors disabled:opacity-30"
                :title="'Redo'"
            >
                <Redo class="h-4 w-4" />
            </button>
        </div>

        <!-- Editor Content -->
        <EditorContent :editor="editor" class="editor-content" />
    </div>
</template>

<style>
.rich-text-editor .ProseMirror {
    min-height: 200px;
    padding: 1rem;
    outline: none;
    color: var(--color-foreground);
}

.rich-text-editor .ProseMirror p.is-editor-empty:first-child::before {
    color: var(--color-muted-foreground);
    content: attr(data-placeholder);
    float: left;
    height: 0;
    pointer-events: none;
}

/* Prose styles for dark mode */
.rich-text-editor .ProseMirror {
    color: var(--color-foreground);
}

.rich-text-editor .ProseMirror h1,
.rich-text-editor .ProseMirror h2,
.rich-text-editor .ProseMirror h3,
.rich-text-editor .ProseMirror h4,
.rich-text-editor .ProseMirror h5,
.rich-text-editor .ProseMirror h6 {
    color: var(--color-foreground);
    font-weight: 600;
    line-height: 1.3;
    margin-top: 1.5rem;
    margin-bottom: 0.75rem;
}

.rich-text-editor .ProseMirror h1 {
    font-size: 2rem;
}

.rich-text-editor .ProseMirror h2 {
    font-size: 1.5rem;
}

.rich-text-editor .ProseMirror h3 {
    font-size: 1.25rem;
}

.rich-text-editor .ProseMirror p {
    margin-bottom: 0.75rem;
}

.rich-text-editor .ProseMirror ul,
.rich-text-editor .ProseMirror ol {
    padding-inline-start: 1.5rem;
    margin-bottom: 0.75rem;
}

.rich-text-editor .ProseMirror ul {
    list-style-type: disc;
}

.rich-text-editor .ProseMirror ol {
    list-style-type: decimal;
}

.rich-text-editor .ProseMirror li {
    margin-bottom: 0.25rem;
}

.rich-text-editor .ProseMirror blockquote {
    border-inline-start: 3px solid var(--color-border);
    padding-inline-start: 1rem;
    margin-inline-start: 0;
    margin-inline-end: 0;
    color: var(--color-muted-foreground);
    font-style: italic;
}

.rich-text-editor .ProseMirror hr {
    border: none;
    border-top: 1px solid var(--color-border);
    margin: 1.5rem 0;
}

.rich-text-editor .ProseMirror a {
    color: var(--color-primary);
    text-decoration: underline;
}

.rich-text-editor .ProseMirror strong {
    font-weight: 600;
}

.rich-text-editor .ProseMirror em {
    font-style: italic;
}

.rich-text-editor .ProseMirror s {
    text-decoration: line-through;
}

.rich-text-editor .ProseMirror u {
    text-decoration: underline;
}

/* Code blocks */
.rich-text-editor .ProseMirror code {
    background-color: var(--color-muted);
    padding: 0.2rem 0.4rem;
    border-radius: 0.25rem;
    font-family: monospace;
    font-size: 0.875em;
}

.rich-text-editor .ProseMirror pre {
    background-color: var(--color-muted);
    padding: 1rem;
    border-radius: 0.5rem;
    overflow-x: auto;
    margin-bottom: 0.75rem;
}

.rich-text-editor .ProseMirror pre code {
    background: none;
    padding: 0;
}
</style>
