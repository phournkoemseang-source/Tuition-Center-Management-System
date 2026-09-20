/** Fire-and-forget toast notifications (rendered by ToastHost.vue). */
export type ToastKind = 'success' | 'error' | 'info'

export function toast(message: string, kind: ToastKind = 'success') {
  window.dispatchEvent(new CustomEvent('app:toast', { detail: { message, kind } }))
}

/** Options for the large center-bottom banner toast. */
export interface BannerToastOptions {
  message: string
  kind?: ToastKind
  /** Optional action button shown inside the banner (e.g. "Enroll in class"). */
  actionLabel?: string
  onAction?: () => void
  /** Auto-dismiss delay in ms (default 6000). */
  duration?: number
}

/** Large center-bottom banner for events the teacher must not miss (e.g. new student). */
export function bannerToast(options: BannerToastOptions) {
  window.dispatchEvent(new CustomEvent('app:banner-toast', { detail: options }))
}
