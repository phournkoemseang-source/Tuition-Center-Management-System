<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import PageHeader from '../components/PageHeader.vue'
import Avatar from '../components/Avatar.vue'
import EmptyState from '../components/EmptyState.vue'
import AppModal from '../components/AppModal.vue'
import AppIcon from '../components/AppIcon.vue'
import { errorMessage } from '../services/api'
import { classService, studentService } from '../services/tcms'
import type { ClassRoom, Student, Teacher } from '../types'
import { money } from '../utils/format'
import { toast } from '../utils/toast'

const classes = ref<ClassRoom[]>([])
const students = ref<Student[]>([])
const loading = ref(true)
const error = ref<string | null>(null)
const saving = ref(false)
const actionError = ref<string | null>(null)

const showForm = ref(false)
const editing = ref<ClassRoom | null>(null)
const form = ref({ name: '', teacher_id: 0, schedule: '', fee_amount: 30 })

// enroll flow
const enrollClass = ref<ClassRoom | null>(null)
const enrollStudentId = ref<number | ''>('')
const enrolling = ref(false)

// remove student confirm
const removing = ref<{ c: ClassRoom; s: Student } | null>(null)
const removeBusy = ref(false)

const teachers = computed<Teacher[]>(() => [...new Map(classes.value.map((c) => [c.teacher.id, c.teacher])).values()])

const totalEnrollments = computed(() => classes.value.reduce((sum, c) => sum + c.active_students_count, 0))

async function load() {
  loading.value = true
  error.value = null
  try {
    const [c, s] = await Promise.all([classService.list(), studentService.list()])
    classes.value = c
    students.value = s.filter((st) => st.status === 'active')
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    loading.value = false
  }
}

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

function openEnroll(c: ClassRoom) {
  enrollClass.value = c
  enrollStudentId.value = ''
  actionError.value = null
}

async function enroll() {
  if (!enrollClass.value || !enrollStudentId.value) return
  enrolling.value = true
  actionError.value = null
  try {
    const cls = enrollClass.value
    const student = students.value.find((s) => s.id === Number(enrollStudentId.value))
    await classService.enroll(cls.id, Number(enrollStudentId.value))
    toast(`${student?.full_name ?? 'Student'} enrolled in ${cls.name}`)
    enrollClass.value = null
    await load()
  } catch (e) {
    actionError.value = errorMessage(e)
  } finally {
    enrolling.value = false
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

        <div v-if="c.students.length" class="mb-4 flex flex-wrap gap-1.5">
          <span
            v-for="s in c.students"
            :key="s.id"
            class="group flex items-center gap-1 rounded-full bg-slate-100 py-1 pl-2.5 pr-1 text-xs font-medium text-slate-700"
          >
            {{ s.full_name }}
            <button
              class="grid h-4.5 w-4.5 place-items-center rounded-full text-slate-400 transition hover:bg-red-100 hover:text-red-600"
              :title="`Remove ${s.full_name}`"
              @click="askRemove(c, s)"
            >
              <AppIcon name="x" :size="11" />
            </button>
          </span>
        </div>

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
      title="Enroll student"
      :description="enrollClass?.name"
      @close="enrollClass = null"
    >
      <label class="label">Pick a student</label>
      <select v-model.number="enrollStudentId" class="select">
        <option :value="''" disabled>Choose a student…</option>
        <option v-for="s in students" :key="s.id" :value="s.id">{{ s.full_name }}</option>
      </select>
      <p v-if="students.length === 0" class="mt-2 text-xs text-slate-400">
        No active students available. Add students first from the Students page.
      </p>

      <p v-if="actionError" class="mt-4 rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700">{{ actionError }}</p>

      <div class="mt-5 flex justify-end gap-2 border-t border-slate-100 pt-4">
        <button class="btn btn-ghost" @click="enrollClass = null">Cancel</button>
        <button :disabled="enrolling || !enrollStudentId" class="btn btn-primary" @click="enroll">
          {{ enrolling ? 'Enrolling…' : 'Enroll student' }}
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
