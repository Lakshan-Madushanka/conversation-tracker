<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { MoonIcon, SunIcon } from '@lucide/vue';
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
import { Switch } from '@/components/ui/switch';
import { useAppearance } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/SettingsLayout.vue';

defineOptions({ layout: [AppLayout, SettingsLayout] });

const { appearance, setAppearance } = useAppearance();

const isDark = computed({
    get: () => appearance.value === 'dark',
    set: (value: boolean) => setAppearance(value ? 'dark' : 'light'),
});

const reminderEnabled = ref(false);
const reminderTime = ref('08:00');
const level = ref('b1');
</script>

<template>
    <div class="space-y-4">
        <Head title="Preferences" />

        <!-- Appearance -->
        <Card>
            <CardHeader>
                <CardTitle class="text-base">Appearance</CardTitle>
                <CardDescription>
                    Choose light or dark mode for the whole app.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="flex items-center justify-between gap-4">
                    <span class="inline-flex items-center gap-2 text-sm">
                        <SunIcon
                            v-if="!isDark"
                            class="size-4 text-amber-500"
                            aria-hidden="true"
                        />
                        <MoonIcon
                            v-else
                            class="size-4 text-indigo-400"
                            aria-hidden="true"
                        />
                        Dark mode
                    </span>
                    <Switch
                        v-model:model-value="isDark"
                        aria-label="Dark mode"
                    />
                </div>
            </CardContent>
        </Card>

        <!-- Daily reminder (placeholder) -->
        <Card>
            <CardHeader>
                <CardTitle class="text-base">Daily reminder</CardTitle>
                <CardDescription>
                    Get a gentle nudge to practice at a time you choose.
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="flex items-center justify-between gap-4">
                    <span class="text-sm">Remind me to review</span>
                    <Switch
                        v-model:model-value="reminderEnabled"
                        aria-label="Remind me to review"
                    />
                </div>

                <div
                    class="flex items-center justify-between gap-4"
                    :class="
                        !reminderEnabled && 'pointer-events-none opacity-60'
                    "
                >
                    <label for="reminder-time" class="text-sm">Time</label>
                    <input
                        id="reminder-time"
                        v-model="reminderTime"
                        type="time"
                        :disabled="!reminderEnabled"
                        class="h-8 rounded-lg border border-input bg-transparent px-2.5 text-base md:text-sm"
                    />
                </div>
            </CardContent>
        </Card>

        <!-- Level (placeholder) -->
        <Card>
            <CardHeader>
                <CardTitle class="text-base">Your level</CardTitle>
                <CardDescription>
                    Helps tailor suggested topics and review items.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Select v-model="level">
                    <SelectTrigger
                        class="w-full sm:w-56"
                        aria-label="Your level"
                    >
                        <SelectValue placeholder="Pick a level" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="a2">A2 — Elementary</SelectItem>
                        <SelectItem value="b1">B1 — Intermediate</SelectItem>
                        <SelectItem value="b2"
                            >B2 — Upper intermediate</SelectItem
                        >
                        <SelectItem value="c1">C1 — Advanced</SelectItem>
                    </SelectContent>
                </Select>
            </CardContent>
        </Card>
    </div>
</template>
