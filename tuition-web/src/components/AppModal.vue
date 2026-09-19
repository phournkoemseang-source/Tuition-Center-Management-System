<script setup lang="ts">
import { onBeforeUnmount, watch } from 'vue'
import AppIcon from './AppIcon.vue'

const props = defineProps<{
  open: boolean
  title: string
  description?: string
  /** Shows Cancel / Confirm with a danger-styled confirm button. */
  confirm?: { label: string; tone?: 'primary' | 'danger' | 'success'; loading?: boolean }
}>()

const emit = defineEmits<{ close: []; confirm: [] }>()

// Escape to close
function onKey(e: KeyboardEvent) {
  if (e.key === 'Escape' && props.open) emit('close')
}
watch(
  () => props.open,
  (open) => {
    if (open) document.addEventListener('keydown', onKey)
    else document.removeEventListener('keydown', onKey)
  },
)
onBeforeUnmount(() => document.removeEventListener('keydown', onKey))
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-100 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="open"
        class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/45 backdrop-blur-[2px]"
        @click.self="emit('close')"
      >
        <div class="flex min-h-full items-center justify-center p-4">
          <Transition
            appear
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="translate-y-2 scale-[0.98] opacity-0"
            enter-to-class="translate-y-0 scale-100 opacity-100"
          >
            <div
              class="w-full rounded-2xl bg-white shadow-pop"
              :class="confirm ? 'max-w-md' : 'max-w-lg'"
              role="dialog"
              aria-modal="true"
            >
              <!-- Confirmation dialog layout -->
              <template v-if="confirm">
                <div class="p-6 text-center">
                  <div
                    class="mx-auto mb-4 grid h-12 w-12 place-items-center rounded-full"
                    :class="confirm.tone === 'danger' ? 'bg-red-50 text-red-600' : 'bg-brand-50 text-brand-600'"
                  >
                    <AppIcon :name="confirm.tone === 'danger' ? 'alert-circle' : 'user-check'" :size="22" />
                  </div>
                  <h2 class="text-base font-bold text-slate-900">{{ title }}</h2>
                  <p v-if="description" class="mt-1 text-sm text-slate-500">{{ description }}</p>
                </div>
                <div class="flex justify-center gap-3 border-t border-slate-100 px-6 py-4">
                  <button type="button" class="btn btn-secondary" @click="emit('close')">Cancel</button>
                  <button
                    type="button"
                    class="btn"
                    :class="confirm.tone === 'danger' ? 'btn-danger' : confirm.tone === 'success' ? 'btn-success' : 'btn-primary'"
                    :disabled="confirm.loading"
                    @click="emit('confirm')"
                  >
                    {{ confirm.loading ? 'Working…' : confirm.label }}
                  </button>
                </div>
              </template>

              <!-- Standard panel layout -->
              <template v-else>
                <div class="flex items-start justify-between gap-4 px-6 pt-5">
                  <div class="min-w-0">
                    <h2 class="text-base font-bold text-slate-900">{{ title }}</h2>
                    <p v-if="description" class="mt-0.5 text-sm text-slate-500">{{ description }}</p>
                  </div>
                  <button
                    class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                    aria-label="Close"
                    @click="emit('close')"
                  >
                    <AppIcon name="x" :size="16" />
                  </button>
                </div>
                <div class="px-6 pb-6 pt-4">
                  <slot />
                </div>
              </template>
            </div>
          </Transition>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
