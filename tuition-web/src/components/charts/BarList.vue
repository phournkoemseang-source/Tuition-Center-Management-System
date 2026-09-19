<script setup lang="ts">
import { computed } from 'vue'

export interface BarItem {
  label: string
  sub?: string
  value: number
  display?: string
  tone?: 'default' | 'danger' | 'success'
}

const props = withDefaults(defineProps<{ items: BarItem[] }>(), {})

const max = computed(() => Math.max(1, ...props.items.map((i) => i.value)))

const toneBar: Record<string, string> = {
  default: 'bg-brand-500',
  danger: 'bg-red-500',
  success: 'bg-emerald-500',
}
</script>

<template>
  <ul v-if="items.length" class="space-y-3.5">
    <li v-for="item in items" :key="item.label">
      <div class="mb-1.5 flex items-baseline justify-between gap-3">
        <p class="min-w-0 truncate text-sm font-medium text-slate-700">
          {{ item.label }}
          <span v-if="item.sub" class="ml-1.5 text-xs font-normal text-slate-400">{{ item.sub }}</span>
        </p>
        <p class="shrink-0 text-sm font-semibold tabular-nums" :class="item.tone === 'danger' ? 'text-red-600' : 'text-slate-800'">
          {{ item.display ?? item.value }}
        </p>
      </div>
      <div class="h-2 overflow-hidden rounded-full bg-slate-100">
        <div
          class="h-full rounded-full transition-all duration-500"
          :class="toneBar[item.tone ?? 'default']"
          :style="{ width: `${(item.value / max) * 100}%` }"
        />
      </div>
    </li>
  </ul>
  <p v-else class="py-4 text-center text-sm text-slate-400">No data to show.</p>
</template>
