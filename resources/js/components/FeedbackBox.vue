<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { ChevronDownIcon, ChevronUpIcon } from '@lucide/vue';
import RichTextEditor from '@/components/RichTextEditor.vue';
import SectionLabel from '@/components/SectionLabel.vue';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { focusElementById } from '@/lib/dom';
import { feedbackBoxFor } from '@/lib/feedback';
import { isRichTextBlank } from '@/lib/rich-text';
import type { FeedbackType } from '@/types';

/**
 * One labelled, colour-tagged feedback box. While it is empty it stays a slim
 * row; it opens on tap or keyboard, and closes again when it loses focus
 * without any content. A box with content always stays open.
 */
const props = defineProps<{
    type: FeedbackType;
    modelValue: string;
    /** Unique id for the input, so focus can be moved to it. */
    fieldId: string;
    /** Question this box belongs to, for its accessible name. */
    questionNumber: number;
    error?: string;
}>();

const emit = defineEmits<{
    (event: 'update:modelValue', value: string): void;
}>();

const box = feedbackBoxFor(props.type);

const isBlank = computed(() =>
    box.rich
        ? isRichTextBlank(props.modelValue)
        : props.modelValue.trim().length === 0,
);

const expanded = ref(isBlank.value === false || Boolean(props.error));

watch(isBlank, (blank) => {
    if (!blank) {
        expanded.value = true;
    }
});

watch(
    () => props.error,
    (error) => {
        if (error) {
            expanded.value = true;
        }
    },
);

const wrapper = ref<HTMLElement | null>(null);

const accessibleLabel = computed(
    () => `${box.label} for question ${props.questionNumber}`,
);

function open(): void {
    expanded.value = true;
    console.log('lakshan', expanded.value);
    focusElementById(props.fieldId);
}

function collapse(): void {
    if (isBlank.value) {
        expanded.value = false;
    }
}

function handleFocusOut(event: FocusEvent): void {
    const next = event.relatedTarget as Node | null;

    if (next && wrapper.value?.contains(next)) {
        return;
    }

    collapse();
}
</script>

<template>
    <div
        ref="wrapper"
        class="rounded-lg border transition-colors"
        :class="
            expanded
                ? 'border-border bg-card'
                : 'border-dashed bg-muted/20 hover:bg-muted/40'
        "
        @focusout="handleFocusOut"
    >
        <button
            v-if="!expanded"
            type="button"
            class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
            :aria-expanded="false"
            @click="open"
        >
            <SectionLabel :tone="box.tone">{{ box.label }}</SectionLabel>
            <span class="truncate text-xs text-muted-foreground">{{
                box.hint
            }}</span>
            <ChevronDownIcon
                class="ml-auto size-4 shrink-0 text-muted-foreground"
                aria-hidden="true"
            />
        </button>

        <template v-else>
            <div class="flex flex-wrap items-center gap-2 px-3 pt-3">
                <SectionLabel :tone="box.tone">{{ box.label }}</SectionLabel>

                <Button
                    v-if="isBlank"
                    type="button"
                    variant="ghost"
                    size="icon-xs"
                    class="ml-auto"
                    :aria-label="`Close ${accessibleLabel}`"
                    :aria-expanded="true"
                    @click="collapse"
                >
                    <ChevronUpIcon aria-hidden="true" />
                </Button>
            </div>

            <div class="p-3 pt-2">
                <Textarea
                    v-if="!box.rich"
                    :id="props.fieldId"
                    :model-value="props.modelValue"
                    :aria-label="accessibleLabel"
                    :aria-invalid="Boolean(props.error)"
                    :placeholder="box.placeholder"
                    class="min-h-20"
                    @update:model-value="
                        emit('update:modelValue', String($event))
                    "
                />

                <RichTextEditor
                    v-else
                    :id="props.fieldId"
                    :model-value="props.modelValue"
                    :label="accessibleLabel"
                    :placeholder="box.placeholder"
                    @update:model-value="emit('update:modelValue', $event)"
                />

                <p
                    v-if="props.error"
                    role="alert"
                    class="mt-1.5 text-xs font-medium text-destructive"
                >
                    {{ props.error }}
                </p>
            </div>
        </template>
    </div>
</template>
