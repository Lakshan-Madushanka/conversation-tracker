<script setup lang="ts">
import { reactive, watch } from 'vue';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import { Placeholder } from '@tiptap/extensions';
import { BoldIcon, ItalicIcon, ListIcon, ListOrderedIcon } from '@lucide/vue';
import { Button } from '@/components/ui/button';

/**
 * The one rich text box used across the app. It supports bold, italic,
 * bulleted and numbered lists, and line breaks — nothing else — and pasted
 * content is reduced to that set by the editor schema.
 */
const props = withDefaults(
    defineProps<{
        modelValue: string;
        /** Used for the toolbar label and for screen readers. */
        label: string;
        id?: string;
        placeholder?: string;
    }>(),
    { id: undefined, placeholder: '' },
);

const emit = defineEmits<{
    (event: 'update:modelValue', value: string): void;
}>();

/** Tiptap does not re-render on its own, so the toolbar reflects state here. */
const active = reactive({
    bold: false,
    italic: false,
    bulletList: false,
    orderedList: false,
});

const editor = useEditor({
    extensions: [
        StarterKit.configure({
            // Nothing outside the small supported formatting set.
            blockquote: false,
            code: false,
            codeBlock: false,
            heading: false,
            horizontalRule: false,
            link: false,
            strike: false,
            underline: false,
        }),
        Placeholder.configure({
            placeholder: () => props.placeholder,
        }),
    ],
    content: props.modelValue,
    editorProps: {
        attributes: {
            id: props.id ?? '',
            'aria-label': props.label,
            'aria-multiline': 'true',
            class: 'min-h-28 px-3 py-2 text-sm outline-none',
        },
    },
    onCreate: () => syncActiveFormats(),
    onSelectionUpdate: () => syncActiveFormats(),
    onTransaction: () => syncActiveFormats(),
    onUpdate: ({ editor }) => emit('update:modelValue', editor.getHTML()),
});

function syncActiveFormats(): void {
    const instance = editor.value;

    if (!instance) {
        return;
    }

    active.bold = instance.isActive('bold');
    active.italic = instance.isActive('italic');
    active.bulletList = instance.isActive('bulletList');
    active.orderedList = instance.isActive('orderedList');
}

watch(
    () => props.modelValue,
    (value) => {
        const instance = editor.value;

        if (!instance || value === instance.getHTML()) {
            return;
        }

        instance.commands.setContent(value, { emitUpdate: false });
    },
);

function toggleBold(): void {
    editor.value?.chain().focus().toggleBold().run();
}

function toggleItalic(): void {
    editor.value?.chain().focus().toggleItalic().run();
}

function toggleBulletList(): void {
    editor.value?.chain().focus().toggleBulletList().run();
}

function toggleOrderedList(): void {
    editor.value?.chain().focus().toggleOrderedList().run();
}
</script>

<template>
    <div
        class="rich-text-editor overflow-hidden rounded-lg border border-input bg-transparent transition-colors focus-within:border-ring focus-within:ring-3 focus-within:ring-ring/50 dark:bg-input/30"
    >
        <div
            role="toolbar"
            :aria-label="`${label} formatting`"
            class="flex flex-wrap items-center gap-0.5 border-b border-input/60 bg-muted/40 p-1"
        >
            <Button
                type="button"
                :variant="active.bold ? 'secondary' : 'ghost'"
                size="icon"
                class="size-9"
                :disabled="!editor"
                aria-label="Bold"
                :aria-pressed="active.bold"
                @mousedown.prevent="toggleBold"
            >
                <BoldIcon aria-hidden="true" />
            </Button>

            <Button
                type="button"
                :variant="active.italic ? 'secondary' : 'ghost'"
                size="icon"
                class="size-9"
                :disabled="!editor"
                aria-label="Italic"
                :aria-pressed="active.italic"
                @mousedown.prevent="toggleItalic"
            >
                <ItalicIcon aria-hidden="true" />
            </Button>

            <Button
                type="button"
                :variant="active.bulletList ? 'secondary' : 'ghost'"
                size="icon"
                class="size-9"
                :disabled="!editor"
                aria-label="Bulleted list"
                :aria-pressed="active.bulletList"
                @mousedown.prevent="toggleBulletList"
            >
                <ListIcon aria-hidden="true" />
            </Button>

            <Button
                type="button"
                :variant="active.orderedList ? 'secondary' : 'ghost'"
                size="icon"
                class="size-9"
                :disabled="!editor"
                aria-label="Numbered list"
                :aria-pressed="active.orderedList"
                @mousedown.prevent="toggleOrderedList"
            >
                <ListOrderedIcon aria-hidden="true" />
            </Button>
        </div>

        <EditorContent
            :editor="editor"
            class="[&_.ProseMirror]:break-words [&_li]:my-0.5 [&_ol]:my-1 [&_ol]:list-decimal [&_ol]:pl-5 [&_p]:my-1 [&_p:first-child]:mt-0 [&_p:last-child]:mb-0 [&_ul]:my-1 [&_ul]:list-disc [&_ul]:pl-5"
        />
    </div>
</template>

<style>
.rich-text-editor .tiptap p.is-editor-empty:first-child::before {
    content: attr(data-placeholder);
    color: var(--muted-foreground);
    float: left;
    height: 0;
    pointer-events: none;
}
</style>
