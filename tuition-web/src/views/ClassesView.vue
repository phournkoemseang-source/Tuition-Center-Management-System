<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import PageHeader from '../components/PageHeader.vue'
import Avatar from '../components/Avatar.vue'
import EmptyState from '../components/EmptyState.vue'
import AppModal from '../components/AppModal.vue'
import LastUpdated from '../components/LastUpdated.vue'
import AppIcon from '../components/AppIcon.vue'
import { errorMessage } from '../services/api'
import { classService, studentService, teacherService } from '../services/tcms'
import type { ClassRoom, Student, Teacher } from '../types'
import { money } from '../utils/format'
import { toast } from '../utils/toast'
import { usePolling } from '../composables/usePolling'
import { useLastUpdated } from '../composables/useLastUpdated'

const classes = ref<ClassRoom[]>([])
const students = ref<Student[]>([])
const loading = ref(true)
const error = ref<string | null>(null)
const saving = ref(false)
const actionError = ref<string | null>(null)

const showForm = ref(false)
const editing = ref<ClassRoom | null>(null)
const form = ref({ name: '', teacher_id: 0, schedule: '', fee_amount: 30 })

// enroll flow — multi-select with instant search (newest students first)
const enrollClass = ref<ClassRoom | null>(null)
const enrollStudentIds = ref<number[]>([])
const enrollSearch = ref('')
const enrolling = ref(false)

// remove student confirm
const removing = ref<{ c: ClassRoom; s: Student } | null>(null)
const removeBusy = ref(false)

const teachers = ref<Teacher[]>([])

const totalEnrollments = computed(() => classes.value.reduce((sum, c) => sum + c.active_students_count, 0))

async function load(opts: { silent?: boolean } = {}) {
  const silent = opts.silent ?? false
  if (!silent) loading.value = true
  try {
    const [c, s, t] = await Promise.all([classService.list(), studentService.list(), teacherService.list()])
    classes.value = c
    students.value = s.filter((st) => st.status === 'active')
    teachers.value = t
    error.value = null
    markUpdated()
    // Keep open modals in sync with the fresh data
    const open = enrollClass.value
    if (open) enrollClass.value = c.find((cl) => cl.id === open.id) ?? open
    const stillRoster = rosterClass.value
    if (stillRoster) rosterClass.value = c.find((cl) => cl.id === stillRoster.id) ?? stillRoster
  } catch (e) {
    // Silent background refreshes keep the current view on screen on failure
    if (!silent) error.value = errorMessage(e)
  } finally {
    if (!silent) loading.value = false
  }
}

// Auto-refresh every 15s, paused while any modal is open or a save is in flight
usePolling(
  () => load({ silent: true }),
  15000,
  () => showForm.value || enrollClass.value !== null || removing.value !== null || rosterClass.value !== null || saving.value || enrolling.value || removeBusy.value,
)

const { lastUpdated, markUpdated } = useLastUpdated()

function openAdd() {
  editing.value = null
  form.value = { name: '', teacher_id: 0, schedule: '', fee_amount: 30 }
  actionError.value = null
  showForm.value = true
}

function openEdit(c: ClassRoom) {
  editing.value = c
  form.value = { name: c.name, teacher_id: c.teacher_id, schedule: c.schedule, fee_amount: Number(c.fee_amount) }
  actionError.value = null
  showForm.value = true
}

async function save() {
  saving.value = true
  actionError.value = null
  try {
    if (editing.value) {
      await classService.update(editing.value.id, form.value)
      toast('Class updated')
    } else {
      await classService.create(form.value)
      toast('Class created')
    }
    showForm.value = false
    await load()
  } catch (e) {
    actionError.value = errorMessage(e)
  } finally {
    saving.value = false
  }
}

// roster flow — big classes stay friendly: peek a few avatars on the card,
// and search + paginate the full roster in a modal instead of listing everyone
const rosterClass = ref<ClassRoom | null>(null)
const rosterSearch = ref('')
const rosterPage = ref(1)
const rosterPerPage = 8

const rosterStudents = computed(() => {
  const cls = rosterClass.value
  if (!cls) return []
  const q = rosterSearch.value.trim().toLowerCase()
  const list = q ? cls.students.filter((s) => s.full_name.toLowerCase().includes(q)) : cls.students
  return [...list].sort((a, b) => a.full_name.localeCompare(b.full_name))
})
const rosterTotalPages = computed(() => Math.max(1, Math.ceil(rosterStudents.value.length / rosterPerPage)))
const rosterPaged = computed(() => rosterStudents.value.slice((rosterPage.value - 1) * rosterPerPage, rosterPage.value * rosterPerPage))

function openRoster(c: ClassRoom) {
  rosterClass.value = c
  rosterSearch.value = ''
  rosterPage.value = 1
  actionError.value = null
}

function addMoreStudents() {
  const cls = rosterClass.value
  if (!cls) return
  rosterClass.value = null
  openEnroll(cls)
}

function openEnroll(c: ClassRoom) {
  enrollClass.value = c
  enrollStudentIds.value = []
  enrollSearch.value = ''
  actionError.value = null
}

/** Active students not yet in this class, newest first so just-created students are on top. */
const enrollable = computed(() => {
  const cls = enrollClass.value
  if (!cls) return []
  const enrolled = new Set(cls.students.map((s) => s.id))
  return students.value
    .filter((s) => !enrolled.has(s.id))
    .sort((a, b) => new Date(b.enrolled_date).getTime() - new Date(a.enrolled_date).getTime())
})

/** Real-time name filtering while typing. */
const filteredEnrollable = computed(() => {
  const q = enrollSearch.value.trim().toLowerCase()
  if (!q) return enrollable.value
  return enrollable.value.filter((s) => s.full_name.toLowerCase().includes(q))
})

const enrollButtonText = computed(() => {
  if (enrolling.value) return 'Enrolling…'
  const n = enrollStudentIds.value.length
  return n > 1 ? `Enroll ${n} students` : 'Enroll student'
})

function selectAllVisible() {
  enrollStudentIds.value = [...new Set([...enrollStudentIds.value, ...filteredEnrollable.value.map((s) => s.id)])]
}

/** Created within the last 7 days. */
function isRecentlyAdded(s: Student) {
  const t = new Date(s.enrolled_date).getTime()
  return Number.isFinite(t) && Date.now() - t < 7 * 24 * 60 * 60 * 1000
}

async function enroll() {
  const cls = enrollClass.value
  const ids = [...enrollStudentIds.value]
  if (!cls || ids.length === 0) return
  enrolling.value = true
  actionError.value = null
  const okIds: number[] = []
  const failedIds: number[] = []
  const ok: string[] = []
  const failed: string[] = []
  for (const id of ids) {
    const student = students.value.find((s) => s.id === id)
    try {
      await classService.enroll(cls.id, id)
      okIds.push(id)
      ok.push(student?.full_name ?? 'Student')
    } catch {
      failedIds.push(id)
      failed.push(student?.full_name ?? 'Student')
    }
  }
  enrolling.value = false
  if (ok.length) {
    toast(ok.length === 1 ? `${ok[0]} enrolled in ${cls.name}` : `${ok.length} students enrolled in ${cls.name}`)
  }
  if (failedIds.length) {
    // Keep the modal open with only the failed ones selected so the teacher can retry
    enrollStudentIds.value = failedIds
    actionError.value = `Couldn't enroll: ${failed.join(', ')}`
  } else {
    enrollClass.value = null
    enrollStudentIds.value = []
    enrollSearch.value = ''
  }
  if (okIds.length) {
    await load()
    // Re-point to the refreshed class so the picker reflects the new roster instantly
    const stillOpen = enrollClass.value
    if (stillOpen) enrollClass.value = classes.value.find((c) => c.id === stillOpen.id) ?? stillOpen
  }
}

function askRemove(c: ClassRoom, s: Student) {
  removing.value = { c, s }
}

async function doRemove() {
  if (!removing.value) return
  removeBusy.value = true
  try {
    await classService.unenroll(removing.value.s.id, removing.value.c.id)
    toast(`${removing.value.s.full_name} removed from ${removing.value.c.name}`, 'info')
    removing.value = null
    await load()
    const stillRoster = rosterClass.value
    if (stillRoster) rosterClass.value = classes.value.find((cl) => cl.id === stillRoster.id) ?? stillRoster
  } catch (e) {
    error.value = errorMessage(e)
    removing.value = null
  } finally {
    removeBusy.value = false
  }
}

onMounted(load)
</script>

<template>
  <div>
    <PageHeader title="Classes" :subtitle="`${classes.length} classes · ${totalEnrollments} active enrollments`">
      <LastUpdated :at="lastUpdated" />
      <button class="btn btn-primary" @click="openAdd">
        <AppIcon name="plus" :size="16" />
        Add class
      </button>
    </PageHeader>

    <!-- Loading skeleton -->
    <div v-if="loading" class="grid gap-4 sm:grid-cols-2">
      <div v-for="i in 4" :key="i" class="card card-pad space-y-3">
        <div class="skeleton h-5 w-2/3"></div>
        <div class="skeleton h-4 w-1/2"></div>
        <div class="skeleton h-16 w-full"></div>
        <div class="skeleton h-9 w-full"></div>
      </div>
    </div>

    <div v-else-if="error" class="card border-red-200 bg-red-50 p-6 text-red-700">{{ error }}</div>

    <EmptyState
      v-else-if="classes.length === 0"
      icon="book-open"
      title="No classes yet"
      description="Create your first class, pick a teacher and set the monthly fee."
    >
      <button class="btn btn-primary btn-sm" @click="openAdd">Add class</button>
    </EmptyState>

    <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
      <article
        v-for="c in classes"
        :key="c.id"
        class="card flex flex-col p-5 transition hover:border-brand-200 hover:shadow-pop"
      >
        <div class="mb-4 flex items-start justify-between gap-3">
          <div class="flex min-w-0 items-center gap-3">
            <div class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-600">
              <AppIcon name="book-open" :size="20" />
            </div>
            <div class="min-w-0">
              <h2 class="truncate font-bold text-slate-900">{{ c.name }}</h2>
              <p class="truncate text-xs text-slate-500">{{ c.teacher.subject }}</p>
            </div>
          </div>
          <span class="shrink-0 rounded-full bg-brand-50 px-2.5 py-1 text-xs font-bold text-brand-700">
            {{ c.active_students_count }} {{ c.active_students_count === 1 ? 'student' : 'students' }}
          </span>
        </div>

        <div class="mb-4 flex items-center gap-2 rounded-xl bg-slate-50 px-3 py-2.5">
          <Avatar :name="c.teacher.full_name" size="sm" />
          <p class="truncate text-sm font-medium text-slate-700">{{ c.teacher.full_name }}</p>
        </div>

        <dl class="mb-4 space-y-2 text-sm">
          <div class="flex items-center justify-between gap-4">
            <dt class="flex items-center gap-1.5 text-slate-500">
              <AppIcon name="calendar" :size="14" /> Schedule
            </dt>
            <dd class="truncate font-medium text-slate-800">{{ c.schedule }}</dd>
          </div>
          <div class="flex items-center justify-between gap-4">
            <dt class="flex items-center gap-1.5 text-slate-500">
              <AppIcon name="dollar-sign" :size="14" /> Monthly fee
            </dt>
            <dd class="font-semibold text-slate-800">{{ money(c.fee_amount) }}</dd>
          </div>
        </dl>

        <!-- Roster peek: avatars + count — click to search/paginate the full list -->
        <button
          type="button"
          class="mb-4 flex w-full items-center gap-2 rounded-xl bg-slate-50 px-3 py-2.5 text-left transition hover:bg-slate-100"
          :title="`View all students in ${c.name}`"
          @click="openRoster(c)"
        >
          <span v-if="c.students.length" class="flex -space-x-2">
            <Avatar
              v-for="s in c.students.slice(0, 5)"
              :key="s.id"
              :name="s.full_name"
              size="sm"
              class="ring-2 ring-white"
            />
          </span>
          <span v-else class="text-xs font-medium text-slate-400">No students yet</span>
          <span v-if="c.students.length" class="text-xs font-semibold text-slate-500">
            {{ c.active_students_count }} enrolled
          </span>
          <AppIcon name="chevron-right" :size="14" class="ml-auto shrink-0 text-slate-400" />
        </button>

        <div class="mt-auto flex gap-2 border-t border-slate-100 pt-4">
          <button class="btn btn-primary flex-1 btn-sm py-2" @click="openEnroll(c)">
            <AppIcon name="plus" :size="14" />
            Enroll
          </button>
          <button class="btn btn-secondary btn-sm py-2" @click="openEdit(c)">
            <AppIcon name="edit" :size="14" />
            Edit
          </button>
        </div>
      </article>
    </div>

    <!-- Add/Edit class modal -->
    <AppModal :open="showForm" :title="editing ? 'Edit class' : 'Add class'" :description="editing?.name ?? 'New class offering'" @close="showForm = false">
      <form @submit.prevent="save">
        <label class="label">Class name *</label>
        <input v-model="form.name" required class="input mb-4" placeholder="e.g. English Basic — Morning" />

        <div class="mb-4">
          <label class="label">Teacher *</label>
          <select v-model.number="form.teacher_id" required class="select">
            <option :value="0" disabled>Choose a teacher…</option>
            <option v-for="t in teachers" :key="t.id" :value="t.id">
              {{ t.full_name }} ({{ t.subject }})
            </option>
          </select>
        </div>

        <div class="mb-4 grid gap-4 sm:grid-cols-2">
          <div>
            <label class="label">Schedule *</label>
            <input v-model="form.schedule" required class="input" placeholder="Mon/Wed/Fri 8:00–10:00" />
          </div>
          <div>
            <label class="label">Monthly fee (USD) *</label>
            <input v-model.number="form.fee_amount" type="number" min="0" step="0.5" required class="input" />
          </div>
        </div>

        <p v-if="actionError" class="mb-4 rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700">{{ actionError }}</p>

        <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
          <button type="button" class="btn btn-ghost" @click="showForm = false">Cancel</button>
          <button type="submit" :disabled="saving" class="btn btn-primary">
            {{ saving ? 'Saving…' : editing ? 'Save changes' : 'Add class' }}
          </button>
        </div>
      </form>
    </AppModal>

    <!-- Enroll modal -->
    <AppModal
      :open="enrollClass !== null"
      :title="enrollStudentIds.length > 1 ? 'Enroll students' : 'Enroll student'"
      :description="enrollClass?.name"
      @close="enrollClass = null"
    >
      <!-- Instant search — no scrolling through a long dropdown -->
      <div class="relative mb-3">
        <AppIcon name="search" :size="16" class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
        <input
          v-model="enrollSearch"
          type="search"
          placeholder="Search by student name…"
          class="input pl-10"
        />
      </div>

      <!-- Checkbox list: tick as many students as you want -->
      <div class="max-h-72 space-y-1 overflow-y-auto rounded-xl border border-slate-200 p-1.5">
        <label
          v-for="s in filteredEnrollable"
          :key="s.id"
          class="flex cursor-pointer items-center gap-3 rounded-lg px-2.5 py-2 transition"
          :class="enrollStudentIds.includes(s.id) ? 'bg-brand-50 ring-1 ring-brand-200' : 'hover:bg-slate-50'"
        >
          <input v-model="enrollStudentIds" type="checkbox" :value="s.id" class="h-4 w-4 shrink-0 accent-brand-600" />
          <Avatar :name="s.full_name" size="sm" />
          <span class="min-w-0 flex-1">
            <span class="block truncate text-sm font-medium text-slate-700">{{ s.full_name }}</span>
            <span class="block truncate text-xs text-slate-400">{{ s.phone ?? 'no phone' }}</span>
          </span>
          <span
            v-if="isRecentlyAdded(s)"
            class="shrink-0 rounded-full bg-brand-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-brand-700"
          >New</span>
        </label>

        <p v-if="filteredEnrollable.length === 0" class="px-3 py-6 text-center text-sm text-slate-400">
          {{ enrollSearch ? `No students match “${enrollSearch}”` : 'All active students are already enrolled in this class.' }}
        </p>
      </div>

      <div class="mt-2 flex items-center justify-between text-xs">
        <p class="text-slate-400">
          <span v-if="enrollStudentIds.length" class="font-semibold text-brand-600">{{ enrollStudentIds.length }} selected · </span>
          {{ enrollable.length }} available · {{ enrollClass?.students.length ?? 0 }} already enrolled
        </p>
        <div class="flex shrink-0 gap-3">
          <button class="font-semibold text-brand-600 transition hover:text-brand-700" @click="selectAllVisible">Select all</button>
          <button class="font-medium text-slate-400 transition hover:text-slate-600" @click="enrollStudentIds = []">Clear</button>
        </div>
      </div>

      <p v-if="students.length === 0" class="mt-2 text-xs text-slate-400">
        No active students available. Add students first from the Students page.
      </p>

      <p v-if="actionError" class="mt-4 rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700">{{ actionError }}</p>

      <div class="mt-5 flex justify-end gap-2 border-t border-slate-100 pt-4">
        <button class="btn btn-ghost" @click="enrollClass = null">Cancel</button>
        <button :disabled="enrolling || enrollStudentIds.length === 0" class="btn btn-primary" @click="enroll">
          {{ enrollButtonText }}
        </button>
      </div>
    </AppModal>

    <!-- Class roster modal (scales to classes with 100+ students) -->
    <AppModal
      :open="rosterClass !== null"
      title="Class roster"
      :description="rosterClass ? `${rosterClass.name} · ${rosterClass.active_students_count} enrolled` : ''"
      @close="rosterClass = null"
    >
      <div class="relative mb-3">
        <AppIcon name="search" :size="16" class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
        <input v-model="rosterSearch" type="search" placeholder="Search a student…" class="input pl-10" @input="rosterPage = 1" />
      </div>

      <div class="max-h-80 divide-y divide-slate-100 overflow-y-auto rounded-xl border border-slate-200">
        <div v-for="s in rosterPaged" :key="s.id" class="flex items-center gap-3 px-3 py-2.5">
          <Avatar :name="s.full_name" size="sm" />
          <span class="min-w-0 flex-1">
            <span class="block truncate text-sm font-medium text-slate-700">{{ s.full_name }}</span>
            <span class="block truncate text-xs text-slate-400">{{ s.phone ?? 'no phone' }}</span>
          </span>
          <button
            class="rounded-lg p-2 text-slate-400 transition hover:bg-red-50 hover:text-red-600"
            title="Remove from class"
            @click="askRemove(rosterClass!, s)"
          >
            <AppIcon name="trash" :size="14" />
          </button>
        </div>
        <p v-if="rosterPaged.length === 0" class="px-3 py-8 text-center text-sm text-slate-400">
          {{ rosterSearch ? `No students match “${rosterSearch}”` : 'No students enrolled yet.' }}
        </p>
      </div>

      <div class="mt-2 flex items-center justify-between text-xs text-slate-400">
        <span>{{ rosterStudents.length }} shown</span>
        <div v-if="rosterTotalPages > 1" class="flex items-center gap-2">
          <button
            class="rounded-lg px-2 py-1 font-semibold text-slate-500 transition hover:bg-slate-100 disabled:opacity-40"
            :disabled="rosterPage === 1"
            @click="rosterPage--"
          >
            Prev
          </button>
          <span>{{ rosterPage }} / {{ rosterTotalPages }}</span>
          <button
            class="rounded-lg px-2 py-1 font-semibold text-slate-500 transition hover:bg-slate-100 disabled:opacity-40"
            :disabled="rosterPage === rosterTotalPages"
            @click="rosterPage++"
          >
            Next
          </button>
        </div>
      </div>

      <div class="mt-5 flex justify-end gap-2 border-t border-slate-100 pt-4">
        <button class="btn btn-ghost" @click="rosterClass = null">Close</button>
        <button class="btn btn-primary" @click="addMoreStudents">
          <AppIcon name="plus" :size="14" />
          Add students
        </button>
      </div>
    </AppModal>

    <!-- Remove student confirm -->
    <AppModal
      :open="removing !== null"
      title="Remove student from class?"
      :description="removing ? `${removing.s.full_name} will no longer appear in ${removing.c.name} and future fees for it.` : ''"
      :confirm="{ label: 'Remove', tone: 'danger', loading: removeBusy }"
      @close="removing = null"
      @confirm="doRemove"
    />
  </div>
</template>
