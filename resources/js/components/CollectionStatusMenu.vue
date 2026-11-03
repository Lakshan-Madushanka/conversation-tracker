<script setup lang="ts">
import { computed } from 'vue';
import { ChevronDownIcon, TagIcon } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { CollectionItemStatusValue } from '@/types';

/**
 * The learner's status for one collection item: Learned or Still difficult,
 * or none at all. Like the star it takes the state the learner asked for,
 * never a toggle; only the learner changes it.
 */
const props = defineProps<{
    /** The chosen status, or null while none is set. */
    status: CollectionItemStatusValue | null;
    /** The accessible name of the switcher, with the item in it. */
    label: string;
    /**
     * The icon-only look used inside a table row, where the status itself is
     * shown next to the switcher as a badge.
     */
    compact?: boolean;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    /** The status the learner asked for, or null to have none. */
    change: [status: CollectionItemStatusValue | null];
}>();

/** The status word shown in the switcher, or none. */
const chosen = computed(() =>
    props.status === 'learned' ? 'Learned' : 'Still difficult',
);
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child :disabled="props.disabled">
            <Button
                v-if="props.compact"
                type="button"
                variant="ghost"
                :aria-label="props.label"
                class="size-8 shrink-0 p-0 cursor-pointer"
            >
                <TagIcon
                    aria-hidden="true"
                    class="size-4 text-muted-foreground"
                />
            </Button>
            <Button
                v-else
                type="button"
                variant="ghost"
                :aria-label="props.label"
                class="min-h-11 max-w-48 shrink-0 justify-start gap-2 px-3 cursor-pointer"
            >
                <TagIcon
                    aria-hidden="true"
                    class="size-4 shrink-0 text-muted-foreground"
                />
                <span
                    v-if="props.status !== null"
                    class="truncate font-medium"
                    >{{ chosen }}</span
                >
                <span v-else class="truncate font-normal text-muted-foreground"
                    >Status</span
                >
                <ChevronDownIcon
                    aria-hidden="true"
                    class="size-4 shrink-0 text-muted-foreground"
                />
            </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent align="start" class="w-52">
            <DropdownMenuRadioGroup
                :model-value="props.status ?? 'none'"
                @update:model-value="
                    emit(
                        'change',
                        $event === 'none'
                            ? null
                            : ($event as CollectionItemStatusValue),
                    )
                "
            >
                <DropdownMenuRadioItem class="cursor-pointer" value="learned">
                    Learned
                </DropdownMenuRadioItem>
                <DropdownMenuRadioItem class="cursor-pointer" value="still_difficult">
                    Still difficult
                </DropdownMenuRadioItem>
                <DropdownMenuRadioItem class="cursor-pointer" value="none">
                    No status
                </DropdownMenuRadioItem>
            </DropdownMenuRadioGroup>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
