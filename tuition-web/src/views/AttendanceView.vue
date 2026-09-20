<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import PageHeader from '../components/PageHeader.vue'
import Avatar from '../components/Avatar.vue'
import EmptyState from '../components/EmptyState.vue'
import AppModal from '../components/AppModal.vue'
import AppIcon from '../components/AppIcon.vue'
import CountUp from '../components/CountUp.vue'
import { errorMessage } from '../services/api'
import { classService } from '../services/tcms'
import { reportService, type AttendanceMonthlyReport } from '../services/reports'
import type { ClassRoom } from '../types'
import { toast } from '../utils/toast'
import { downloadCsv } from '../utils/csv'

const classes = ref<ClassRoom[]>([])
const selectedClassId = ref<number | ''>('')
const date = ref(new Date().toISOString().slice(0, 10))
const loading = ref(true)
const saving = ref(false)
const error = ref<string | null>(null)
const saved = ref(false)
const dirty = ref(false)

interface Row {
  studentId: number
  name: string
  status: 'present' | 'absent' | 'late'
}

const rows = ref<Row[]>([])

const selectedClass = computed(() => classes.value.find((c) => c.id === selectedClassId.value))

const counts = computed(() => ({
  present: rows.value.filter((r) => r.status === 'present').length,
  late: rows.value.filter((r) => r.status === 'late').length,
  absent: rows.value.filter((r) => r.status === 'absent').length,
}))

const statusOptions: { value: Row['status']; label: string; on: string; off: string }[] = [
  { value: 'present', label: 'Present', on: 'bg-emerald-600 text-white shadow-sm', off: 'text-emerald-700 bg-emerald-50 hover:bg-emerald-100' },
  { value: 'late', label: 'Late', on: 'bg-amber-500 text-white shadow-sm', off: 'text-amber-700 bg-amber-50 hover:bg-amber-100' },
  { value: 'absent', label: 'Absent', on: 'bg-red-600 text-white shadow-sm', off: 'text-red-700 bg-red-50 hover:bg-red-100' },
]

// Instant search + multi-select for fast marking of big rosters
const rosterSearch = ref('')
const selected = ref<number[]>([])

/** Rows filtered by the search box, as you type. */
const filteredRows = computed(() => {
  const q = rosterSearch.value.trim().toLowerCase()
  if (!q) return rows.value
  return rows.value.filter((r) => r.name.toLowerCase().includes(q))
})

const allShownSelected = computed(
  () => filteredRows.value.length > 0 && filteredRows.value.every((r) => selected.value.includes(r.studentId)),
)

function toggleSelectAllShown() {
  const ids = filteredRows.value.map((r) => r.studentId)
  if (allShownSelected.value) {
    selected.value = selected.value.filter((id) => !ids.includes(id))
  } else {
    selected.value = [...new Set([...selected.value, ...ids])]
  }
}

function markSelected(status: Row['status']) {
  const n = selected.value.length
  if (n === 0) return
  rows.value = rows.value.map((r) => (selected.value.includes(r.studentId) ? { ...r, status } : r))
  selected.value = []
  dirty.value = true
  toast(`${n} ${n === 1 ? 'student' : 'students'} marked ${status}`)
}

async function loadClasses() {
  loading.value = true
  error.value = null
  try {
    classes.value = await classService.list()
    if (classes.value.length > 0) {
      selectedClassId.value = classes.value[0]!.id
      await loadSheet()
    }
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    loading.value = false
  }
}

async function loadSheet() {
  if (!selectedClassId.value) return
  try {
    const existing = await classService.attendance(Number(selectedClassId.value), date.value)
    const roster = selectedClass.value?.students ?? []
    rows.value = roster.map((s) => ({
      studentId: s.id,
      name: s.full_name,
      status: (existing[String(s.id)] as Row['status']) ?? 'present',
    }))
    selected.value = []
    saved.value = false
    dirty.value = false
  } catch (e) {
    error.value = errorMessage(e)
  }
}

async function saveSheet() {
  saving.value = true
  error.value = null
  try {
    await classService.saveAttendance(
      Number(selectedClassId.value),
      date.value,
      rows.value.map((r) => ({ student_id: r.studentId, status: r.status })),
    )
    saved.value = true
    dirty.value = false
    setTimeout(() => (saved.value = false), 3000)
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    saving.value = false
  }
}

function markAllPresent() {
  rows.value = rows.value.map((r) => ({ ...r, status: 'present' as const }))
  dirty.value = true
}

// ----- Monthly attendance report (permanent, saved data) -----
const showMonthly = ref(false)
const monthlyYear = ref(new Date().getFullYear())
const monthlyMonth = ref(new Date().getMonth() + 1)
const monthly = ref<AttendanceMonthlyReport | null>(null)
const monthlyLoading = ref(false)
const monthlyError = ref<string | null>(null)

const monthNames = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December',
]
const monthlyYears = [new Date().getFullYear(), new Date().getFullYear() - 1, new Date().getFullYear() - 2]

async function openMonthly() {
  showMonthly.value = true
  await loadMonthly()
}

async function loadMonthly() {
  monthlyLoading.value = true
  monthlyError.value = null
  try {
    monthly.value = await reportService.attendanceMonthly(monthlyYear.value, monthlyMonth.value)
  } catch (e) {
    monthlyError.value = errorMessage(e)
  } finally {
    monthlyLoading.value = false
  }
}

function exportMonthlyCsv() {
  const m = monthly.value
  if (!m) return
  const rowsOut: (string | number | null)[][] = [
    ['Student', 'Class', 'Present', 'Late', 'Absent', 'Attendance rate %'],
    ...m.per_student.map((r) => [r.student, r.class, r.present, r.late, r.absent, r.rate ?? '-']),
    [],
    ['Total', '', m.totals.present, m.totals.late, m.totals.absent, m.totals.rate ?? '-'],
  ]
  downloadCsv(rowsOut, `attendance-${monthlyYear.value}-${String(monthlyMonth.value).padStart(2, '0')}.csv`)
  toast('Monthly attendance exported to CSV')
}

onMounted(loadClasses)
</script>

<template>
  <div>
    <PageHeader title="Attendance" subtitle="Pick a class and date, tap each student's status, then save.">
      <div class="flex items-center gap-2">
        <button class="btn btn-secondary btn-sm" :disabled="rows.length === 0" @click="markAllPresent">
          <AppIcon name="check" :size="14" />
          Mark all present
        </button>
        <button class="btn btn-secondary btn-sm" @click="openMonthly">
          <AppIcon name="bar-chart" :size="14" />
          Monthly report
        </button>
      </div>
    </PageHeader>

    <div v-if="loading" class="space-y-4">
      <div class="card card-pad grid gap-4 sm:grid-cols-2">
        <div class="skeleton h-11 w-full"></div>
        <div class="skeleton h-11 w-full"></div>
      </div>
      <div class="card p-5 space-y-3">
        <div v-for="i in 5" :key="i" class="skeleton h-10 w-full"></div>
      </div>
    </div>

    <div v-else-if="error" class="card border-red-200 bg-red-50 p-6 text-red-700">{{ error }}</div>

    <EmptyState
      v-else-if="classes.length === 0"
      icon="book-open"
      title="No classes to take attendance for"
      description="Create a class and enroll students first."
    >
      <RouterLink to="/classes" class="btn btn-primary btn-sm">Go to classes</RouterLink>
    </EmptyState>

    <template v-else>
      <!-- Filters -->
      <div class="card card-pad mb-4 grid gap-4 sm:grid-cols-2">
        <div>
          <label class="label">Class</label>
          <select v-model="selectedClassId" class="select" @change="loadSheet">
            <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </div>
        <div>
          <label class="label">Date</label>
          <input v-model="date" type="date" class="input" @change="loadSheet" />
        </div>
      </div>

      <template v-if="selectedClass">
        <!-- Search + selection toolbar -->
        <div class="mb-4 flex flex-wrap items-center gap-2">
          <div class="relative w-full max-w-xs">
            <AppIcon name="search" :size="16" class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
            <input v-model="rosterSearch" type="search" placeholder="Search a student…" class="input pl-10" />
          </div>
          <button class="text-xs font-semibold text-brand-600 transition hover:text-brand-700" @click="toggleSelectAllShown">
            {{ allShownSelected ? 'Unselect shown' : 'Select all shown' }}
          </button>
          <button v-if="selected.length" class="text-xs font-medium text-slate-400 transition hover:text-slate-600" @click="selected = []">
            Clear selection
          </button>
        </div>

        <!-- Bulk mark bar -->
        <div v-if="selected.length" class="mb-4 flex flex-wrap items-center gap-2 rounded-xl border border-brand-200 bg-brand-50 px-4 py-3">
          <span class="text-sm font-bold text-brand-800">{{ selected.length }} selected</span>
          <div class="ml-auto flex flex-wrap gap-1.5">
            <button
              v-for="opt in statusOptions"
              :key="opt.value"
              class="rounded-lg px-3 py-1.5 text-xs font-semibold transition"
              :class="opt.off"
              @click="markSelected(opt.value)"
            >
              Mark {{ opt.label.toLowerCase() }}
            </button>
          </div>
        </div>

        <!-- Summary strip -->
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
          <p class="text-sm text-slate-500">
            <span class="font-semibold text-slate-800">{{ selectedClass.name }}</span>
            · {{ filteredRows.length === rows.length ? `${rows.length} students on roster` : `${filteredRows.length} of ${rows.length} shown` }}
          </p>
          <div class="flex gap-2 text-xs font-semibold">
            <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-emerald-700">{{ counts.present }} present</span>
            <span class="rounded-full bg-amber-50 px-2.5 py-1 text-amber-700">{{ counts.late }} late</span>
            <span class="rounded-full bg-red-50 px-2.5 py-1 text-red-700">{{ counts.absent }} absent</span>
          </div>
        </div>

        <!-- Roster -->
        <div class="card divide-y divide-slate-100">
          <div
            v-for="r in filteredRows"
            :key="r.studentId"
            class="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5 transition"
            :class="selected.includes(r.studentId) ? 'bg-brand-50/60' : 'hover:bg-slate-50/60'"
          >
            <div class="flex min-w-0 items-center gap-3">
              <input v-model="selected" type="checkbox" :value="r.studentId" class="h-4 w-4 shrink-0 accent-brand-600" />
              <Avatar :name="r.name" size="md" />
              <p class="truncate font-medium text-slate-800">{{ r.name }}</p>
            </div>
            <div class="flex gap-1.5">
              <button
                v-for="opt in statusOptions"
                :key="opt.value"
                class="rounded-lg px-3.5 py-1.5 text-xs font-semibold transition"
                :class="r.status === opt.value ? opt.on : opt.off"
                @click="r.status = opt.value; dirty = true"
              >
                {{ opt.label }}
              </button>
            </div>
          </div>
          <p v-if="rows.length > 0 && filteredRows.length === 0" class="px-5 py-8 text-center text-sm text-slate-400">
            No students match “{{ rosterSearch }}”
          </p>
          <EmptyState
            v-if="rows.length === 0"
            icon="users"
            title="No students enrolled in this class"
            description="Enroll students from the Classes page first."
          />
        </div>

        <!-- Sticky save bar -->
        <div class="sticky bottom-4 z-10 mt-4">
          <div class="card flex items-center justify-between gap-3 px-5 py-3.5 shadow-pop">
            <p class="text-sm text-slate-500">
              <span v-if="saved" class="flex items-center gap-1.5 font-semibold text-emerald-600">
                <AppIcon name="check-circle" :size="16" /> Attendance saved!
              </span>
              <span v-else-if="dirty" class="font-medium text-amber-600">Unsaved changes</span>
              <span v-else>All changes saved</span>
            </p>
            <button :disabled="saving || rows.length === 0" class="btn btn-primary" @click="saveSheet">
              {{ saving ? 'Saving…' : 'Save attendance' }}
            </button>
          </div>
        </div>
      </template>
    </template>

    <!-- Monthly attendance report -->
    <AppModal
      :open="showMonthly"
      title="Monthly attendance report"
      description="Aggregated from every saved session"
      @close="showMonthly = false"
    >
      <div class="mb-4 flex items-center gap-2">
        <select v-model.number="monthlyMonth" class="select" @change="loadMonthly">
          <option v-for="(m, i) in monthNames" :key="m" :value="i + 1">{{ m }}</option>
        </select>
        <select v-model.number="monthlyYear" class="select w-auto" @change="loadMonthly">
          <option v-for="y in monthlyYears" :key="y" :value="y">{{ y }}</option>
        </select>
        <button class="btn btn-secondary btn-sm ml-auto shrink-0" @click="exportMonthlyCsv">
          <AppIcon name="download" :size="14" />
          CSV
        </button>
      </div>

      <div v-if="monthlyLoading" class="space-y-2">
        <div v-for="i in 4" :key="i" class="skeleton h-10 w-full"></div>
      </div>

      <p v-else-if="monthlyError" class="rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700">{{ monthlyError }}</p>

      <template v-else-if="monthly">
        <div class="mb-3 grid grid-cols-4 gap-2 text-center">
          <div class="rounded-xl bg-emerald-50 p-3">
            <p class="text-xl font-bold text-emerald-700"><CountUp :value="monthly.totals.present" /></p>
            <p class="text-[11px] font-medium text-emerald-600">Present</p>
          </div>
          <div class="rounded-xl bg-amber-50 p-3">
            <p class="text-xl font-bold text-amber-700"><CountUp :value="monthly.totals.late" /></p>
            <p class="text-[11px] font-medium text-amber-600">Late</p>
          </div>
          <div class="rounded-xl bg-red-50 p-3">
            <p class="text-xl font-bold text-red-700"><CountUp :value="monthly.totals.absent" /></p>
            <p class="text-[11px] font-medium text-red-600">Absent</p>
          </div>
          <div class="rounded-xl bg-brand-50 p-3">
            <p class="text-xl font-bold text-brand-700">
              <CountUp :value="monthly.totals.rate ?? 0" :format="(n) => `${Math.round(n)}%`" />
            </p>
            <p class="text-[11px] font-medium text-brand-600">Rate</p>
          </div>
        </div>
        <p class="mb-2 text-xs text-slate-400">
          {{ monthly.totals.sessions }} class sessions held · per-student summary below
        </p>

        <div class="max-h-72 overflow-x-auto overflow-y-auto rounded-xl border border-slate-200">
          <table class="table">
            <thead>
              <tr>
                <th>Student</th>
                <th class="hidden sm:table-cell">Class</th>
                <th class="text-center">P</th>
                <th class="text-center">L</th>
                <th class="text-center">A</th>
                <th class="text-right">Rate</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="monthly.per_student.length === 0">
                <td colspan="6" class="py-8 text-center text-sm text-slate-400">No attendance was saved this month.</td>
              </tr>
              <tr v-for="r in monthly.per_student" :key="r.id">
                <td class="font-medium text-slate-800">{{ r.student }}</td>
                <td class="hidden text-slate-500 sm:table-cell">{{ r.class }}</td>
                <td class="text-center font-semibold text-emerald-700">{{ r.present }}</td>
                <td class="text-center font-semibold text-amber-600">{{ r.late }}</td>
                <td class="text-center font-semibold text-red-600">{{ r.absent }}</td>
                <td class="text-right font-semibold tabular-nums">{{ r.rate === null ? '—' : `${r.rate}%` }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </AppModal>
  </div>
</template>
