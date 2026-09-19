<script setup lang="ts">
/**
 * Central inline SVG icon set (Lucide-style, stroke-based).
 * Usage: <AppIcon name="users" class="h-5 w-5" />
 */
import { computed } from 'vue'

export type IconName =
  | 'layout-dashboard'
  | 'users'
  | 'book-open'
  | 'check-circle'
  | 'wallet'
  | 'bar-chart'
  | 'log-out'
  | 'plus'
  | 'search'
  | 'x'
  | 'edit'
  | 'trash'
  | 'chevron-down'
  | 'chevron-left'
  | 'chevron-right'
  | 'chevrons-up-down'
  | 'chevron-up'
  | 'menu'
  | 'calendar'
  | 'clock'
  | 'alert-circle'
  | 'check'
  | 'download'
  | 'graduation-cap'
  | 'dollar-sign'
  | 'user-check'
  | 'clipboard-list'
  | 'trending-up'
  | 'credit-card'
  | 'banknote'
  | 'smartphone'
  | 'landmark'
  | 'arrow-left'
  | 'inbox'
  | 'eye'
  | 'eye-off'

const paths: Record<IconName, string[]> = {
  'layout-dashboard': [
    'M3 3h7v9H3z',
    'M14 3h7v5h-7z',
    'M14 12h7v9h-7z',
    'M3 16h7v5H3z',
  ],
  users: [
    'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2',
    'M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0z',
    'M22 21v-2a4 4 0 0 0-3-3.87',
    'M16 3.13a4 4 0 0 1 0 7.75',
  ],
  'book-open': [
    'M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z',
    'M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z',
  ],
  'check-circle': ['M22 11.08V12a10 10 0 1 1-5.93-9.14', 'M22 4 12 14.01l-3-3'],
  wallet: [
    'M21 12V7H5a2 2 0 0 1 0-4h14v4',
    'M3 5v14a2 2 0 0 0 2 2h16v-5',
    'M18 12a2 2 0 0 0 0 4h4v-4z',
  ],
  'bar-chart': ['M12 20V10', 'M18 20V4', 'M6 20v-4'],
  'log-out': ['M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4', 'M16 17l5-5-5-5', 'M21 12H9'],
  plus: ['M5 12h14', 'M12 5v14'],
  search: ['M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16z', 'M21 21l-4.35-4.35'],
  x: ['M18 6 6 18', 'M6 6l12 12'],
  edit: [
    'M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5z',
  ],
  trash: [
    'M3 6h18',
    'M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6',
    'M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2',
    'M10 11v6',
    'M14 11v6',
  ],
  'chevron-down': ['M6 9l6 6 6-6'],
  'chevron-left': ['M15 18l-6-6 6-6'],
  'chevron-right': ['M9 18l6-6-6-6'],
  'chevrons-up-down': ['M7 15l5 5 5-5', 'M7 9l5-5 5 5'],
  'chevron-up': ['M18 15l-6-6-6 6'],
  menu: ['M4 6h16', 'M4 12h16', 'M4 18h16'],
  calendar: [
    'M8 2v4',
    'M16 2v4',
    'M3 10h18',
    'M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z',
  ],
  clock: ['M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20z', 'M12 6v6l4 2'],
  'alert-circle': [
    'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20z',
    'M12 8v4',
    'M12 16h.01',
  ],
  check: ['M20 6 9 17l-5-5'],
  download: ['M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4', 'M7 10l5 5 5-5', 'M12 15V3'],
  'graduation-cap': [
    'M22 10 12 5 2 10l10 5 10-5z',
    'M6 12v5c0 1.7 2.7 3 6 3s6-1.3 6-3v-5',
  ],
  'dollar-sign': ['M12 2v20', 'M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6'],
  'user-check': [
    'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2',
    'M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0z',
    'M16 11l2 2 4-4',
  ],
  'clipboard-list': [
    'M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2',
    'M9 2h6a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1H9a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1z',
    'M9 12h6',
    'M9 16h6',
  ],
  'trending-up': ['M22 7l-8.5 8.5-5-5L2 17', 'M16 7h6v6'],
  'credit-card': [
    'M2 5h20v14H2z',
    'M2 10h20',
  ],
  banknote: [
    'M2 6h20v12H2z',
    'M12 12a2 2 0 1 0 0-4 2 2 0 0 0 0 4z',
    'M6 12h.01',
    'M18 12h.01',
  ],
  smartphone: [
    'M7 2h10a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z',
    'M12 18h.01',
  ],
  landmark: [
    'M3 22h18',
    'M6 18v-7',
    'M10 18v-7',
    'M14 18v-7',
    'M18 18v-7',
    'M12 2 3 7v2h18V7z',
  ],
  'arrow-left': ['M19 12H5', 'M12 19l-7-7 7-7'],
  eye: [
    'M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z',
    'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z',
  ],
  'eye-off': [
    'M9.88 9.88a3 3 0 1 0 4.24 4.24',
    'M10.73 5.08A10.43 10.43 0 0 1 12 5c6.5 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68',
    'M6.61 6.61A13.526 13.526 0 0 0 2 12s3.5 7 10 7a9.74 9.74 0 0 0 5.39-1.61',
    'M2 2l20 20',
  ],
  inbox: [
    'M22 12h-6l-2 3h-4l-2-3H2',
    'M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z',
  ],
}

const props = withDefaults(defineProps<{ name: IconName; size?: number }>(), {
  size: 20,
})

const pathD = computed(() => paths[props.name] ?? [])
</script>

<template>
  <svg
    :width="size"
    :height="size"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="2"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
  >
    <path v-for="(d, i) in pathD" :key="i" :d="d" />
  </svg>
</template>
