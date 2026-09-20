<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import PageHeader from '../components/PageHeader.vue'
import StatusBadge from '../components/StatusBadge.vue'
import EmptyState from '../components/EmptyState.vue'
import AppIcon from '../components/AppIcon.vue'
import LastUpdated from '../components/LastUpdated.vue'
import CountUp from '../components/CountUp.vue'
import DonutChart from '../components/charts/DonutChart.vue'
import BarList from '../components/charts/BarList.vue'
import { errorMessage } from '../services/api'
import {
  reportService,
  type AttendanceMonthlyReport,
  type MonthlyReport,
  type ReportRow,
} from '../services/reports'
import { usePolling } from '../composables/usePolling'
import { useLastUpdated } from '../composables/useLastUpdated'
import { formatDate, money } from '../utils/format'
import { downloadCsv, copyTsv } from '../utils/csv'
import { toast } from '../utils/toast'

const now = new Date()
const year = ref(now.getFullYear())
const month = ref(now.getMonth() + 1)

const months = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December',
]

const report = ref<MonthlyReport | null>(null)
const attendance = ref<AttendanceMonthlyReport | null>(null)
const rows = ref<ReportRow[]>([])
const loading = ref(true)
const exporting = ref(false)
const error = ref<string | null>(null)

const { lastUpdated, markUpdated } = useLastUpdated()

// Live board: numbers re-run their animations whenever fresh data lands
usePolling(() => load({ silent: true }), 30000)

// Bars slide in after first paint so the width transition actually plays
const barsIn = ref(false)
onMounted(() => requestAnimationFrame(() => requestAnimationFrame(() => (barsIn.value = true))))

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

const attendanceSlices = computed(() => {
  const t = attendance.value?.totals
  if (!t) return []
  return [
    { label: 'Present', value: t.present, color: '#10b981' },
    { label: 'Late', value: t.late, color: '#f59e0b' },
    { label: 'Absent', value: t.absent, color: '#ef4444' },
  ].filter((s) => s.value > 0)
})

const classBars = computed(() =>
  (report.value?.per_class ?? []).map((c) => ({
    label: c.name,
    sub: `${c.invoices} fees`,
    value: Number(c.collected_total),
    display: money(c.collected_total),
    tone: 'success' as const,
  })),
)

async function load(opts: { silent?: boolean } = {}) {
  const silent = opts.silent ?? false
  if (!silent) loading.value = true
  try {
    const [r, detail, att] = await Promise.all([
      reportService.monthly(year.value, month.value),
      reportService.monthlyStudents(year.value, month.value),
      reportService.attendanceMonthly(year.value, month.value),
    ])
    report.value = r
    rows.value = detail
    attendance.value = att
    error.value = null
    markUpdated()
  } catch (e) {
    if (!silent) error.value = errorMessage(e)
  } finally {
    if (!silent) loading.value = false
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

// ===== Export menu =====
const exportOpen = ref(false)
const exportPicker = ref<HTMLElement | null>(null)

function onDocClick(e: MouseEvent) {
  if (!exportOpen.value) return
  if (exportPicker.value && !exportPicker.value.contains(e.target as Node)) exportOpen.value = false
}

/** Fee detail via the backend (streams, handles big months). */
async function exportFees() {
  exportOpen.value = false
  await download()
}

/** Class money summary built from the data already on screen. */
function exportClassSummary() {
  exportOpen.value = false
  if (!report.value) return
  const rowsOut: (string | number | null)[][] = [
    ['Class', 'Invoices', 'Billed', 'Collected', 'Outstanding', 'Overdue fees'],
    ...report.value.per_class.map((c) => [c.name, c.invoices, c.billed_total, c.collected_total, c.outstanding_total, c.overdue_count]),
    [
      'TOTAL',
      report.value.totals.invoice_count,
      report.value.totals.billed,
      report.value.totals.collected,
      report.value.totals.outstanding,
      report.value.totals.overdue_count,
    ],
  ]
  downloadCsv(rowsOut, `class-summary-${year.value}-${String(month.value).padStart(2, '0')}.csv`)
}

/** Per-student attendance summary built from the data already on screen. */
function exportAttendanceSummary() {
  exportOpen.value = false
  const a = attendance.value
  if (!a) return
  const rowsOut: (string | number | null)[][] = [
    ['Student', 'Class', 'Present', 'Late', 'Absent', 'Attendance rate %'],
    ...a.per_student.map((r) => [r.student, r.class, r.present, r.late, r.absent, r.rate ?? '-']),
    ['TOTAL', '', a.totals.present, a.totals.late, a.totals.absent, a.totals.rate ?? '-'],
  ]
  downloadCsv(rowsOut, `attendance-${year.value}-${String(month.value).padStart(2, '0')}.csv`)
}

/** Copy the fee detail as TSV for Google Sheets. */
async function copyFeeDetail() {
  exportOpen.value = false
  const rowsOut: (string | number | null)[][] = [
    ['Student', 'Class', 'Amount', 'Due date', 'Paid date', 'Status', 'Method'],
    ...rows.value.map((r) => [r.student, r.class, r.amount, r.due_date, r.paid_date ?? '-', r.status, r.method]),
  ]
  if (await copyTsv(rowsOut)) {
    toast(`Copied ${rows.value.length} fee rows — paste into Sheets`)
  } else {
    toast('Could not access the clipboard', 'error')
  }
}

// ===== Per-class drill-down =====
const drillClassId = ref<number | null>(null)
const drillSearch = ref('')

interface DrillStudent {
  name: string
  fees: number
  billed: number
  paid: number
  owed: number
  present: number
  late: number
  absent: number
  rate: number | null
}

const drillClass = computed(() => report.value?.per_class.find((c) => c.id === drillClassId.value) ?? null)

/** Merge the month's fee lines and attendance marks for the drilled-into class, per student. */
const drillStudents = computed<DrillStudent[]>(() => {
  const cls = drillClass.value
  if (!cls) return []
  const byName = new Map<string, DrillStudent>()

  const ensure = (name: string): DrillStudent => {
    let s = byName.get(name)
    if (!s) {
      s = { name, fees: 0, billed: 0, paid: 0, owed: 0, present: 0, late: 0, absent: 0, rate: null }
      byName.set(name, s)
    }
    return s
  }

  for (const r of rows.value.filter((r) => r.class === cls.name)) {
    const s = ensure(r.student)
    s.fees += 1
    s.billed += Number(r.amount)
    if (r.status === 'paid') s.paid += Number(r.amount)
    else s.owed += Number(r.amount)
  }

  for (const p of attendance.value?.per_student.filter((p) => p.class === cls.name) ?? []) {
    const s = ensure(p.student)
    s.present = p.present
    s.late = p.late
    s.absent = p.absent
    s.rate = p.rate
  }

  return [...byName.values()].sort((a, b) => a.name.localeCompare(b.name))
})

const drillFiltered = computed(() => {
  const q = drillSearch.value.trim().toLowerCase()
  if (!q) return drillStudents.value
  return drillStudents.value.filter((s) => s.name.toLowerCase().includes(q))
})

/** Average attendance rate across rated students in the drilled class. */
const drillAvgRate = computed<number | null>(() => {
  const rated = drillStudents.value.filter((s) => s.rate !== null)
  if (rated.length === 0) return null
  return Math.round(rated.reduce((n, s) => n + (s.rate ?? 0), 0) / rated.length)
})

function openDrill(classId: number) {
  drillClassId.value = classId
  drillSearch.value = ''
}

function openDrillByName(name: string) {
  const c = report.value?.per_class.find((x) => x.name === name)
  if (c) openDrill(c.id)
}

function rateTone(rate: number | null): string {
  if (rate === null) return 'text-slate-400'
  if (rate >= 90) return 'text-emerald-600'
  if (rate >= 75) return 'text-amber-600'
  return 'text-red-600'
}

function exportDrillCsv() {
  const cls = drillClass.value
  if (!cls) return
  const rowsOut: (string | number | null)[][] = [
    [`${cls.name} — ${months[month.value - 1]} ${year.value}`],
    ['Student', 'Fees', 'Billed', 'Paid', 'Owed', 'Present', 'Late', 'Absent', 'Attendance %'],
    ...drillStudents.value.map((s) => [s.name, s.fees, s.billed.toFixed(2), s.paid.toFixed(2), s.owed.toFixed(2), s.present, s.late, s.absent, s.rate ?? '-']),
    [
      'TOTAL',
      drillStudents.value.reduce((n, s) => n + s.fees, 0),
      drillStudents.value.reduce((n, s) => n + s.billed, 0).toFixed(2),
      drillStudents.value.reduce((n, s) => n + s.paid, 0).toFixed(2),
      drillStudents.value.reduce((n, s) => n + s.owed, 0).toFixed(2),
      drillStudents.value.reduce((n, s) => n + s.present, 0),
      drillStudents.value.reduce((n, s) => n + s.late, 0),
      drillStudents.value.reduce((n, s) => n + s.absent, 0),
      '',
    ],
  ]
  downloadCsv(rowsOut, `${cls.name.toLowerCase().replace(/[^a-z0-9]+/g, '-')}-${year.value}-${String(month.value).padStart(2, '0')}.csv`)
}

async function copyDrill() {
  const cls = drillClass.value
  if (!cls) return
  const rowsOut: (string | number | null)[][] = [
    ['Student', 'Fees', 'Billed', 'Paid', 'Owed', 'Present', 'Late', 'Absent', 'Attendance %'],
    ...drillStudents.value.map((s) => [s.name, s.fees, s.billed.toFixed(2), s.paid.toFixed(2), s.owed.toFixed(2), s.present, s.late, s.absent, s.rate ?? '-']),
  ]
  if (await copyTsv(rowsOut)) {
    toast(`Copied ${drillStudents.value.length} students from ${cls.name}`)
  } else {
    toast('Could not access the clipboard', 'error')
  }
}

watch([year, month], () => {
  drillClassId.value = null
  load()
})
onMounted(() => {
  load()
  document.addEventListener('click', onDocClick)
})
onBeforeUnmount(() => document.removeEventListener('click', onDocClick))
</script>

<template>
  <div>
    <PageHeader :title="`${months[month - 1]} ${year} KPI board`" subtitle="How the center performed this month.">
      <LastUpdated :at="lastUpdated" />
      <select v-model.number="month" class="select w-auto">
        <option v-for="(m, i) in months" :key="m" :value="i + 1">{{ m }}</option>
      </select>
      <select v-model.number="year" class="select w-auto">
        <option v-for="y in availableYears" :key="y" :value="y">{{ y }}</option>
      </select>
      <div ref="exportPicker" class="relative">
        <button :disabled="exporting" class="btn btn-success" @click="exportOpen = !exportOpen">
          <AppIcon name="download" :size="16" />
          {{ exporting ? 'Preparing…' : 'Export' }}
          <AppIcon name="chevron-down" :size="13" />
        </button>
        <div
          v-if="exportOpen"
          class="absolute right-0 z-20 mt-2 w-60 rounded-xl border border-slate-200 bg-white p-1.5 shadow-pop"
        >
          <button
            class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left text-sm font-medium text-slate-700 transition hover:bg-slate-50"
            @click="exportFees"
          >
            <AppIcon name="clipboard-list" :size="14" class="text-slate-400" />
            Fee detail (Excel)
          </button>
          <button
            class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left text-sm font-medium text-slate-700 transition hover:bg-slate-50"
            @click="exportClassSummary"
          >
            <AppIcon name="bar-chart" :size="14" class="text-slate-400" />
            Class summary (Excel)
          </button>
          <button
            class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left text-sm font-medium text-slate-700 transition hover:bg-slate-50"
            @click="exportAttendanceSummary"
          >
            <AppIcon name="check-circle" :size="14" class="text-slate-400" />
            Attendance summary (Excel)
          </button>
          <button
            class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left text-sm font-medium text-slate-700 transition hover:bg-slate-50"
            @click="copyFeeDetail"
          >
            <AppIcon name="download" :size="14" class="text-slate-400" />
            Copy for Sheets
          </button>
        </div>
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
      <!-- ===== KPI board ===== -->
      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <!-- Billed -->
        <div class="card card-pad rise" style="animation-delay: 0ms">
          <p class="flex items-center gap-1.5 text-[13px] font-medium text-slate-500">
            <AppIcon name="clipboard-list" :size="14" class="text-slate-400" />
            Billed
          </p>
          <p class="mt-1.5 text-[28px] font-bold leading-none tracking-tight text-slate-900">
            <CountUp :value="report.totals.billed" :format="money" />
          </p>
          <p class="mt-2 text-xs font-medium text-slate-400">{{ report.totals.invoice_count }} fees issued</p>
        </div>

        <!-- Collected -->
        <div class="card card-pad rise border-emerald-200 bg-emerald-50/60" style="animation-delay: 70ms">
          <p class="flex items-center gap-1.5 text-[13px] font-medium text-emerald-600">
            <AppIcon name="wallet" :size="14" />
            Collected
          </p>
          <p class="mt-1.5 text-[28px] font-bold leading-none tracking-tight text-emerald-700">
            <CountUp :value="report.totals.collected" :format="money" />
          </p>
          <div class="mt-3 h-2 overflow-hidden rounded-full bg-emerald-100">
            <div
              class="h-full rounded-full bg-emerald-500 transition-all duration-1000 ease-out"
              :style="{ width: barsIn ? `${collectionRate ?? 0}%` : '0%' }"
            />
          </div>
          <p class="mt-2 flex items-center gap-1 text-xs font-medium text-emerald-500">
            <AppIcon name="trending-up" :size="13" />
            {{ collectionRate === null ? 'No fees billed yet' : `${collectionRate}% of billed` }}
          </p>
        </div>

        <!-- Still owed -->
        <div class="card card-pad rise border-red-200 bg-red-50/60" style="animation-delay: 140ms">
          <p class="flex items-center gap-1.5 text-[13px] font-medium text-red-600">
            <AppIcon name="alert-circle" :size="14" />
            Still owed
          </p>
          <p class="mt-1.5 text-[28px] font-bold leading-none tracking-tight text-red-700">
            <CountUp :value="report.totals.outstanding" :format="money" />
          </p>
          <p class="mt-2 flex gap-1.5 text-xs font-medium text-red-500">
            <span class="rounded-full bg-red-100 px-2 py-0.5">{{ report.totals.unpaid_count }} unpaid</span>
            <span class="rounded-full bg-red-100 px-2 py-0.5">{{ report.totals.overdue_count }} overdue</span>
          </p>
        </div>

        <!-- Attendance -->
        <div class="card card-pad rise" style="animation-delay: 210ms">
          <p class="flex items-center gap-1.5 text-[13px] font-medium text-slate-500">
            <AppIcon name="check-circle" :size="14" class="text-slate-400" />
            Attendance rate
          </p>
          <p class="mt-1.5 text-[28px] font-bold leading-none tracking-tight text-brand-700">
            <CountUp :value="attendance?.totals.rate ?? 0" :format="(n) => `${Math.round(n)}%`" />
          </p>
          <p class="mt-2 text-xs font-medium text-slate-400">
            <template v-if="attendance">
              {{ attendance.totals.present }} present · {{ attendance.totals.late }} late · {{ attendance.totals.absent }} absent
            </template>
          </p>
        </div>
      </div>

      <!-- ===== Charts ===== -->
      <div class="mt-6 grid gap-4 lg:grid-cols-3">
        <section class="card card-pad rise" style="animation-delay: 280ms">
          <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-500">Attendance mix</h2>
          <DonutChart
            :slices="attendanceSlices"
            :size="112"
            center-label="rate"
            :center-value="attendance?.totals.rate === null || attendance?.totals.rate === undefined ? '—' : `${attendance.totals.rate}%`"
          />
          <p class="mt-3 text-xs text-slate-400">
            {{ attendance?.totals.sessions ?? 0 }} class sessions held in {{ months[month - 1] }}.
          </p>
        </section>

        <section class="card card-pad rise" style="animation-delay: 350ms">
          <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-500">How money arrived</h2>
          <DonutChart
            :slices="donutSlices"
            :size="112"
            center-label="paid"
            :center-value="money(report.totals.collected)"
          />
        </section>

        <section class="card card-pad rise" style="animation-delay: 420ms">
          <h2 class="mb-1 text-sm font-bold uppercase tracking-wide text-slate-500">Collected by class</h2>
          <p class="mb-3 text-xs text-slate-400">Tap a class to see its students.</p>
          <BarList :items="classBars" selectable @select="(item) => openDrillByName(item.label)" />
        </section>
      </div>

      <!-- Per-class table -->
      <section class="card mt-6 rise" style="animation-delay: 490ms">
        <div class="card-header">
          <div>
            <h2 class="text-sm font-bold uppercase tracking-wide text-slate-500">Money by class</h2>
            <p class="mt-0.5 text-xs text-slate-400">Click a class to drill into its students.</p>
          </div>
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
              <tr
                v-for="c in report.per_class"
                :key="c.id"
                class="cursor-pointer transition hover:bg-brand-50/60"
                @click="openDrill(c.id)"
              >
                <td class="font-medium text-brand-700">{{ c.name }}</td>
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

      <!-- Detail lines -->
      <section class="card mt-6 rise" style="animation-delay: 560ms">
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

    <!-- ===== Per-class drill-down ===== -->
    <AppModal
      :open="drillClassId !== null"
      wide
      :title="drillClass?.name ?? 'Class'"
      :description="`${months[month - 1]} ${year} — students, fees and attendance in this class`"
      @close="drillClassId = null"
    >
      <div v-if="drillClass">
        <!-- Class summary strip -->
        <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
          <div class="rounded-xl bg-slate-50 px-3 py-2.5">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Students</p>
            <p class="text-lg font-bold tabular-nums text-slate-900">{{ drillStudents.length }}</p>
          </div>
          <div class="rounded-xl bg-emerald-50 px-3 py-2.5">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-emerald-500">Collected</p>
            <p class="text-lg font-bold tabular-nums text-emerald-700">{{ money(drillClass.collected_total) }}</p>
          </div>
          <div class="rounded-xl bg-red-50 px-3 py-2.5">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-red-400">Owed</p>
            <p class="text-lg font-bold tabular-nums text-red-600">{{ money(drillClass.outstanding_total) }}</p>
          </div>
          <div class="rounded-xl bg-brand-50 px-3 py-2.5">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-brand-400">Attendance</p>
            <p class="text-lg font-bold tabular-nums" :class="rateTone(drillAvgRate)">
              {{ drillAvgRate === null ? '—' : `${drillAvgRate}%` }}
            </p>
          </div>
        </div>

        <!-- Toolbar -->
        <div class="mb-3 flex flex-wrap items-center gap-2">
          <div class="relative min-w-0 flex-1">
            <AppIcon name="search" :size="15" class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
            <input
              v-model="drillSearch"
              type="search"
              placeholder="Find a student in this class…"
              class="input pl-10"
            />
          </div>
          <button class="btn btn-secondary btn-sm" @click="exportDrillCsv">
            <AppIcon name="download" :size="14" />
            Excel
          </button>
          <button class="btn btn-secondary btn-sm" @click="copyDrill">
            <AppIcon name="clipboard-list" :size="14" />
            Copy
          </button>
        </div>

        <!-- Per-student table -->
        <div class="max-h-[55vh] overflow-auto rounded-xl border border-slate-100">
          <table class="table">
            <thead class="sticky top-0 z-10 bg-white shadow-sm">
              <tr>
                <th>Student</th>
                <th class="text-right">Billed</th>
                <th class="text-right">Paid</th>
                <th class="text-right">Owed</th>
                <th class="text-center">P / L / A</th>
                <th class="text-right">Attend %</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="drillFiltered.length === 0">
                <td colspan="6">
                  <EmptyState
                    :icon="drillSearch ? 'search' : 'inbox'"
                    :title="drillSearch ? 'No student matches' : 'No activity for this class this month'"
                  />
                </td>
              </tr>
              <tr v-for="s in drillFiltered" :key="s.name">
                <td class="font-medium text-slate-800">{{ s.name }}</td>
                <td class="text-right tabular-nums text-slate-600">{{ money(s.billed) }}</td>
                <td class="text-right font-semibold tabular-nums text-emerald-700">{{ money(s.paid) }}</td>
                <td class="text-right tabular-nums" :class="s.owed > 0 ? 'font-semibold text-red-600' : 'text-slate-400'">
                  {{ s.owed > 0 ? money(s.owed) : '—' }}
                </td>
                <td class="text-center text-xs tabular-nums text-slate-600">
                  <span class="text-emerald-600">{{ s.present }}</span>
                  <span class="text-slate-300"> / </span>
                  <span class="text-amber-600">{{ s.late }}</span>
                  <span class="text-slate-300"> / </span>
                  <span class="text-red-600">{{ s.absent }}</span>
                </td>
                <td class="text-right font-semibold tabular-nums" :class="rateTone(s.rate)">
                  {{ s.rate === null ? '—' : `${s.rate}%` }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </AppModal>
  </div>
</template>

<style scoped>
.rise {
  animation: rise 0.55s cubic-bezier(0.2, 0.7, 0.3, 1) both;
}
@keyframes rise {
  from {
    opacity: 0;
    transform: translateY(14px) scale(0.985);
  }
  to {
    opacity: 1;
    transform: none;
  }
}
@media (prefers-reduced-motion: reduce) {
  .rise {
    animation: none;
  }
}
</style>
