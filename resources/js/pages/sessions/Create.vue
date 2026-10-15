<script setup lang="ts">
import { reactive, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { CheckIcon, ChevronDownIcon, PlusIcon } from '@lucide/vue';
import { cn } from 'cn';
import type { FeedbackTone } from '@/components/SectionLabel.vue';
import PageHeader from '@/components/PageHeader.vue';
import SectionLabel from '@/components/SectionLabel.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import { topics } from '@/lib/sample-data';
import { index as sessionsIndex } from '@/routes/sessions';

defineOptions({ layout: AppLayout });

const selectedTopic = ref(topics[0]);
const guidedMode = ref(false);
const question = ref('');
const answer = ref('');

interface FeedbackBox {
    label: string;
    tone: FeedbackTone;
    hint: string;
    placeholder: string;
}

const feedbackBoxes: FeedbackBox[] = [
    {
        label: 'Mistakes',
        tone: 'mistakes',
        hint: 'What went wrong, and the correction',
        placeholder: 'Paste the mistakes and corrections…',
    },
    {
        label: 'Vocabulary',
        tone: 'vocabulary',
        hint: 'One word per line',
        placeholder: 'reluctant — unwilling; not eager',
    },
    {
        label: 'Phrases',
        tone: 'phrases',
        hint: 'One phrase per line',
        placeholder: 'catch up — talk after a long time',
    },
    {
        label: 'Grammar',
        tone: 'grammar',
        hint: 'Points to remember',
        placeholder: 'Use “I’d like …” instead of “I want …”',
    },
    {
        label: 'Natural version',
        tone: 'natural',
        hint: 'A more natural way to say it',
        placeholder: 'I’d like the soup, then a salad if that’s okay.',
    },
];

const openBoxes = reactive<Record<string, boolean>>({
    Mistakes: true,
    Vocabulary: true,
});

const boxValues = reactive<Record<string, string>>({});
</script>

<template>
    <div>
        <PageHeader
            title="New session"
            description="Paste the tutor’s questions, your answers and the feedback you want to keep."
        >
            <template #actions>
                <div class="flex items-center gap-2">
                    <span
                        class="inline-flex items-center gap-1.5 text-xs text-muted-foreground"
                    >
                        <span
                            class="size-1.5 rounded-full bg-emerald-500"
                            aria-hidden="true"
                        />
                        Saved
                    </span>
                    <Button variant="outline" size="lg" as-child>
                        <Link :href="sessionsIndex.url()">
                            <CheckIcon aria-hidden="true" />
                            Finish session
                        </Link>
                    </Button>
                </div>
            </template>
        </PageHeader>

        <!-- Topic + guided mode -->
        <Card class="mb-6">
            <CardContent class="space-y-4 pt-4">
                <div class="space-y-1.5">
                    <label for="topic" class="text-sm font-medium">Topic</label>
                    <Input
                        id="topic"
                        v-model="selectedTopic"
                        placeholder="Ordering at a restaurant"
                    />
                    <p class="text-xs text-muted-foreground">
                        Pick a topic below, or type your own.
                    </p>
                </div>

                <div
                    class="flex flex-wrap gap-2"
                    role="group"
                    aria-label="Recent topics"
                >
                    <button
                        v-for="topic in topics"
                        :key="topic"
                        type="button"
                        :class="
                            cn(
                                'rounded-full border px-3 py-1 text-xs font-medium transition-colors',
                                selectedTopic === topic
                                    ? 'border-primary bg-primary/10 text-primary'
                                    : 'text-muted-foreground hover:bg-accent',
                            )
                        "
                        @click="selectedTopic = topic"
                    >
                        {{ topic }}
                    </button>
                </div>

                <Separator />

                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium">Paste in order</p>
                        <p class="text-xs text-muted-foreground">
                            One box at a time, with Back and Next.
                        </p>
                    </div>
                    <Switch v-model:model-value="guidedMode" />
                </div>
            </CardContent>
        </Card>

        <!-- Question card -->
        <Card>
            <CardHeader class="flex-row items-center justify-between space-y-0">
                <CardTitle class="text-base">Question 1</CardTitle>
                <CardDescription>Draft</CardDescription>
            </CardHeader>

            <CardContent class="space-y-4">
                <div class="space-y-1.5">
                    <label for="question-1" class="text-sm font-medium"
                        >Tutor’s question</label
                    >
                    <Textarea
                        id="question-1"
                        v-model="question"
                        placeholder="Paste the tutor’s question here…"
                    />
                </div>

                <div class="space-y-1.5">
                    <label for="answer-1" class="text-sm font-medium"
                        >My answer</label
                    >
                    <Textarea
                        id="answer-1"
                        v-model="answer"
                        placeholder="Paste your own answer…"
                    />
                </div>

                <Separator />

                <div class="space-y-2">
                    <p
                        class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        Tutor’s feedback
                    </p>

                    <Collapsible
                        v-for="box in feedbackBoxes"
                        :key="box.label"
                        v-model:open="openBoxes[box.label]"
                        class="rounded-lg border"
                    >
                        <CollapsibleTrigger
                            class="flex w-full items-center gap-3 px-3 py-2 text-left outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
                        >
                            <SectionLabel :tone="box.tone">{{
                                box.label
                            }}</SectionLabel>
                            <span
                                class="flex-1 text-xs text-muted-foreground"
                                >{{ box.hint }}</span
                            >
                            <ChevronDownIcon
                                class="size-4 shrink-0 text-muted-foreground transition-transform"
                                :class="openBoxes[box.label] && 'rotate-180'"
                                aria-hidden="true"
                            />
                        </CollapsibleTrigger>
                        <CollapsibleContent class="px-3 pb-3">
                            <Textarea
                                v-model="boxValues[box.label]"
                                :placeholder="box.placeholder"
                            />
                        </CollapsibleContent>
                    </Collapsible>
                </div>
            </CardContent>

            <CardFooter class="justify-between gap-2">
                <Button variant="outline" size="lg">
                    <PlusIcon aria-hidden="true" />
                    Add question
                </Button>
                <p class="text-xs text-muted-foreground">
                    Empty boxes are simply skipped.
                </p>
            </CardFooter>
        </Card>
    </div>
</template>
