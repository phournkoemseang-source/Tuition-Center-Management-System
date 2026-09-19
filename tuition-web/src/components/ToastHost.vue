<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import AppIcon from './AppIcon.vue'

const toasts = ref<{ id: number; message: string; kind: 'success' | 'error' | 'info' }[]>([])
let seq = 0
const timers = new Map<number, ReturnType<typeof setTimeout>>()

function push(message: string, kind: 'success' | 'error' | 'info' = 'success') {
  const id = ++seq
  toasts.value.push({ id, message, kind })
  timers.set(id, setTimeout(() => dismiss(id), 3500))
}

function dismiss(id: number) {
  toasts.value = toasts.value.filter((t) => t.id !== id)
  const timer = timers.get(id)
  if (timer) {
    clearTimeout(timer)
    timers.delete(id)
  }
}

function onToast(e: Event) {
  const detail = (e as CustomEvent).detail as { message: string; kind?: 'success' | 'error' | 'info' }
  push(detail.message, detail.kind ?? 'success')
}

onMounted(() => window.addEventListener('app:toast', onToast))
onBeforeUnmount(() => {
  window.removeEventListener('app:toast', onToast)
  timers.forEach((t) => clearTimeout(t))
})

const icons = { success: 'check-circle', error: 'alert-circle', info: 'alert-circle' } as const
const tones = {
  success: 'text-emerald-500',
  error: 'text-red-500',
  info: 'text-brand-500',
}
</script>

<template>
  <Teleport to="body">
    <div class="pointer-events-none fixed right-4 top-4 z-[70] flex w-full max-w-sm flex-col gap-2">
      <TransitionGroup
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="translate-x-4 opacity-0"
        enter-to-class="translate-x-0 opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="translate-x-4 opacity-0"
      >
        <div
          v-for="t in toasts"
          :key="t.id"
          class="pointer-events-auto flex items-start gap-3 rounded-xl border border-slate-200 bg-white p-3.5 shadow-pop"
          role="status"
        >
          <AppIcon :name="icons[t.kind]" :size="18" class="mt-0.5 shrink-0" :class="tones[t.kind]" />
          <p class="min-w-0 flex-1 text-sm font-medium text-slate-700">{{ t.message }}</p>
          <button class="shrink-0 rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600" @click="dismiss(t.id)">
            <AppIcon name="x" :size="14" />
          </button>
        </div>
  </TransitionGroup>
    </div>
  </Teleport>
</template>
