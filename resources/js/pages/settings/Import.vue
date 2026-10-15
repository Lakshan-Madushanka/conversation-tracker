<script setup lang="ts">
import { reactive, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import {
    AlertTriangleIcon,
    ChevronRightIcon,
    FileSpreadsheetIcon,
} from '@lucide/vue';
import { cn } from 'cn';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/SettingsLayout.vue';
import { index as sessionsIndex } from '@/routes/sessions';

defineOptions({ layout: [AppLayout, SettingsLayout] });

const steps = ['File', 'Match', 'Preview', 'Done'];
const activeStep = ref(0);

const fieldOptions = [
    'Topic',
    'Question',
    'Answer',
    'Mistakes',
    'Vocabulary',
    'Phrases',
    'Grammar',
    'Natural version',
    'Skip',
];

const mappings = reactive([
    { column: 'Column A', value: 'Topic' },
    { column: 'Column B', value: 'Question' },
    { column: 'Column C', value: 'Vocabulary' },
]);

const previewRows = [
    {
        id: 1,
        topic: 'Ordering at a restaurant',
        question: 'What would you like to start with?',
        vocabulary: 'relish',
        problem: '',
    },
    {
        id: 2,
        topic: 'Job interview practice',
        question: 'Tell me about yourself.',
        vocabulary: 'reluctant',
        problem: '',
    },
    {
        id: 3,
        topic: '',
        question: 'How do you handle feedback?',
        vocabulary: '',
        problem: 'Empty topic and vocabulary cells',
    },
];
</script>

<template>
    <div>
        <!-- Step indicator -->
        <ol
            class="mb-6 flex flex-wrap items-center gap-2 text-sm"
            aria-label="Import steps"
        >
            <li
                v-for="(step, index) in steps"
                :key="step"
                class="flex items-center gap-2"
            >
                <span
                    :class="
                        cn(
                            'flex size-6 items-center justify-center rounded-full text-xs font-medium',
                            index === activeStep
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-muted text-muted-foreground',
                        )
                    "
                    >{{ index + 1 }}</span
                >
                <span
                    :class="
                        index === activeStep
                            ? 'text-foreground'
                            : 'text-muted-foreground'
                    "
                    >{{ step }}</span
                >
                <ChevronRightIcon
                    v-if="index < steps.length - 1"
                    class="size-4 text-muted-foreground"
                    aria-hidden="true"
                />
            </li>
        </ol>

        <!-- Choose file -->
        <Card class="mb-4">
            <CardHeader>
                <CardTitle class="text-base">Choose file</CardTitle>
                <CardDescription>
                    Bring in an existing spreadsheet so past practice is not
                    lost.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div
                    class="flex flex-col items-center justify-center rounded-xl border border-dashed px-6 py-10 text-center"
                >
                    <FileSpreadsheetIcon
                        class="mb-2 size-8 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <p class="text-sm font-medium">
                        Drag your spreadsheet here
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        .csv or .xlsx
                    </p>
                    <Button variant="outline" class="mt-4">
                        Choose file
                    </Button>
                </div>
            </CardContent>
        </Card>

        <!-- Match columns -->
        <Card class="mb-4">
            <CardHeader>
                <CardTitle class="text-base">Match columns</CardTitle>
                <CardDescription>
                    Tell the app what each spreadsheet column holds.
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-3">
                <div
                    v-for="mapping in mappings"
                    :key="mapping.column"
                    class="flex items-center justify-between gap-3"
                >
                    <span class="text-sm">{{ mapping.column }}</span>
                    <Select v-model="mapping.value">
                        <SelectTrigger class="w-56">
                            <SelectValue placeholder="What is this?" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in fieldOptions"
                                :key="option"
                                :value="option"
                            >
                                {{ option }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </CardContent>
        </Card>

        <!-- Preview -->
        <Card>
            <CardHeader>
                <CardTitle class="text-base">Preview</CardTitle>
                <CardDescription>
                    Check the first few rows before importing.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="overflow-x-auto">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Topic</TableHead>
                                <TableHead>Question</TableHead>
                                <TableHead>Vocabulary</TableHead>
                                <TableHead>Status</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="row in previewRows"
                                :key="row.id"
                                :class="
                                    row.problem &&
                                    'bg-amber-50 dark:bg-amber-950/30'
                                "
                            >
                                <TableCell>{{ row.topic || '—' }}</TableCell>
                                <TableCell>{{ row.question }}</TableCell>
                                <TableCell>{{
                                    row.vocabulary || '—'
                                }}</TableCell>
                                <TableCell>
                                    <span
                                        v-if="row.problem"
                                        class="inline-flex items-center gap-1 text-amber-600 dark:text-amber-400"
                                    >
                                        <AlertTriangleIcon
                                            class="size-3.5"
                                            aria-hidden="true"
                                        />
                                        {{ row.problem }}
                                    </span>
                                    <span v-else class="text-muted-foreground"
                                        >Ready</span
                                    >
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <div class="mt-5 flex items-center justify-between gap-3">
                    <p class="text-xs text-muted-foreground">
                        Nothing is saved until you confirm.
                    </p>
                    <Button size="lg" as-child>
                        <Link :href="sessionsIndex.url()">
                            Import and open library
                        </Link>
                    </Button>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
