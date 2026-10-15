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
        <SunIcon v-if="appearance === 'dark'" />
        <MoonIcon v-else />
    </Button>
</template>
