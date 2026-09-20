<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import AppIcon from './AppIcon.vue'
import type { BannerToastOptions, ToastKind } from '../utils/toast'

const toasts = ref<{ id: number; message: string; kind: ToastKind }[]>([])
let seq = 0
const timers = new Map<number, ReturnType<typeof setTimeout>>()

// Center-bottom banners (big attention-grabbing events)
const banners = ref<(BannerToastOptions & { id: number })[]>([])
let bannerSeq = 0
const bannerTimers = new Map<number, ReturnType<typeof setTimeout>>()

function push(message: string, kind: ToastKind = 'success') {
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

function pushBanner(detail: BannerToastOptions) {
  const id = ++bannerSeq
  banners.value = [...banners.value.slice(-2), { ...detail, id }]
  bannerTimers.set(id, setTimeout(() => dismissBanner(id), detail.duration ?? 6000))
}

function dismissBanner(id: number) {
  banners.value = banners.value.filter((b) => b.id !== id)
  const timer = bannerTimers.get(id)
  if (timer) {
    clearTimeout(timer)
    bannerTimers.delete(id)
  }
}

function runBannerAction(b: BannerToastOptions & { id: number }) {
  b.onAction?.()
  dismissBanner(b.id)
}

function onToast(e: Event) {
  const detail = (e as CustomEvent).detail as { message: string; kind?: ToastKind }
  push(detail.message, detail.kind ?? 'success')
}

function onBannerToast(e: Event) {
  pushBanner((e as CustomEvent).detail as BannerToastOptions)
}

onMounted(() => {
  window.addEventListener('app:toast', onToast)
  window.addEventListener('app:banner-toast', onBannerToast)
})
onBeforeUnmount(() => {
  window.removeEventListener('app:toast', onToast)
  window.removeEventListener('app:banner-toast', onBannerToast)
  timers.forEach((t) => clearTimeout(t))
  bannerTimers.forEach((t) => clearTimeout(t))
})

const icons = { success: 'check-circle', error: 'alert-circle', info: 'alert-circle' } as const
const tones = {
  success: 'text-emerald-500',
  error: 'text-red-500',
  info: 'text-brand-500',
}
const bannerIcons = { success: 'check-circle', error: 'alert-circle', info: 'graduation-cap' } as const
const bannerTones = {
  success: 'text-emerald-400',
  error: 'text-red-400',
  info: 'text-brand-300',
}
</script>

<template>
  <Teleport to="body">
    <!-- Regular toasts: top-right -->
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

    <!-- Banner toasts: center-bottom, for events the teacher must notice -->
    <div class="pointer-events-none fixed inset-x-0 bottom-6 z-[80] flex flex-col items-center gap-2 px-4">
      <TransitionGroup
        enter-active-class="transition duration-250 ease-out"
        enter-from-class="translate-y-6 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="translate-y-4 opacity-0"
      >
        <div
          v-for="b in banners"
          :key="b.id"
          class="pointer-events-auto flex w-full max-w-md items-center gap-3 rounded-2xl border border-slate-700/60 bg-slate-900/95 px-4 py-3.5 shadow-pop backdrop-blur"
          role="alert"
        >
          <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-white/10">
            <AppIcon :name="bannerIcons[b.kind ?? 'success']" :size="18" :class="bannerTones[b.kind ?? 'success']" />
          </span>
          <p class="min-w-0 flex-1 text-sm font-bold text-white">{{ b.message }}</p>
          <button
            v-if="b.actionLabel"
            class="shrink-0 rounded-lg bg-white px-3 py-1.5 text-xs font-bold text-slate-900 transition hover:bg-slate-200"
            @click="runBannerAction(b)"
          >
            {{ b.actionLabel }}
          </button>
          <button class="shrink-0 rounded-md p-1 text-slate-400 transition hover:bg-white/10 hover:text-white" aria-label="Dismiss" @click="dismissBanner(b.id)">
            <AppIcon name="x" :size="14" />
          </button>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>
