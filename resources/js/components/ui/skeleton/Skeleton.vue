<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from 'cn'

const props = defineProps<{
  class?: HTMLAttributes['class']
}>()
</script>

<template>
  <div
    data-slot="skeleton"
    :class="cn('bg-accent animate-pulse rounded-md', props.class)"
  />
</template>
