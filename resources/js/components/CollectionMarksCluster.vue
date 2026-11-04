<script setup lang="ts">
import { computed } from 'vue';
import { NotebookIcon } from '@lucide/vue';
import CollectionStarToggle from '@/components/CollectionStarToggle.vue';
import CollectionStatusMenu from '@/components/CollectionStatusMenu.vue';
import { Badge } from '@/components/ui/badge';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover'
import {
    starAriaLabel,
    statusAriaLabel,
    type MarkableItem,
} from '@/lib/collection-marks';
import type { CollectionItemStatusValue } from '@/types';
import notes from "@/routes/collections/notes";

/**
 * The marks shown beside one collection item in the list: the star, the
 * chosen status and, when there is one, a sign that a personal note exists.
 * The controls request the state the learner wants — never a toggle — and the
 * page decides how to save it.
 */
const props = defineProps<{
    /** The item whose marks these are: star, status and the notes sign. */
    item: MarkableItem;
    /**
     * What the item is called in words, so the accessible names say what
     * they belong to: "Star the word reluctant".
     */
    name: string;
    /** What one item of this collection is called: word, note, mistake. */
    unit: string;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    /** The state the learner asked for. */
    star: [starred: boolean];
    /** The status the learner asked for, or null to have none. */
    status: [status: CollectionItemStatusValue | null];
}>();

/** The status word in the badge, or none. */
const statusWord = computed(() =>
    props.item.status === 'learned' ? 'Learned' : 'Still difficult',
);
</script>

<!-- The cluster keeps the row's own click from firing when a control is
    used, so marking never also opens the item. -->
<template>
    <div class="flex flex-wrap items-center justify-end gap-1" @click.stop>
        <Popover
            v-if="props.item.has_notes"
            class="inline-flex items-center text-muted-foreground"
        >
            <PopoverTrigger>
                <NotebookIcon aria-hidden="true" class="size-5 cursor-pointer" />
            </PopoverTrigger>
            <PopoverContent>
                <div v-html="item.notes_html"></div>
            </PopoverContent>
        </Popover>

        <Badge v-if="props.item.status !== null" variant="outline">
            {{ statusWord }}
        </Badge>

        <CollectionStarToggle
            :starred="props.item.starred"
            :label="starAriaLabel(props.unit, props.name, props.item.starred)"
            :disabled="props.disabled"
            @change="emit('star', $event)"
        />

        <CollectionStatusMenu
            compact
            :status="props.item.status"
            :label="statusAriaLabel(props.unit, props.name)"
            :disabled="props.disabled"
            @change="emit('status', $event)"
        />
    </div>
</template>
