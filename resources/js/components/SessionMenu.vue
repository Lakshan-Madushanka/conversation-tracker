<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import {
    ArchiveIcon,
    ArchiveRestoreIcon,
    EllipsisVerticalIcon,
    PencilIcon,
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

/**
 * The small menu every session card and reading page offers: Edit and Archive
 * while the session is in the Library, and Restore once it is archived. An
 * unfinished session shows Archive unavailable with the reason in view.
 */
const props = defineProps<{
    /** The session's topic, used to name the menu for screen readers. */
    topic: string;
    /** Where Edit leads, or null when the session cannot be edited. */
    editHref: string | null;
    archived: boolean;
    finished: boolean;
}>();

const emit = defineEmits<{
    (event: 'archive'): void;
    (event: 'restore'): void;
}>();

const label = computed(() => `Actions for ${props.topic}`);
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="size-11 shrink-0 md:size-8"
                :aria-label="label"
            >
                <EllipsisVerticalIcon aria-hidden="true" />
            </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent align="end" class="w-56">
            <template v-if="props.archived">
                <DropdownMenuItem @select="emit('restore')">
                    <ArchiveRestoreIcon aria-hidden="true" />
                    Restore
                </DropdownMenuItem>
            </template>

            <template v-else>
                <DropdownMenuItem v-if="props.editHref !== null" as-child>
                    <Link :href="props.editHref">
                        <PencilIcon aria-hidden="true" />
                        Edit
                    </Link>
                </DropdownMenuItem>

                <DropdownMenuItem
                    v-if="props.finished"
                    @select="emit('archive')"
                >
                    <ArchiveIcon aria-hidden="true" />
                    Archive
                </DropdownMenuItem>

                <!-- Available to read, but never triggerable: the reason is
                    visible text, not a tooltip alone. -->
                <DropdownMenuItem v-else disabled class="items-start">
                    <ArchiveIcon class="mt-0.5" aria-hidden="true" />
                    <span class="grid gap-0.5">
                        <span>Archive</span>
                        <span class="text-xs text-muted-foreground">
                            Finish this session to archive it.
                        </span>
                    </span>
                </DropdownMenuItem>
            </template>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
