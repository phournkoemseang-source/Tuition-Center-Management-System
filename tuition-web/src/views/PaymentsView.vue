<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import PageHeader from '../components/PageHeader.vue'
import Avatar from '../components/Avatar.vue'
import StatusBadge from '../components/StatusBadge.vue'
import EmptyState from '../components/EmptyState.vue'
import RecordPaymentModal from '../components/RecordPaymentModal.vue'
import AppIcon from '../components/AppIcon.vue'
import { errorMessage } from '../services/api'
import { paymentService } from '../services/tcms'
import type { Payment } from '../types'
import { formatDate, money } from '../utils/format'

const payments = ref<Payment[]>([])
const loading = ref(true)
const error = ref<string | null>(null)
const filter = ref<'all' | 'unpaid' | 'paid' | 'overdue'>('unpaid')
const search = ref('')
const searchTimer = ref<ReturnType<typeof setTimeout> | null>(null)
const paying = ref<Payment | null>(null)

const tabs = [
  { value: 'unpaid', label: 'Not paid yet' },
  { value: 'overdue', label: 'Overdue' },
  { value: 'paid', label: 'Paid' },
  { value: 'all', label: 'All' },
] as const

const totalDue = computed(() =>
  payments.value.filter((p) => p.status !== 'paid').reduce((s, p) => s + Number(p.amount), 0),
)
const totalPaid = computed(() =>
  payments.value.filter((p) => p.status === 'paid').reduce((s, p) => s + Number(p.amount), 0),
)

const fmtDate = formatDate

async function load() {
  loading.value = true
  error.value = null
  try {
    payments.value = await paymentService.list(filter.value, search.value)
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    loading.value = false
  }
}

function setFilter(value: typeof filter.value) {
  filter.value = value
  load()
}

function debouncedSearch() {
  if (searchTimer.value) clearTimeout(searchTimer.value)
  searchTimer.value = setTimeout(load, 300)
}

onMounted(load)
</script>

<template>
  <div>
    <PageHeader title="Payments" subtitle="Track who has paid this month and record payments in one tap." />

    <!-- Filter tabs + search -->
    <div class="mb-4 flex flex-wrap items-center gap-2">
      <div class="flex rounded-xl border border-slate-200 bg-white p-1 shadow-sm">
        <button
          v-for="t in tabs"
          :key="t.value"
          class="rounded-lg px-3.5 py-1.5 text-sm font-medium transition"
          :class="filter === t.value ? 'bg-brand-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100'"
          @click="setFilter(t.value)"
        >
          {{ t.label }}
        </button>
      </div>

      <div class="relative ml-auto w-full max-w-xs">
        <AppIcon name="search" :size="16" class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
        <input
          v-model="search"
          type="search"
          placeholder="Search student…"
          class="input pl-10"
          @input="debouncedSearch"
        />
      </div>
    </div>

    <!-- Summary chips -->
    <div class="mb-4 flex flex-wrap gap-3">
      <span class="inline-flex items-center gap-2 rounded-full bg-white px-3.5 py-1.5 text-xs font-semibold text-slate-600 ring-1 ring-slate-200">
        <AppIcon name="clipboard-list" :size="13" class="text-slate-400" />
        {{ payments.length }} fees shown
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
          <tr v-if="payments.length === 0">
            <td colspan="6">
              <EmptyState
                icon="wallet"
                title="Nothing here"
                description="Switch tabs or clear the search to see other fees."
              />
            </td>
          </tr>
          <tr v-for="p in payments" :key="p.id">
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
