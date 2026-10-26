<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { SearchIcon, XIcon } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    MAX_SEARCH_LENGTH,
    MIN_SEARCH_LENGTH,
    normalizeSearch,
} from '@/lib/library-filters';

/**
 * The Library's search box. It waits for a short pause in typing before
 * searching, never searches on a single letter, and lets Escape clear the
 * words without taking the cursor away.
 */
const props = withDefaults(
    defineProps<{
        modelValue: string;
        /** True while a search is on its way; the box stays usable. */
        updating?: boolean;
        label?: string;
    }>(),
    {
        updating: false,
        label: 'Search your sessions',
    },
);

const emit = defineEmits<{
    (event: 'search', value: string): void;
}>();

/** A pause in typing before the search runs, in milliseconds. */
const DEBOUNCE_MS = 350;

const local = ref(props.modelValue);
const inputComponent = ref<{ $el?: unknown } | null>(null);
let timer: number | null = null;

/**
 * The native field inside the shadcn input. The component does not expose its
 * element, but it has a single root, so `$el` is the field focus moves to.
 */
function inputElement(): HTMLInputElement | null {
    const element = inputComponent.value?.$el;

    return element instanceof HTMLInputElement ? element : null;
}

defineExpose({
    focus(): void {
        inputElement()?.focus();
    },
});

const trimmed = computed(() => local.value.replace(/\s+/gu, ' ').trim());
const meaningfulLength = computed(
    () => trimmed.value.replace(/\s+/gu, '').length,
);

/** The one short hint shown under the box, when it is useful. */
const hint = computed(() => {
    if (meaningfulLength.value === 0) {
        return '';
    }

    if (meaningfulLength.value < MIN_SEARCH_LENGTH) {
        return `Type at least ${MIN_SEARCH_LENGTH} characters to search.`;
    }

    if (trimmed.value.length >= MAX_SEARCH_LENGTH) {
        return `Search is limited to ${MAX_SEARCH_LENGTH} characters.`;
    }

    return '';
});

// A clear from elsewhere (the empty state, or a restored list) reaches the
// box while it is not being typed into.
watch(
    () => props.modelValue,
    (value) => {
        if (
            typeof document !== 'undefined' &&
            document.activeElement === inputElement()
        ) {
            return;
        }

        local.value = value;
    },
);

function schedule(): void {
    clearTimer();
    timer = window.setTimeout(flush, DEBOUNCE_MS);
}

function flush(): void {
    clearTimer();
    emit('search', normalizeSearch(local.value));
}

/** Empty the search and leave the cursor where it is, ready to type again. */
function clear(): void {
    clearTimer();
    local.value = '';
    emit('search', '');

    void nextTick(() => inputElement()?.focus());
}

function clearTimer(): void {
    if (timer !== null) {
        window.clearTimeout(timer);
        timer = null;
    }
}

onBeforeUnmount(clearTimer);
</script>

<template>
    <div class="mb-3">
        <label :for="'library-search'" class="sr-only">{{ label }}</label>

        <div class="relative">
            <SearchIcon
                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                aria-hidden="true"
            />

            <Input
                id="library-search"
                ref="inputComponent"
                :model-value="local"
                type="search"
                :maxlength="MAX_SEARCH_LENGTH"
                autocomplete="off"
                spellcheck="false"
                :placeholder="label"
                class="h-11 pr-10 pl-9"
                :aria-describedby="
                    hint === '' ? undefined : 'library-search-hint'
                "
                @update:model-value="
                    local = String($event);
                    schedule();
                "
                @keydown.esc.prevent="clear"
                @keydown.enter.prevent="flush"
            />

            <Button
                v-if="local !== ''"
                type="button"
                variant="ghost"
                size="icon-sm"
                class="absolute top-1/2 right-1 -translate-y-1/2"
                aria-label="Clear search"
                @click="clear"
            >
                <XIcon aria-hidden="true" />
            </Button>
        </div>

        <p
            v-if="hint !== ''"
            id="library-search-hint"
            role="status"
            class="mt-1.5 text-xs text-muted-foreground"
        >
            {{ hint }}
        </p>
    </div>
</template>
