<script setup lang="ts">
import AppIcon, { type IconName } from './AppIcon.vue'

defineProps<{
  label: string
  value: string | number
  sub?: string
  icon: IconName
  tone?: 'default' | 'danger' | 'success' | 'warning'
}>()

const iconTone: Record<string, string> = {
  default: 'bg-brand-50 text-brand-600',
  danger: 'bg-red-50 text-red-600',
  success: 'bg-emerald-50 text-emerald-600',
  warning: 'bg-amber-50 text-amber-600',
}
</script>

<template>
  <div class="card card-pad flex items-start justify-between gap-4">
    <div class="min-w-0">
      <p class="text-[13px] font-medium text-slate-500">{{ label }}</p>
      <p class="mt-1.5 truncate text-[26px] font-bold leading-none tracking-tight text-slate-900">
        {{ value }}
      </p>
      <p v-if="sub" class="mt-2 text-xs text-slate-400">{{ sub }}</p>
    </div>
    <div class="grid h-11 w-11 shrink-0 place-items-center rounded-xl" :class="iconTone[tone ?? 'default']">
      <AppIcon :name="icon" :size="20" />
    </div>
  </div>
</template>
