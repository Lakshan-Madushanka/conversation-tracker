<script setup lang="ts">
import { ArrowUpRightIcon } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { shortDate } from '@/lib/format';
import type {
    CustomReviewAttemptHistoryData,
    CustomReviewAttemptItemData,
} from '@/types';

const props = defineProps<{ attempt: CustomReviewAttemptHistoryData }>();

/** Where the item came from, as the review's own page words it. */
function sourceLabel(item: CustomReviewAttemptItemData): string {
    const date = item.session_date === null ? '' : shortDate(item.session_date);

    return [item.topic, date].filter((piece) => piece !== '').join(' · ');
}

/** The attempt's own answer for the item, never the item's current state. */
function stateLabel(item: CustomReviewAttemptItemData): string {
    if (item.state === 'rated') {
        return item.rating_label ?? 'Rated';
    }

    return item.state === 'skipped' ? 'Skipped' : 'Not rated';
}
</script>

<template>
    <ul class="grid gap-3">
        <li
            v-for="item in props.attempt.items"
            :key="item.id"
            class="grid gap-1 border-b pb-3 last:border-b-0 last:pb-0"
        >
            <div class="flex flex-wrap items-center gap-2">
                <Badge variant="secondary">{{ item.kind_label }}</Badge>
                <span class="text-xs text-muted-foreground">
                    {{ stateLabel(item) }}
                </span>
            </div>

            <p class="text-sm break-words whitespace-pre-line">
                {{ item.text }}
            </p>

            <p
                v-if="sourceLabel(item) !== ''"
                class="text-xs text-muted-foreground"
            >
                {{ sourceLabel(item) }}
            </p>

            <Button
                v-if="item.href"
                variant="ghost"
                size="sm"
                class="justify-self-start"
                as-child
            >
                <a :href="item.href" target="_blank" rel="noopener noreferrer">
                    <ArrowUpRightIcon aria-hidden="true" />
                    See original session
                    <span class="sr-only"> (opens in a new tab)</span>
                </a>
            </Button>
        </li>
    </ul>
</template>
