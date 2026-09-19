<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ status: string }>()

const styles = computed(() => {
  switch (props.status) {
    case 'paid':
    case 'present':
    case 'active':
      return 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20'
    case 'late':
      return 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/20'
    case 'unpaid':
      return 'bg-sky-50 text-sky-700 ring-1 ring-inset ring-sky-600/20'
    case 'overdue':
    case 'absent':
    case 'inactive':
    case 'dropped':
      return 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20'
    default:
      return 'bg-slate-100 text-slate-600 ring-1 ring-inset ring-slate-500/20'
  }
})
</script>

<template>
  <span class="badge" :class="styles">
    <span class="badge-dot" aria-hidden="true" />
    {{ status }}
  </span>
</template>
