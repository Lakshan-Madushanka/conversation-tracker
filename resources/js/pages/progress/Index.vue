<script setup lang="ts">
import {
    CalendarIcon,
    FlameIcon,
    GraduationCapIcon,
    MessageSquareIcon,
    PartyPopperIcon,
    TrendingDownIcon,
    TypeIcon,
    BookOpenIcon,
} from '@lucide/vue';
import PageHeader from '@/components/PageHeader.vue';
import StatTile from '@/components/StatTile.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { mistakeTrend, progressStats } from '@/lib/sample-data';

defineOptions({ layout: AppLayout });

const maxMistakes = Math.max(...mistakeTrend.map((point) => point.count));

function barHeight(count: number): string {
    return `${Math.round((count / maxMistakes) * 100)}%`;
}
</script>

<template>
    <div>
        <PageHeader
            title="Progress"
            description="A gentle look at how your practice is adding up."
        />

        <!-- Streak + practice days -->
        <div class="grid grid-cols-2 gap-3 sm:gap-4">
            <StatTile
                label="Current streak"
                :value="`${progressStats.streak} days`"
                :icon="FlameIcon"
                hint="Keep it going!"
            />
            <StatTile
                label="Total practice days"
                :value="progressStats.practiceDays"
                :icon="CalendarIcon"
                hint="Since you started"
            />
        </div>

        <!-- Learning totals -->
        <div
            class="mt-3 grid grid-cols-2 gap-3 sm:mt-4 sm:grid-cols-4 sm:gap-4"
        >
            <StatTile
                label="Sessions"
                :value="progressStats.sessions"
                :icon="MessageSquareIcon"
            />
            <StatTile
                label="Words"
                :value="progressStats.words"
                :icon="TypeIcon"
            />
            <StatTile
                label="Phrases"
                :value="progressStats.phrases"
                :icon="BookOpenIcon"
            />
            <StatTile
                label="Grammar"
                :value="progressStats.grammar"
                :icon="GraduationCapIcon"
            />
        </div>

        <!-- Mistakes chart -->
        <Card class="mt-6">
            <CardHeader>
                <CardDescription>Mistakes over time</CardDescription>
                <CardTitle class="flex items-center gap-2 text-base">
                    <TrendingDownIcon
                        class="size-4 text-emerald-500"
                        aria-hidden="true"
                    />
                    Your common mistakes are fading
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div
                    class="flex h-40 items-end gap-3"
                    role="img"
                    aria-label="A bar chart showing common mistakes falling from 14 to 5 over four weeks."
                >
                    <div
                        v-for="point in mistakeTrend"
                        :key="point.week"
                        class="flex flex-1 flex-col items-center gap-2"
                    >
                        <span
                            class="text-xs text-muted-foreground tabular-nums"
                            >{{ point.count }}</span
                        >
                        <div
                            class="w-full rounded-t-md bg-primary/70"
                            :style="{ height: barHeight(point.count) }"
                        />
                        <span class="text-xs text-muted-foreground">{{
                            point.week
                        }}</span>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Weekly summary -->
        <Card class="mt-4 border-primary/30 bg-primary/5">
            <CardHeader>
                <CardDescription>This week</CardDescription>
                <CardTitle class="text-base">
                    {{ progressStats.weeklySummary }}
                </CardTitle>
            </CardHeader>
            <CardContent>
                <p class="flex items-center gap-2 text-sm">
                    <PartyPopperIcon
                        class="size-4 shrink-0 text-primary"
                        aria-hidden="true"
                    />
                    {{ progressStats.win }}
                </p>
            </CardContent>
        </Card>
    </div>
</template>
