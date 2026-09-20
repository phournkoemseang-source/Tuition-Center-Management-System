import { ref } from 'vue'

/** Tracks when a view's data was last refreshed (works for polling + manual loads). */
export function useLastUpdated() {
  const lastUpdated = ref<Date | null>(null)
  function markUpdated() {
    lastUpdated.value = new Date()
  }
  return { lastUpdated, markUpdated }
}
