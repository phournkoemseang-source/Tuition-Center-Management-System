<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import PageHeader from '../components/PageHeader.vue'
import Avatar from '../components/Avatar.vue'
import StatusBadge from '../components/StatusBadge.vue'
import EmptyState from '../components/EmptyState.vue'
import RecordPaymentModal from '../components/RecordPaymentModal.vue'
import LastUpdated from '../components/LastUpdated.vue'
import AppIcon from '../components/AppIcon.vue'
import { errorMessage } from '../services/api'
import { paymentService } from '../services/tcms'
import type { Payment } from '../types'
import { formatDate, money } from '../utils/format'
import { usePolling } from '../composables/usePolling'
import { useLastUpdated } from '../composables/useLastUpdated'

const payments = ref<Payment[]>([])
const loading = ref(true)
const error = ref<string | null>(null)
const search = ref('')
const searchTimer = ref<ReturnType<typeof setTimeout> | null>(null)
const paying = ref<Payment | null>(null)

// Status filter — searchable multi-select dropdown instead of fixed tabs.
// Status changes apply instantly (client-side); only the name search hits the server.
const selectedStatuses = ref<Payment['status'][]>(['unpaid'])
const statusOpen = ref(false)
const statusPicker = ref<HTMLElement | null>(null)

const statusFilters: { value: Payment['status']; label: string }[] = [
  { value: 'unpaid', label: 'Not paid yet' },
  { value: 'overdue', label: 'Overdue' },
  { value: 'paid', label: 'Paid' },
]

const statusCounts = computed(() => ({
  unpaid: payments.value.filter((p) => p.status === 'unpaid').length,
  overdue: payments.value.filter((p) => p.status === 'overdue').length,
  paid: payments.value.filter((p) => p.status === 'paid').length,
}))

const statusLabel = computed(() => {
  const sel = selectedStatuses.value
  if (sel.length === 0) return 'All statuses'
  if (sel.length === 1) return statusFilters.find((f) => f.value === sel[0])?.label ?? 'All statuses'
  return `${sel.length} statuses`
})

const filteredPayments = computed(() =>
  selectedStatuses.value.length === 0
    ? payments.value
    : payments.value.filter((p) => selectedStatuses.value.includes(p.status)),
)

const totalDue = computed(() =>
  filteredPayments.value.filter((p) => p.status !== 'paid').reduce((s, p) => s + Number(p.amount), 0),
)
const totalPaid = computed(() =>
  filteredPayments.value.filter((p) => p.status === 'paid').reduce((s, p) => s + Number(p.amount), 0),
)

const fmtDate = formatDate

async function load(opts: { silent?: boolean } = {}) {
  const silent = opts.silent ?? false
  if (!silent) loading.value = true
  try {
    payments.value = await paymentService.list('', search.value)
    error.value = null
    markUpdated()
  } catch (e) {
    // Silent background refreshes keep the current list on screen on failure
    if (!silent) error.value = errorMessage(e)
  } finally {
    if (!silent) loading.value = false
  }
}

// Auto-refresh every 15s, paused while the record-payment modal is open
usePolling(
  () => load({ silent: true }),
  15000,
  () => paying.value !== null,
)

const { lastUpdated, markUpdated } = useLastUpdated()

function debouncedSearch() {
  if (searchTimer.value) clearTimeout(searchTimer.value)
  searchTimer.value = setTimeout(load, 300)
}

// Close the status dropdown when clicking anywhere else
function onDocClick(e: MouseEvent) {
  if (!statusOpen.value) return
  if (statusPicker.value && !statusPicker.value.contains(e.target as Node)) statusOpen.value = false
}

onMounted(() => {
  load()
  document.addEventListener('click', onDocClick)
})
onBeforeUnmount(() => document.removeEventListener('click', onDocClick))
</script>

<template>
  <div>
    <PageHeader title="Payments" subtitle="Track who has paid this month and record payments in one tap." />

    <!-- Search + status filter -->
    <div class="mb-4 flex flex-wrap items-center gap-2">
      <div class="relative w-full max-w-xs">
        <AppIcon name="search" :size="16" class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
        <input
          v-model="search"
          type="search"
          placeholder="Search student…"
          class="input pl-10"
          @input="debouncedSearch"
        />
      </div>

      <div ref="statusPicker" class="relative">
        <button class="btn btn-secondary btn-sm" @click="statusOpen = !statusOpen">
          <AppIcon name="clipboard-list" :size="14" />
          {{ statusLabel }}
          <AppIcon name="chevron-down" :size="13" />
        </button>

        <div
          v-if="statusOpen"
          class="absolute left-0 z-20 mt-2 w-60 rounded-xl border border-slate-200 bg-white p-2 shadow-pop"
        >
          <p class="px-2 pb-1 pt-1 text-[11px] font-bold uppercase tracking-wide text-slate-400">Filter by status</p>
          <label
            v-for="opt in statusFilters"
            :key="opt.value"
            class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
          >
            <input v-model="selectedStatuses" type="checkbox" :value="opt.value" class="h-4 w-4 shrink-0 accent-brand-600" />
            {{ opt.label }}
            <span class="ml-auto text-xs font-semibold text-slate-400">{{ statusCounts[opt.value] }}</span>
          </label>
          <div class="mt-1 flex items-center justify-between border-t border-slate-100 px-2 pt-2">
            <button class="text-xs font-semibold text-brand-600 transition hover:text-brand-700" @click="selectedStatuses = []">
              All statuses
            </button>
            <span class="text-xs text-slate-400">
              {{ selectedStatuses.length ? `${selectedStatuses.length} selected` : 'showing all' }}
            </span>
          </div>
        </div>
      </div>

      <LastUpdated :at="lastUpdated" class="ml-auto" />
    </div>

    <!-- Summary chips -->
    <div class="mb-4 flex flex-wrap gap-3">
      <span class="inline-flex items-center gap-2 rounded-full bg-white px-3.5 py-1.5 text-xs font-semibold text-slate-600 ring-1 ring-slate-200">
        <AppIcon name="clipboard-list" :size="13" class="text-slate-400" />
        {{ filteredPayments.length }} fees shown
      </span>
      <span v-if="totalDue > 0" class="inline-flex items-center gap-2 rounded-full bg-red-50 px-3.5 py-1.5 text-xs font-semibold text-red-700 ring-1 ring-inset ring-red-600/20">
        <AppIcon name="alert-circle" :size="13" />
        {{ money(totalDue) }} outstanding
      </span>
      <span v-if="totalPaid > 0" class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3.5 py-1.5 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
        <AppIcon name="check-circle" :size="13" />
        {{ money(totalPaid) }} collected
      </span>
    </div>

    <!-- Loading skeleton -->
    <div v-if="loading" class="card p-5">
      <div v-for="i in 6" :key="i" class="flex items-center gap-4 border-b border-slate-100 py-3.5 last:border-0">
        <div class="skeleton h-9 w-9 rounded-full"></div>
        <div class="skeleton h-4 flex-1"></div>
        <div class="skeleton h-4 w-20"></div>
        <div class="skeleton h-6 w-16 rounded-full"></div>
        <div class="skeleton h-8 w-28 rounded-lg"></div>
      </div>
    </div>

    <div v-else-if="error" class="card border-red-200 bg-red-50 p-6 text-red-700">{{ error }}</div>

    <!-- Table -->
    <div v-else class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Student</th>
            <th class="hidden md:table-cell">Class</th>
            <th>Amount</th>
            <th class="hidden lg:table-cell">Due</th>
            <th>Status</th>
            <th class="text-right">Action</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="filteredPayments.length === 0">
            <td colspan="6">
              <EmptyState
                icon="wallet"
                title="Nothing here"
                description="Clear the search or status filter to see other fees."
              />
            </td>
          </tr>
          <tr v-for="p in filteredPayments" :key="p.id">
            <td>
              <div class="flex items-center gap-3">
                <Avatar :name="p.student.full_name" size="md" />
                <div class="min-w-0">
                  <p class="truncate font-semibold text-slate-800">{{ p.student.full_name }}</p>
                  <p class="truncate text-xs text-slate-400 md:hidden">{{ p.classRoom.name }}</p>
                </div>
              </div>
            </td>
            <td class="hidden text-slate-600 md:table-cell">{{ p.classRoom.name }}</td>
            <td class="font-semibold tabular-nums text-slate-800">{{ money(p.amount) }}</td>
            <td class="hidden text-slate-600 lg:table-cell">{{ fmtDate(p.due_date) }}</td>
            <td>
              <StatusBadge :status="p.status" />
              <p v-if="p.paid_date" class="mt-0.5 text-[11px] text-slate-400">{{ fmtDate(p.paid_date) }}</p>
            </td>
            <td>
              <div class="flex justify-end">
                <button
                  v-if="p.status !== 'paid'"
                  class="btn btn-success btn-sm"
                  @click="paying = p"
                >
                  <AppIcon name="banknote" :size="14" />
                  Record payment
                </button>
                <span v-else class="inline-flex items-center gap-1 text-xs font-medium uppercase tracking-wide text-slate-400">
                  <AppIcon name="check" :size="13" />
                  {{ p.method ?? 'paid' }}
                </span>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <RecordPaymentModal :payment="paying" @close="paying = null" @saved="paying = null; load()" />
  </div>
</template>
