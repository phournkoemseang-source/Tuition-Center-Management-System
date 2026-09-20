<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import AppIcon from './AppIcon.vue'

const props = defineProps<{ at: Date | null }>()

const now = ref(Date.now())
let timer: ReturnType<typeof setInterval> | null = null
onMounted(() => {
  timer = setInterval(() => (now.value = Date.now()), 5000)
})
onBeforeUnmount(() => {
  if (timer) clearInterval(timer)
})

const label = computed(() => {
  if (!props.at) return null
  const s = Math.max(0, Math.round((now.value - props.at.getTime()) / 1000))
  if (s < 10) return 'just now'
  if (s < 60) return `${s}s ago`
  const m = Math.floor(s / 60)
  if (m < 60) return `${m}m ago`
  return `${Math.floor(m / 60)}h ago`
})
</script>

<template>
  <span
    v-if="label"
    class="inline-flex shrink-0 items-center gap-1.5 text-xs font-medium text-slate-400"
    :title="at ? `Last refreshed at ${at.toLocaleTimeString()}` : undefined"
  >
    <AppIcon name="clock" :size="12" />
    Updated {{ label }}
  </span>
</template>
