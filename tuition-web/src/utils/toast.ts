/** Fire-and-forget toast notifications (rendered by ToastHost.vue). */
export type ToastKind = 'success' | 'error' | 'info'

export function toast(message: string, kind: ToastKind = 'success') {
  window.dispatchEvent(new CustomEvent('app:toast', { detail: { message, kind } }))
}
