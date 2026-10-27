<script setup lang="ts">
import { computed } from 'vue';
import { ArchiveIcon, RotateCcwIcon, Trash2Icon } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    archiveAvailabilityLabel,
    selectionCountLabel,
} from '@/lib/session-selection';

const props = withDefaults(
    defineProps<{
        selectedCount: number;
        /** How many of the selected sessions are finished and can be archived. */
        finishedCount: number;
        archivedView?: boolean;
        /** True while an action is on its way. */
        busy?: boolean;
        /** True while "Select all" is asking the server for the whole result. */
        selectingAll?: boolean;
        /** A calm, persistent explanation, such as a list too large. */
        notice?: string;
    }>(),
    {
        archivedView: false,
        busy: false,
        selectingAll: false,
        notice: '',
    },
);

const emit = defineEmits<{
    (event: 'cancel'): void;
    (event: 'select-all'): void;
    (event: 'archive'): void;
    (event: 'restore'): void;
    (event: 'delete'): void;
}>();

const countLabel = computed(() => selectionCountLabel(props.selectedCount));

const availability = computed(() =>
    archiveAvailabilityLabel(props.selectedCount, props.finishedCount),
);

const canAct = computed(() => props.selectedCount > 0 && !props.busy);

const canArchive = computed(
    () => canAct.value && props.finishedCount > 0 && !props.archivedView,
);
</script>

<template>
    <section
        aria-label="Selection actions"
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
                class="text-sm text-destructive"
            >
                {{ props.notice }}
            </p>

            <p class="text-xs text-muted-foreground">
                {{ availability }}
            </p>

            <div class="grid grid-cols-2 gap-2">
                <Button
                    v-if="props.archivedView"
                    type="button"
                    variant="outline"
                    class="min-h-11 md:min-h-9"
                    :disabled="!canAct"
                    @click="emit('restore')"
                >
                    <RotateCcwIcon aria-hidden="true" />
                    {{ props.busy ? 'Restoring…' : 'Restore' }}
                </Button>

                <Button
                    v-else
                    type="button"
                    variant="outline"
                    class="min-h-11 md:min-h-9"
                    :disabled="!canArchive"
                    @click="emit('archive')"
                >
                    <ArchiveIcon aria-hidden="true" />
                    {{ props.busy ? 'Archiving…' : 'Archive' }}
                </Button>

                <Button
                    type="button"
                    variant="destructive"
                    class="min-h-11 md:min-h-9"
                    :disabled="!canAct"
                    @click="emit('delete')"
                >
                    <Trash2Icon aria-hidden="true" />
                    Delete
                </Button>
            </div>
        </div>
    </section>
</template>
