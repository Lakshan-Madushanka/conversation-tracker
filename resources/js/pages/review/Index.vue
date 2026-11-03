<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { GraduationCapIcon, PlayIcon, SquarePenIcon } from '@lucide/vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { store as reviewStore } from '@/routes/review';
import { create as sessionsCreate } from '@/routes/sessions';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    /** How many words and phrases could be reviewed right now. */
    reviewableCount: number;
    /** Whether the learner has finished at least one session. */
    hasFinishedSessions: boolean;
}>();

const form = useForm({});

function startReview(): void {
    form.post(reviewStore.url());
}
</script>

<template>
    <div>
        <Head title="Review" />

        <PageHeader
            title="Review"
            description="A calm few minutes with the words and phrases you want to keep."
        />

        <Card class="border-primary/30 bg-primary/5">
            <CardHeader>
                <span
                    class="mb-1 flex size-11 items-center justify-center rounded-full bg-primary/10 text-primary"
                    aria-hidden="true"
                >
                    <GraduationCapIcon class="size-5" />
                </span>

                <CardTitle class="text-xl">
                    <template v-if="!props.hasFinishedSessions">
                        Review fills up after your first session
                    </template>
                    <template v-else-if="props.reviewableCount === 0">
                        Nothing is ready to review just yet
                    </template>
                    <template v-else>
                        {{ props.reviewableCount }}
                        {{ props.reviewableCount === 1 ? 'item' : 'items' }}
                        ready to revisit
                    </template>
                </CardTitle>

                <CardDescription>
                    <template v-if="!props.hasFinishedSessions">
                        Finish a practice session and the words and phrases you
                        save will show up here as flashcards.
                    </template>
                    <template v-else-if="props.reviewableCount === 0">
                        Words and phrases need a meaning (or a use) before they
                        can become a flashcard. They will appear here once they
                        have one.
                    </template>
                    <template v-else>
                        About five minutes. Learn at your own pace — there is no
                        timer and no score.
                    </template>
                </CardDescription>
            </CardHeader>

            <CardFooter class="flex flex-wrap gap-2">
                <Button
                    v-if="props.reviewableCount > 0"
                    size="lg"
                    :disabled="form.processing"
                    @click="startReview"
                >
                    <PlayIcon aria-hidden="true" />
                    Start review
                </Button>

                <Button
                    v-else-if="!props.hasFinishedSessions"
                    size="lg"
                    as-child
                >
                    <Link :href="sessionsCreate.url()">
                        <SquarePenIcon aria-hidden="true" />
                        Start a session
                    </Link>
                </Button>
            </CardFooter>
        </Card>
    </div>
</template>
