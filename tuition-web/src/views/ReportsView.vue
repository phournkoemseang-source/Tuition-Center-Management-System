<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import PageHeader from '../components/PageHeader.vue'
import StatusBadge from '../components/StatusBadge.vue'
import EmptyState from '../components/EmptyState.vue'
import AppIcon from '../components/AppIcon.vue'
import DonutChart from '../components/charts/DonutChart.vue'
import BarList from '../components/charts/BarList.vue'
import { errorMessage } from '../services/api'
import { reportService } from '../services/reports'
import type { MonthlyReport, ReportRow } from '../services/reports'
import { formatDate, money } from '../utils/format'

const now = new Date()
const year = ref(now.getFullYear())
const month = ref(now.getMonth() + 1)

const months = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December',
]

const report = ref<MonthlyReport | null>(null)
const rows = ref<ReportRow[]>([])
const loading = ref(true)
const exporting = ref(false)
const error = ref<string | null>(null)

const collectionRate = computed(() => {
  if (!report.value || report.value.totals.billed === 0) return null
  return Math.round((report.value.totals.collected / report.value.totals.billed) * 100)
})

const availableYears = computed(() => {
  const y = now.getFullYear()
  return [y, y - 1, y - 2]
})

const methodIcons: Record<string, import('../components/AppIcon.vue').IconName> = {
  cash: 'banknote',
  khqr: 'smartphone',
  aba: 'landmark',
  wing: 'smartphone',
  bank: 'landmark',
}

const donutSlices = computed(() =>
  report.value?.by_method.map((m) => {
    const colors: Record<string, string> = {
      cash: '#10b981',
      khqr: '#6366f1',
      aba: '#0ea5e9',
      wing: '#f59e0b',
      bank: '#8b5cf6',
      '-': '#cbd5e1',
    }
    return {
      label: m.method.toUpperCase(),
      value: Number(m.total),
      color: colors[m.method] ?? '#94a3b8',
    }
  }) ?? [],
)

const classBars = computed(() =>
  (report.value?.per_class ?? []).map((c) => ({
    label: c.name,
    sub: `${c.invoices} fees`,
    value: Number(c.collected_total),
    display: money(c.collected_total),
    tone: 'success' as const,
  })),
)

async function load() {
  loading.value = true
  error.value = null
  try {
    const [r, detail] = await Promise.all([
      reportService.monthly(year.value, month.value),
      reportService.monthlyStudents(year.value, month.value),
    ])
    report.value = r
    rows.value = detail
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    loading.value = false
  }
}

async function download() {
  exporting.value = true
  error.value = null
  try {
    await reportService.exportCsv(year.value, month.value)
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    exporting.value = false
  }
}

const fmtDate = formatDate

watch([year, month], load)
onMounted(load)
</script>

<template>
  <div>
    <PageHeader title="Monthly report" :subtitle="`How the center performed in ${months[month - 1]} ${year}.`">
      <div class="flex items-end gap-2">
        <select v-model.number="month" class="select w-auto">
          <option v-for="(m, i) in months" :key="m" :value="i + 1">{{ m }}</option>
        </select>
        <select v-model.number="year" class="select w-auto">
          <option v-for="y in availableYears" :key="y" :value="y">{{ y }}</option>
        </select>
        <button :disabled="exporting" class="btn btn-success" @click="download">
          <AppIcon name="download" :size="16" />
          {{ exporting ? 'Preparing…' : 'Export CSV' }}
        </button>
      </div>
    </PageHeader>

    <div v-if="loading" class="space-y-6">
      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div v-for="i in 4" :key="i" class="card card-pad space-y-3">
          <div class="skeleton h-4 w-24"></div>
          <div class="skeleton h-8 w-32"></div>
        </div>
      </div>
      <div class="card card-pad">
        <div class="skeleton h-48 w-full"></div>
      </div>
    </div>

    <div v-else-if="error" class="card border-red-200 bg-red-50 p-6 text-red-700">{{ error }}</div>

    <template v-else-if="report">
      <!-- Totals -->
      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="card card-pad">
          <p class="text-[13px] font-medium text-slate-500">Billed ({{ report.totals.invoice_count }} fees)</p>
          <p class="mt-1.5 text-[26px] font-bold leading-none tracking-tight text-slate-900">{{ money(report.totals.billed) }}</p>
        </div>
        <div class="card card-pad border-emerald-200 bg-emerald-50/60">
          <p class="text-[13px] font-medium text-emerald-600">Collected ({{ report.totals.paid_count }})</p>
          <p class="mt-1.5 text-[26px] font-bold leading-none tracking-tight text-emerald-700">{{ money(report.totals.collected) }}</p>
          <p v-if="collectionRate !== null" class="mt-2 flex items-center gap-1 text-xs font-medium text-emerald-500">
            <AppIcon name="trending-up" :size="13" /> {{ collectionRate }}% of billed
          </p>
        </div>
        <div class="card card-pad border-red-200 bg-red-50/60">
          <p class="text-[13px] font-medium text-red-600">Still owed</p>
          <p class="mt-1.5 text-[26px] font-bold leading-none tracking-tight text-red-700">{{ money(report.totals.outstanding) }}</p>
          <p class="mt-2 text-xs font-medium text-red-500">{{ report.totals.unpaid_count }} unpaid · {{ report.totals.overdue_count }} overdue</p>
        </div>
        <div class="card card-pad">
          <p class="mb-3 text-[13px] font-medium text-slate-500">How money arrived</p>
          <DonutChart
            :slices="donutSlices"
            :size="112"
            center-label="paid"
            :center-value="money(report.totals.collected)"
          />
        </div>
      </div>

      <!-- Per-class -->
      <div class="mt-6 grid gap-4 lg:grid-cols-2">
        <section class="card card-pad">
          <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-500">Collected by class</h2>
          <BarList :items="classBars" />
        </section>

        <section class="card">
          <div class="card-header">
            <h2 class="text-sm font-bold uppercase tracking-wide text-slate-500">By class</h2>
          </div>
          <div class="overflow-x-auto">
            <table class="table">
              <thead>
                <tr>
                  <th>Class</th>
                  <th>Billed</th>
                  <th>Collected</th>
                  <th>Owed</th>
                  <th>Overdue</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="c in report.per_class" :key="c.id">
                  <td class="font-medium text-slate-800">{{ c.name }}</td>
                  <td class="tabular-nums text-slate-600">{{ money(c.billed_total) }}</td>
                  <td class="font-semibold tabular-nums text-emerald-700">{{ money(c.collected_total) }}</td>
                  <td class="font-semibold tabular-nums text-red-600">{{ money(c.outstanding_total) }}</td>
                  <td>
                    <span v-if="c.overdue_count > 0" class="badge bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20">
                      {{ c.overdue_count }}
                    </span>
                    <span v-else class="text-slate-400">—</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </div>

      <!-- Detail lines -->
      <section class="card mt-6">
        <div class="card-header">
          <div>
            <h2 class="text-sm font-bold uppercase tracking-wide text-slate-500">Every fee, student by student</h2>
            <p class="mt-0.5 text-xs text-slate-400">This is exactly what the CSV export contains.</p>
          </div>
          <span class="badge bg-slate-100 text-slate-600">{{ rows.length }} rows</span>
        </div>
        <div class="overflow-x-auto">
          <table class="table">
            <thead>
              <tr>
                <th>Student</th>
                <th class="hidden md:table-cell">Class</th>
                <th>Amount</th>
                <th class="hidden lg:table-cell">Due</th>
                <th class="hidden lg:table-cell">Paid</th>
                <th>Status</th>
                <th class="hidden sm:table-cell">Method</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="rows.length === 0">
                <td colspan="7">
                  <EmptyState icon="clipboard-list" title="No fees were billed this month" />
                </td>
              </tr>
              <tr v-for="r in rows" :key="r.id">
                <td class="font-medium text-slate-800">{{ r.student }}</td>
                <td class="hidden text-slate-600 md:table-cell">{{ r.class }}</td>
                <td class="tabular-nums">{{ money(r.amount) }}</td>
                <td class="hidden text-slate-600 lg:table-cell">{{ fmtDate(r.due_date) }}</td>
                <td class="hidden text-slate-600 lg:table-cell">{{ fmtDate(r.paid_date) }}</td>
                <td><StatusBadge :status="r.status" /></td>
                <td class="hidden text-slate-600 sm:table-cell">
                  <span class="inline-flex items-center gap-1.5">
                    <AppIcon v-if="methodIcons[r.method]" :name="methodIcons[r.method]" :size="14" class="text-slate-400" />
                    {{ r.method === '-' ? '—' : r.method.toUpperCase() }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>
  </div>
</template>
