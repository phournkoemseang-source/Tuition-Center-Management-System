<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'

/**
 * Animates from 0 to `value` with an ease-out curve — gives the KPI board
 * its "running numbers" feel. Re-runs whenever `value` changes.
 */
const props = withDefaults(
  defineProps<{
    value: number
    duration?: number
    format?: (n: number) => string
  }>(),
  { duration: 900 },
)

const display = ref((props.format ?? defaultFormat)(0))
let raf = 0

function defaultFormat(n: number): string {
  return String(Math.round(n))
}

function run() {
  cancelAnimationFrame(raf)
  const fmt = props.format ?? defaultFormat
  const to = Number(props.value)
  if (!Number.isFinite(to)) {
    display.value = fmt(0)
    return
  }
  if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) {
    display.value = fmt(to)
    return
  }
  const t0 = performance.now()
  const ease = (t: number) => 1 - Math.pow(1 - t, 3)
  const step = (now: number) => {
    const p = Math.min(1, (now - t0) / props.duration)
    display.value = fmt(to * ease(p))
    if (p < 1) raf = requestAnimationFrame(step)
  }
  raf = requestAnimationFrame(step)
}

onMounted(run)
watch(() => props.value, run)
onBeforeUnmount(() => cancelAnimationFrame(raf))
</script>

<template>
  <span class="tabular-nums">{{ display }}</span>
</template>
