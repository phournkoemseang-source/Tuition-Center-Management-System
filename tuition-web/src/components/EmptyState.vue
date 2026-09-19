<script setup lang="ts">
import AppIcon, { type IconName } from './AppIcon.vue'

withDefaults(
  defineProps<{
    title?: string
    description?: string
    icon?: IconName
  }>(),
  {
    title: 'Nothing here yet',
    description: undefined,
    icon: 'inbox',
  },
)
</script>

<template>
  <div class="grid place-items-center px-6 py-16 text-center">
    <div class="mb-4 grid h-14 w-14 place-items-center rounded-2xl bg-slate-100 text-slate-400">
      <AppIcon :name="icon" :size="26" />
    </div>
    <h3 class="text-sm font-semibold text-slate-800">{{ title }}</h3>
    <p v-if="description" class="mt-1 max-w-sm text-sm text-slate-500">{{ description }}</p>
    <div v-if="$slots.default" class="mt-4">
      <slot />
    </div>
  </div>
</template>
