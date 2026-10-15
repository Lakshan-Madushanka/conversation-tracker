<script setup lang="ts">
import { reactive } from 'vue';
import { DownloadIcon } from '@lucide/vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/SettingsLayout.vue';

defineOptions({ layout: [AppLayout, SettingsLayout] });

const options = [
    { key: 'sessions', label: 'All sessions' },
    { key: 'vocabulary', label: 'Vocabulary' },
    { key: 'phrases', label: 'Phrases' },
    { key: 'grammar', label: 'Grammar notes' },
    { key: 'mistakes', label: 'My common mistakes' },
] as const;

type OptionKey = (typeof options)[number]['key'];

const include = reactive<Record<OptionKey, boolean>>({
    sessions: true,
    vocabulary: true,
    phrases: true,
    grammar: true,
    mistakes: true,
});

const lastBackup = '12 Oct';

function download(): void {
    const picked = options
        .filter((option) => include[option.key])
        .map((option) => option.label.toLowerCase());

    toast.success('Your export is ready', {
        description: `Includes ${picked.join(', ') || 'nothing yet'}.`,
    });
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="text-base">Export your data</CardTitle>
            <CardDescription>
                Your data is yours. Download it any time as a spreadsheet.
            </CardDescription>
        </CardHeader>

        <CardContent class="space-y-4">
            <ul class="grid gap-3">
                <li
                    v-for="option in options"
                    :key="option.key"
                    class="flex items-center gap-3"
                >
                    <Checkbox
                        :id="`export-${option.key}`"
                        v-model="include[option.key]"
                    />
                    <label :for="`export-${option.key}`" class="text-sm">
                        {{ option.label }}
                    </label>
                </li>
            </ul>

            <div
                class="flex flex-wrap items-center justify-between gap-3 border-t pt-4"
            >
                <p class="text-xs text-muted-foreground">
                    Last backup: {{ lastBackup }}
                </p>
                <Button size="lg" @click="download">
                    <DownloadIcon aria-hidden="true" />
                    Download
                </Button>
            </div>
        </CardContent>
    </Card>
</template>
