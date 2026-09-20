<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import PageHeader from '../components/PageHeader.vue'
import Avatar from '../components/Avatar.vue'
import AppIcon from '../components/AppIcon.vue'
import LastUpdated from '../components/LastUpdated.vue'
import { errorMessage } from '../services/api'
import { classService, teacherService } from '../services/tcms'
import type { ClassRoom, Teacher } from '../types'
import { money } from '../utils/format'
import { toast } from '../utils/toast'
import { usePolling } from '../composables/usePolling'
import { useLastUpdated } from '../composables/useLastUpdated'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()

const teachers = ref<Teacher[]>([])
const classes = ref<ClassRoom[]>([])
const loading = ref(true)
const error = ref<string | null>(null)
const search = ref('')
const tab = ref<'teachers' | 'classes' | 'week'>('teachers')

// Class id currently being reassigned (for select busy state)
const reassigning = ref<number | null>(null)

const { lastUpdated, markUpdated } = useLastUpdated()

// Auto-refresh every 15s, paused while a reassign request is in flight
usePolling(() => load({ silent: true }), 15000, () => reassigning.value !== null)

async function load(opts: { silent?: boolean } = {}) {
  const silent = opts.silent ?? false
  if (!silent) loading.value = true
  try {
    const [t, c] = await Promise.all([teacherService.list(), classService.list()])
    teachers.value = t
    classes.value = c
    error.value = null
    markUpdated()
  } catch (e) {
    if (!silent) error.value = errorMessage(e)
  } finally {
    if (!silent) loading.value = false
  }
}

const q = computed(() => search.value.trim().toLowerCase())

const filteredTeachers = computed(() => {
  if (!q.value) return teachers.value
  return teachers.value.filter(
    (t) =>
      t.full_name.toLowerCase().includes(q.value) ||
      t.subject.toLowerCase().includes(q.value) ||
      (t.classes ?? []).some((c) => c.name.toLowerCase().includes(q.value)),
  )
})

const filteredClasses = computed(() => {
  if (!q.value) return classes.value
  return classes.value.filter(
    (c) =>
      c.name.toLowerCase().includes(q.value) ||
      c.schedule.toLowerCase().includes(q.value) ||
      (c.teacher?.full_name ?? '').toLowerCase().includes(q.value),
  )
})

// ===== Week view: map free-form schedule strings onto weekdays =====
const DAYS = [
  { key: 'mon', label: 'Mon', re: /\bmon[a-z]*\b/i },
  { key: 'tue', label: 'Tue', re: /\btue[a-z]*\b/i },
  { key: 'wed', label: 'Wed', re: /\bwed[a-z]*\b/i },
  { key: 'thu', label: 'Thu', re: /\bthu[a-z]*\b/i },
  { key: 'fri', label: 'Fri', re: /\bfri[a-z]*\b/i },
  { key: 'sat', label: 'Sat', re: /\bsat[a-z]*\b/i },
  { key: 'sun', label: 'Sun', re: /\bsun[a-z]*\b/i },
] as const

interface Slot {
  classId: number
  name: string
  teacher: string
  time: string | null
  schedule: string
}

/** Pull a readable time range out of the schedule text, e.g. "9:00–11:00". */
function parseSlot(c: ClassRoom): Slot {
  const m = /(\d{1,2}(?::\d{2})?\s*(?:am|pm)?)(\s*[-–]\s*(\d{1,2}(?::\d{2})?\s*(?:am|pm)?))?/i.exec(c.schedule)
  return {
    classId: c.id,
    name: c.name,
    teacher: c.teacher?.full_name ?? 'No teacher',
    time: m ? m[0].trim() : null,
    schedule: c.schedule,
  }
}

const week = computed(() => {
  const slots = filteredClasses.value.map(parseSlot)
  return {
    days: DAYS.map((d) => ({ ...d, slots: slots.filter((s) => d.re.test(s.schedule)) })),
    unscheduled: slots.filter((s) => !DAYS.some((d) => d.re.test(s.schedule))),
  }
})

// JS getDay(): 0=Sun…6=Sat
const todayKey = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'][new Date().getDay()]

const tabs = [
  { key: 'teachers' as const, label: 'By teacher', icon: 'graduation-cap' as const },
  { key: 'classes' as const, label: 'By class', icon: 'book-open' as const },
  { key: 'week' as const, label: 'Week view', icon: 'calendar' as const },
]

async function reassign(cls: ClassRoom, teacherId: number) {
  if (!auth.isAdmin || teacherId === cls.teacher_id) return
  reassigning.value = cls.id
  try {
    await classService.update(cls.id, { teacher_id: teacherId })
    const name = teachers.value.find((t) => t.id === teacherId)?.full_name ?? 'a new teacher'
    toast(`${cls.name} reassigned to ${name}`)
    await load({ silent: true })
  } catch (e) {
    toast(errorMessage(e), 'error')
    await load({ silent: true }) // restore the select to the real value
  } finally {
    reassigning.value = null
  }
}

onMounted(() => load())
</script>

<template>
  <div>
    <PageHeader title="Assignments" :subtitle="`${teachers.length} teachers · ${classes.length} classes — who teaches what, and when`">
      <LastUpdated :at="lastUpdated" />
    </PageHeader>

    <!-- Toolbar -->
    <div class="mb-4 flex flex-wrap items-center gap-3">
      <div class="relative w-full max-w-sm">
        <AppIcon name="search" :size="16" class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
        <input v-model="search" type="search" placeholder="Search teacher, class or schedule…" class="input pl-10" />
      </div>
      <div class="inline-flex rounded-xl bg-slate-100 p-1">
        <button
          v-for="t in tabs"
          :key="t.key"
          class="inline-flex items-center gap-1.5 rounded-lg px-3.5 py-1.5 text-sm font-semibold transition"
          :class="tab === t.key ? 'bg-white text-brand-700 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
          @click="tab = t.key"
        >
          <AppIcon :name="t.icon" :size="14" />
          {{ t.label }}
        </button>
      </div>
      <p v-if="!auth.isAdmin" class="text-xs text-slate-400">Only admins can reassign teachers.</p>
    </div>

    <!-- Loading skeleton -->
    <div v-if="loading" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
      <div v-for="i in 3" :key="i" class="card card-pad space-y-3">
        <div class="flex items-center gap-3">
          <div class="skeleton h-10 w-10 rounded-full"></div>
          <div class="skeleton h-4 flex-1"></div>
        </div>
        <div class="skeleton h-12 w-full"></div>
        <div class="skeleton h-12 w-full"></div>
      </div>
    </div>

    <div v-else-if="error" class="card border-red-200 bg-red-50 p-6 text-red-700">{{ error }}</div>

    <!-- ===== By teacher ===== -->
    <div v-else-if="tab === 'teachers'" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
      <article
        v-for="t in filteredTeachers"
        :key="t.id"
        class="card flex flex-col p-5"
      >
        <div class="flex items-start justify-between gap-3">
          <div class="flex min-w-0 items-center gap-3">
            <Avatar :name="t.full_name" size="md" />
            <div class="min-w-0">
              <h2 class="truncate font-bold text-slate-900">{{ t.full_name }}</h2>
              <p class="truncate text-xs text-slate-500">{{ t.user?.email ?? 'no login email' }}</p>
            </div>
          </div>
          <span class="shrink-0 rounded-full bg-brand-50 px-2.5 py-1 text-xs font-bold text-brand-700">{{ t.subject }}</span>
        </div>

        <div v-if="(t.classes ?? []).length" class="mt-4 space-y-2.5">
          <div
            v-for="c in t.classes"
            :key="c.id"
            class="rounded-xl bg-slate-50 px-3 py-2.5"
          >
            <div class="flex items-center justify-between gap-2">
              <p class="flex min-w-0 items-center gap-1.5 text-sm font-semibold text-slate-800">
                <AppIcon name="book-open" :size="13" class="shrink-0 text-slate-400" />
                <span class="truncate">{{ c.name }}</span>
              </p>
              <span class="shrink-0 rounded-full bg-white px-2 py-0.5 text-[11px] font-bold text-slate-600 ring-1 ring-inset ring-slate-200">
                {{ c.active_students_count }} {{ c.active_students_count === 1 ? 'student' : 'students' }}
              </span>
            </div>
            <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
              <span class="inline-flex items-center gap-1">
                <AppIcon name="clock" :size="12" class="text-slate-400" />
                {{ c.schedule }}
              </span>
              <span class="inline-flex items-center gap-1 font-medium text-slate-600">
                <AppIcon name="dollar-sign" :size="12" class="text-slate-400" />
                {{ money(c.fee_amount) }}/mo
              </span>
            </div>
          </div>
        </div>

        <div v-else class="mt-4 flex items-center gap-2 rounded-xl border border-dashed border-slate-200 px-3 py-4 text-xs text-slate-400">
          <AppIcon name="inbox" :size="14" class="shrink-0" />
          No classes assigned yet — assign one via Classes → Edit.
        </div>

        <div class="mt-4 flex items-center gap-2 border-t border-slate-100 pt-3 text-xs font-semibold text-slate-500">
          <AppIcon name="check-square" :size="13" class="text-slate-400" />
          {{ (t.classes ?? []).length }} {{ (t.classes ?? []).length === 1 ? 'class' : 'classes' }}
          · {{ (t.classes ?? []).reduce((n, c) => n + c.active_students_count, 0) }} students
        </div>
      </article>

      <div v-if="filteredTeachers.length === 0" class="sm:col-span-2 xl:col-span-3">
        <div class="card p-6 text-center text-sm text-slate-400">No teachers match your search.</div>
      </div>
    </div>

    <!-- ===== By class ===== -->
    <section v-else-if="tab === 'classes'" class="card">
      <div class="overflow-x-auto">
        <table class="table">
          <thead>
            <tr>
              <th>Class</th>
              <th>Schedule</th>
              <th>Teacher</th>
              <th class="text-right">Students</th>
              <th class="text-right">Monthly fee</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="filteredClasses.length === 0">
              <td colspan="5" class="py-6 text-center text-sm text-slate-400">No classes match your search.</td>
            </tr>
            <tr v-for="c in filteredClasses" :key="c.id">
              <td class="font-medium text-slate-800">{{ c.name }}</td>
              <td>
                <span class="inline-flex items-center gap-1.5 text-slate-600">
                  <AppIcon name="clock" :size="13" class="text-slate-400" />
                  {{ c.schedule }}
                </span>
              </td>
              <td>
                <select
                  v-if="auth.isAdmin"
                  :value="c.teacher_id"
                  :disabled="reassigning === c.id"
                  class="select w-auto py-1 text-sm"
                  @change="reassign(c, Number(($event.target as HTMLSelectElement).value))"
                >
                  <option v-for="t in teachers" :key="t.id" :value="t.id">{{ t.full_name }}</option>
                </select>
                <span v-else class="inline-flex items-center gap-1.5 text-slate-700">
                  <AppIcon name="graduation-cap" :size="13" class="text-slate-400" />
                  {{ c.teacher?.full_name ?? '—' }}
                </span>
              </td>
              <td class="text-right tabular-nums text-slate-600">{{ c.active_students_count }}</td>
              <td class="text-right font-semibold tabular-nums text-slate-800">{{ money(c.fee_amount) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- ===== Week view ===== -->
    <div v-else class="space-y-4">
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        <section
          v-for="d in week.days"
          :key="d.key"
          class="card p-4"
          :class="d.key === todayKey ? 'border-brand-300 bg-brand-50/50' : ''"
        >
          <div class="mb-3 flex items-center justify-between">
            <h2 class="flex items-center gap-2 text-sm font-bold text-slate-800">
              {{ d.label }}
              <span
                v-if="d.key === todayKey"
                class="rounded-full bg-brand-600 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white"
              >Today</span>
            </h2>
            <span class="text-xs font-semibold text-slate-400">{{ d.slots.length }}</span>
          </div>
          <div v-if="d.slots.length" class="space-y-2">
            <div v-for="s in d.slots" :key="`${d.key}-${s.classId}`" class="rounded-xl bg-slate-50 px-3 py-2">
              <p class="truncate text-sm font-semibold text-slate-800">{{ s.name }}</p>
              <div class="mt-0.5 flex flex-wrap items-center gap-x-2.5 gap-y-0.5 text-xs text-slate-500">
                <span class="truncate">{{ s.teacher }}</span>
                <span v-if="s.time" class="inline-flex items-center gap-1 font-medium text-brand-600">
                  <AppIcon name="clock" :size="11" />
                  {{ s.time }}
                </span>
              </div>
            </div>
          </div>
          <p v-else class="rounded-xl border border-dashed border-slate-200 px-3 py-3 text-center text-xs text-slate-400">
            No classes
          </p>
        </section>
      </div>

      <section v-if="week.unscheduled.length" class="card p-4">
        <h2 class="mb-3 text-sm font-bold text-slate-800">No weekday found in schedule</h2>
        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
          <div v-for="s in week.unscheduled" :key="s.classId" class="rounded-xl bg-slate-50 px-3 py-2">
            <p class="truncate text-sm font-semibold text-slate-800">{{ s.name }}</p>
            <p class="mt-0.5 text-xs text-slate-500">{{ s.schedule }} · {{ s.teacher }}</p>
          </div>
        </div>
        <p class="mt-3 text-xs text-slate-400">Tip: include a day name in the schedule (e.g. “Sat/Sun 9:00–11:00”) so it appears on the right day.</p>
      </section>
    </div>
  </div>
</template>
