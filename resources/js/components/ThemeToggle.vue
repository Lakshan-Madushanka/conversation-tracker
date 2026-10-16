<script setup lang="ts">
import { MoonIcon, SunIcon } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/composables/useAppearance';

const { appearance, toggleAppearance } = useAppearance();
</script>

<template>
    <Button
        variant="ghost"
        size="icon-sm"
        :aria-label="
            appearance === 'dark'
                ? 'Switch to light mode'
                : 'Switch to dark mode'
        "
        @click="toggleAppearance"
    >
        <div class="sr-only">
            {{ appearance === 'dark' ? 'Dark' : 'Light' }} mode
        </div>
        <SunIcon v-if="appearance === 'dark'" aria-hidden="true" />
        <MoonIcon v-else aria-hidden="true" />
    </Button>
</template>
