<script setup lang="ts">
import { computed } from 'vue';
import { PlusIcon } from '@lucide/vue';
import { Button } from '@/components/ui/button';

/**
 * The actions for the items chosen from one collection. It sits where the
 * Library's selection bar does, so choosing several items and acting on them
 * feels the same everywhere.
 */
const props = withDefaults(
    defineProps<{
        selectedCount: number;
        /** True while an add is on its way. */
        busy?: boolean;
        /** True while "Select all" is asking the server for every match. */
        selectingAll?: boolean;
        /** A calm explanation after a failure; the selection is kept. */
        notice?: string;
    }>(),
    {
        busy: false,
        selectingAll: false,
        notice: '',
    },
);

const emit = defineEmits<{
    (event: 'cancel'): void;
    (event: 'select-all'): void;
    (event: 'add'): void;
}>();

const countLabel = computed(() =>
    props.selectedCount === 1
        ? '1 item selected'
        : `${props.selectedCount} items selected`,
);

const canAdd = computed(() => props.selectedCount > 0 && !props.busy);
</script>

<template>
    <section
        aria-label="Basket actions"
        class="fixed inset-x-0 bottom-0 z-40 border-t bg-background/95 pb-[env(safe-area-inset-bottom)] backdrop-blur md:bottom-4 md:left-1/2 md:max-w-3xl md:-translate-x-1/2 md:rounded-xl md:border md:shadow-lg"
    >
        <div class="mx-auto flex max-w-3xl flex-col gap-2 p-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm font-medium">{{ countLabel }}</p>

                <div class="flex items-center gap-1">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="min-h-11 md:min-h-9"
                        :disabled="props.busy || props.selectingAll"
                        @click="emit('select-all')"
                    >
                        {{ props.selectingAll ? 'Selecting…' : 'Select all' }}
                    </Button>

                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="min-h-11 md:min-h-9"
                        :disabled="props.busy"
                        @click="emit('cancel')"
                    >
                        Cancel
                    </Button>
                </div>
            </div>

            <p
                v-if="props.notice !== ''"
                role="alert"
                class="text-sm text-muted-foreground"
            >
                {{ props.notice }}
            </p>

            <p v-else class="text-xs text-muted-foreground">
                The chosen items join the basket; nothing else changes.
            </p>

            <Button
                type="button"
                class="min-h-11 md:min-h-9"
                :disabled="!canAdd"
                @click="emit('add')"
            >
                <PlusIcon aria-hidden="true" />
                {{
                    props.busy
                        ? 'Adding…'
                        : props.notice !== ''
                          ? 'Try again'
                          : 'Add to review'
                }}
            </Button>
        </div>
    </section>
</template>
