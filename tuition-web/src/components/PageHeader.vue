<script setup lang="ts">
defineProps<{
  title: string
  subtitle?: string
}>()
</script>

<template>
  <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div class="min-w-0">
      <h1 class="truncate text-2xl font-bold tracking-tight text-slate-900">{{ title }}</h1>
      <p v-if="subtitle" class="mt-0.5 text-sm text-slate-500">{{ subtitle }}</p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
      <slot />
    </div>
  </div>
</template>
