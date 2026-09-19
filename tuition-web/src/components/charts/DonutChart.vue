<script setup lang="ts">
import { computed } from 'vue'

export interface DonutSlice {
  label: string
  value: number
  color: string
}

const props = defineProps<{
  slices: DonutSlice[]
  centerLabel?: string
  centerValue?: string
  size?: number
}>()

const RADIUS = 15.9155 // circumference = 100

const total = computed(() => props.slices.reduce((s, x) => s + Math.max(0, x.value), 0))
const hasData = computed(() => total.value > 0)

const segments = computed(() => {
  let offset = 0
  return props.slices.map((s) => {
    const frac = total.value > 0 ? s.value / total.value : 0
    const dash = frac * 100
    const seg = { ...s, dash, offset }
    offset += dash
    return seg
  })
})

const legend = computed(() =>
  props.slices.map((s) => ({
    ...s,
    pct: total.value > 0 ? Math.round((s.value / total.value) * 100) : 0,
  })),
)
</script>

<template>
  <div class="flex flex-col items-center gap-6 sm:flex-row sm:items-center">
    <div class="relative shrink-0" :style="{ width: `${size}px`, height: `${size}px` }">
      <svg viewBox="0 0 40 40" class="h-full w-full -rotate-90">
        <circle cx="20" cy="20" :r="RADIUS" fill="none" stroke="#e2e8f0" stroke-width="4" />
        <template v-if="hasData">
          <circle
            v-for="(seg, i) in segments"
            :key="i"
            cx="20"
            cy="20"
            :r="RADIUS"
            fill="none"
            :stroke="seg.color"
            stroke-width="4"
            :stroke-dasharray="`${seg.dash} ${100 - seg.dash}`"
            :stroke-dashoffset="-seg.offset"
            stroke-linecap="butt"
          />
        </template>
      </svg>
      <div class="absolute inset-0 grid place-items-center text-center">
        <div>
          <p class="text-xl font-bold tracking-tight text-slate-900">{{ centerValue }}</p>
          <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400">{{ centerLabel }}</p>
        </div>
      </div>
    </div>

    <ul class="w-full space-y-2.5 sm:max-w-[220px]">
      <li v-for="l in legend" :key="l.label" class="flex items-center justify-between gap-3 text-sm">
        <span class="flex min-w-0 items-center gap-2">
          <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="{ backgroundColor: l.color }" />
          <span class="truncate text-slate-600">{{ l.label }}</span>
        </span>
        <span class="shrink-0 font-semibold text-slate-800">{{ l.pct }}%</span>
      </li>
    </ul>
  </div>
</template>
