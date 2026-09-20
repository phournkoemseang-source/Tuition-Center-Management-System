import { onBeforeUnmount, onMounted, type Ref } from 'vue'

type PauseCheck = Ref<boolean> | (() => boolean)

/**
 * Keeps data fresh by re-running `fn` on an interval — no manual reload needed.
 *
 * - Skips ticks while the browser tab is hidden (saves battery and API calls)
 * - Skips ticks while `paused` is true (e.g. a modal is open or a save is in flight)
 * - Never overlaps: a tick is skipped if the previous run hasn't finished yet
 *
 * Pair it with a "silent" load (one that doesn't toggle the loading skeleton)
 * so background refreshes don't flash the UI.
 */
export function usePolling(fn: () => unknown, intervalMs = 15000, paused?: PauseCheck) {
  let timer: ReturnType<typeof setInterval> | null = null
  let inFlight = false

  function isPaused() {
    if (!paused) return false
    return typeof paused === 'function' ? paused() : paused.value
  }

  async function tick() {
    if (inFlight || isPaused()) return
    if (typeof document !== 'undefined' && document.hidden) return
    inFlight = true
    try {
      await fn()
    } finally {
      inFlight = false
    }
  }

  onMounted(() => {
    timer = setInterval(() => void tick(), intervalMs)
  })

  onBeforeUnmount(() => {
    if (timer) clearInterval(timer)
    timer = null
  })
}
