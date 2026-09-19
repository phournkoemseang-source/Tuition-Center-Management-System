<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import PageHeader from '../components/PageHeader.vue'
import Avatar from '../components/Avatar.vue'
import EmptyState from '../components/EmptyState.vue'
import AppIcon from '../components/AppIcon.vue'
import { errorMessage } from '../services/api'
import { classService } from '../services/tcms'
import type { ClassRoom } from '../types'

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
        <!-- Summary strip -->
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
          <p class="text-sm text-slate-500">
            <span class="font-semibold text-slate-800">{{ selectedClass.name }}</span>
            · {{ rows.length }} students on roster
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
            v-for="r in rows"
            :key="r.studentId"
            class="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5 transition hover:bg-slate-50/60"
          >
            <div class="flex min-w-0 items-center gap-3">
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
  </div>
</template>
