<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import PageHeader from '../components/PageHeader.vue'
import Avatar from '../components/Avatar.vue'
import StatusBadge from '../components/StatusBadge.vue'
import EmptyState from '../components/EmptyState.vue'
import AppModal from '../components/AppModal.vue'
import AppIcon from '../components/AppIcon.vue'
import { errorMessage } from '../services/api'
import { studentService } from '../services/tcms'
import type { Student } from '../types'
import { formatDate } from '../utils/format'
import { downloadCsv, copyTsv } from '../utils/csv'
import { bannerToast, toast } from '../utils/toast'
import { usePolling } from '../composables/usePolling'
import { useLastUpdated } from '../composables/useLastUpdated'
import LastUpdated from '../components/LastUpdated.vue'
import { celebrate } from '../utils/celebrate'

const router = useRouter()

type SortKey = 'full_name' | 'enrolled_date' | 'status'

const students = ref<Student[]>([])
const loading = ref(true)
const error = ref<string | null>(null)
const search = ref('')
const searchTimer = ref<ReturnType<typeof setTimeout> | null>(null)

// Form state
const showForm = ref(false)
const editing = ref<Student | null>(null)
const saving = ref(false)
const actionError = ref<string | null>(null)
const form = ref({ full_name: '', phone: '', parent_contact: '' })

// Status toggle confirm
const confirming = ref<Student | null>(null)
const confirmBusy = ref(false)

// Sorting + pagination
const sortKey = ref<SortKey>('full_name')
const sortDir = ref<1 | -1>(1)
const page = ref(1)
const perPage = 10

// Student just created in this session — always pinned to the very top
const newStudentId = ref<number | null>(null)

const sorted = computed(() => {
  const dir = sortDir.value
  const list = [...students.value].sort((a, b) => {
    const av = a[sortKey.value]
    const bv = b[sortKey.value]
    if (typeof av === 'number' && typeof bv === 'number') return (av - bv) * dir
    return String(av ?? '').localeCompare(String(bv ?? '')) * dir
  })
  // Pin the newly created student to the first row, above any sorting
  if (newStudentId.value !== null) {
    const idx = list.findIndex((s) => s.id === newStudentId.value)
    if (idx > 0) list.unshift(...list.splice(idx, 1))
  }
  return list
})

const totalPages = computed(() => Math.max(1, Math.ceil(sorted.value.length / perPage)))
const paged = computed(() => sorted.value.slice((page.value - 1) * perPage, page.value * perPage))

function setSort(key: SortKey) {
  if (sortKey.value === key) {
    sortDir.value = sortDir.value === 1 ? -1 : 1
  } else {
    sortKey.value = key
    sortDir.value = 1
  }
  page.value = 1
}

function debouncedSearch() {
  if (searchTimer.value) clearTimeout(searchTimer.value)
  searchTimer.value = setTimeout(load, 300)
}

async function load(opts: { silent?: boolean } = {}) {
  const silent = opts.silent ?? false
  if (!silent) loading.value = true
  try {
    students.value = await studentService.list(search.value)
    error.value = null
    markUpdated()
    if (!silent) page.value = 1
  } catch (e) {
    // Silent background refreshes keep the current list on screen on failure
    if (!silent) error.value = errorMessage(e)
  } finally {
    if (!silent) loading.value = false
  }
}

// Auto-refresh every 15s, paused while a modal is open or a save is in flight
usePolling(
  () => load({ silent: true }),
  15000,
  () => showForm.value || confirming.value !== null || saving.value,
)

const { lastUpdated, markUpdated } = useLastUpdated()

function openAdd() {
  editing.value = null
  form.value = { full_name: '', phone: '', parent_contact: '' }
  actionError.value = null
  showForm.value = true
}

function openEdit(s: Student) {
  editing.value = s
  form.value = { full_name: s.full_name, phone: s.phone ?? '', parent_contact: s.parent_contact ?? '' }
  actionError.value = null
  showForm.value = true
}

async function save() {
  saving.value = true
  actionError.value = null
  try {
    if (editing.value) {
      const updated = await studentService.update(editing.value.id, form.value)
      // Update in place — no reload flicker
      const idx = students.value.findIndex((s) => s.id === updated.id)
      if (idx !== -1) students.value[idx] = { ...students.value[idx], ...updated }
      toast('Student updated')
    } else {
      const created = await studentService.create(form.value)
      // Show the new student immediately at the top — no reload needed
      students.value = [created, ...students.value]
      newStudentId.value = created.id
      page.value = 1
      celebrate()
      bannerToast({
        message: `New student added: ${created.full_name}`,
        kind: 'success',
        actionLabel: 'Enroll in class',
        onAction: () => router.push('/classes'),
      })
    }
    showForm.value = false
  } catch (e) {
    actionError.value = errorMessage(e)
  } finally {
    saving.value = false
  }
}

function askToggle(s: Student) {
  confirming.value = s
}

async function doToggle() {
  const s = confirming.value
  if (!s) return
  const next = s.status === 'active' ? 'inactive' : 'active'
  confirmBusy.value = true
  try {
    await studentService.update(s.id, { status: next })
    // Update in place — no reload flicker
    const idx = students.value.findIndex((x) => x.id === s.id)
    if (idx !== -1) students.value[idx] = { ...students.value[idx], status: next }
    toast(next === 'active' ? `${s.full_name} reactivated` : `${s.full_name} deactivated`, next === 'active' ? 'success' : 'info')
    confirming.value = null
  } catch (e) {
    error.value = errorMessage(e)
    confirming.value = null
  } finally {
    confirmBusy.value = false
  }
}

// Export — quick CSV for Excel / TSV copy for Google Sheets
const exportOpen = ref(false)
const exportPicker = ref<HTMLElement | null>(null)

function onDocClick(e: MouseEvent) {
  if (!exportOpen.value) return
  if (exportPicker.value && !exportPicker.value.contains(e.target as Node)) exportOpen.value = false
}

function exportHeader(): string[] {
  return ['Name', 'Phone', 'Parent contact', 'Enrolled', 'Status']
}

function exportRow(s: Student): string[] {
  return [s.full_name, s.phone ?? '', s.parent_contact ?? '', s.enrolled_date, s.status]
}

function exportCsv() {
  exportOpen.value = false
  const rows = [exportHeader(), ...sorted.value.map(exportRow)]
  downloadCsv(rows, `students-${new Date().toISOString().slice(0, 10)}.csv`)
  toast(`Exported ${sorted.value.length} students to CSV`)
}

async function copyList() {
  exportOpen.value = false
  const rows = [exportHeader(), ...sorted.value.map(exportRow)]
  if (await copyTsv(rows)) {
    toast(`Copied ${sorted.value.length} students — paste straight into Sheets/Excel`)
  } else {
    toast('Could not access the clipboard', 'error')
  }
}

onMounted(() => {
  load()
  document.addEventListener('click', onDocClick)
})
onBeforeUnmount(() => document.removeEventListener('click', onDocClick))
</script>

<template>
  <div>
    <PageHeader title="Students" :subtitle="`${students.length} enrolled in total`">
      <div ref="exportPicker" class="relative">
        <button class="btn btn-secondary" @click="exportOpen = !exportOpen">
          <AppIcon name="download" :size="15" />
          Export
          <AppIcon name="chevron-down" :size="13" />
        </button>
        <div
          v-if="exportOpen"
          class="absolute right-0 z-20 mt-2 w-56 rounded-xl border border-slate-200 bg-white p-1.5 shadow-pop"
        >
          <button
            class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left text-sm font-medium text-slate-700 transition hover:bg-slate-50"
            @click="exportCsv"
          >
            <AppIcon name="download" :size="14" class="text-slate-400" />
            Excel (CSV)
          </button>
          <button
            class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left text-sm font-medium text-slate-700 transition hover:bg-slate-50"
            @click="copyList"
          >
            <AppIcon name="clipboard-list" :size="14" class="text-slate-400" />
            Copy for Sheets
          </button>
        </div>
      </div>
      <button class="btn btn-primary" @click="openAdd">
        <AppIcon name="plus" :size="16" />
        Add student
      </button>
    </PageHeader>

    <!-- Toolbar -->
    <div class="mb-4 flex flex-wrap items-center gap-3">
      <div class="relative w-full max-w-sm">
        <AppIcon name="search" :size="16" class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
        <input
          v-model="search"
          type="search"
          placeholder="Search by student name…"
          class="input pl-10"
          @input="debouncedSearch"
        />
      </div>
      <LastUpdated :at="lastUpdated" class="ml-auto" />
    </div>

    <!-- Loading skeleton -->
    <div v-if="loading" class="card p-5">
      <div v-for="i in 6" :key="i" class="flex items-center gap-4 border-b border-slate-100 py-3.5 last:border-0">
        <div class="skeleton h-9 w-9 rounded-full"></div>
        <div class="skeleton h-4 flex-1"></div>
        <div class="skeleton h-4 w-24"></div>
        <div class="skeleton h-6 w-16 rounded-full"></div>
      </div>
    </div>

    <div v-else-if="error" class="card border-red-200 bg-red-50 p-6 text-red-700">{{ error }}</div>

    <!-- Table -->
    <div v-else class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>
              <button class="th-sortable" @click="setSort('full_name')">
                Student
                <AppIcon :name="sortKey === 'full_name' && sortDir === -1 ? 'chevron-up' : 'chevron-down'" :size="13" :class="sortKey === 'full_name' ? 'text-brand-600' : 'text-slate-300'" />
              </button>
            </th>
            <th class="hidden md:table-cell">Phone</th>
            <th class="hidden lg:table-cell">Parent contact</th>
            <th class="hidden xl:table-cell">Enrolled</th>
            <th>
              <button class="th-sortable" @click="setSort('status')">
                Status
                <AppIcon :name="sortKey === 'status' && sortDir === -1 ? 'chevron-up' : 'chevron-down'" :size="13" :class="sortKey === 'status' ? 'text-brand-600' : 'text-slate-300'" />
              </button>
            </th>
            <th class="text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="paged.length === 0">
            <td colspan="6">
              <EmptyState
                icon="users"
                :title="search ? 'No students match your search' : 'No students yet'"
                :description="search ? 'Try a different name or clear the search.' : 'Add your first student to start managing enrolments and fees.'"
              >
                <button v-if="!search" class="btn btn-primary btn-sm" @click="openAdd">Add student</button>
              </EmptyState>
            </td>
          </tr>
          <tr
            v-for="s in paged"
            :key="s.id"
            class="transition-colors"
            :class="s.id === newStudentId ? 'bg-brand-50/70' : ''"
          >
            <td>
              <div class="flex items-center gap-3">
                <Avatar :name="s.full_name" size="md" />
                <div class="min-w-0">
                  <div class="flex items-center gap-2">
                    <p class="truncate font-semibold text-slate-800">{{ s.full_name }}</p>
                    <span
                      v-if="s.id === newStudentId"
                      class="inline-flex shrink-0 items-center gap-1 rounded-full bg-brand-600 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white"
                    >
                      <AppIcon name="sparkles" :size="10" />
                      New
                    </span>
                  </div>
                  <p class="text-xs text-slate-400 sm:hidden">{{ s.phone ?? 'no phone' }}</p>
                </div>
              </div>
            </td>
            <td class="hidden text-slate-600 md:table-cell">{{ s.phone ?? '—' }}</td>
            <td class="hidden text-slate-600 lg:table-cell">{{ s.parent_contact ?? '—' }}</td>
            <td class="hidden text-slate-600 xl:table-cell">{{ formatDate(s.enrolled_date, false) }}</td>
            <td><StatusBadge :status="s.status" /></td>
            <td>
              <div class="flex items-center justify-end gap-1">
                <button
                  class="rounded-lg p-2 text-slate-400 transition hover:bg-brand-50 hover:text-brand-600"
                  title="Edit"
                  @click="openEdit(s)"
                >
                  <AppIcon name="edit" :size="15" />
                </button>
                <button
                  class="rounded-lg p-2 transition"
                  :class="s.status === 'active' ? 'text-slate-400 hover:bg-red-50 hover:text-red-600' : 'text-slate-400 hover:bg-emerald-50 hover:text-emerald-600'"
                  :title="s.status === 'active' ? 'Deactivate' : 'Reactivate'"
                  @click="askToggle(s)"
                >
                  <AppIcon :name="s.status === 'active' ? 'trash' : 'user-check'" :size="15" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="!loading && !error && totalPages > 1" class="mt-4 flex items-center justify-between">
      <p class="text-sm text-slate-500">
        Page <span class="font-semibold text-slate-700">{{ page }}</span> of {{ totalPages }}
        · {{ sorted.length }} students
      </p>
      <div class="flex gap-1.5">
        <button class="btn btn-secondary btn-sm" :disabled="page === 1" @click="page--">
          <AppIcon name="chevron-left" :size="14" /> Prev
        </button>
        <button class="btn btn-secondary btn-sm" :disabled="page === totalPages" @click="page++">
          Next <AppIcon name="chevron-right" :size="14" />
        </button>
      </div>
    </div>

    <!-- Add/Edit modal -->
    <AppModal :open="showForm" :title="editing ? 'Edit student' : 'Add student'" :description="editing ? editing.full_name : 'New student record'" @close="showForm = false">
      <form @submit.prevent="save">
        <label class="label">Full name *</label>
        <input v-model="form.full_name" required class="input mb-4" placeholder="e.g. Sok Piseth" />

        <div class="mb-4 grid gap-4 sm:grid-cols-2">
          <div>
            <label class="label">Phone</label>
            <input v-model="form.phone" class="input" placeholder="e.g. 012 345 678" />
          </div>
          <div>
            <label class="label">Parent contact</label>
            <input v-model="form.parent_contact" class="input" placeholder="e.g. 011 999 888" />
          </div>
        </div>

        <p v-if="actionError" class="mb-4 rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700">{{ actionError }}</p>

        <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
          <button type="button" class="btn btn-ghost" @click="showForm = false">Cancel</button>
          <button type="submit" :disabled="saving" class="btn btn-primary">
            {{ saving ? 'Saving…' : editing ? 'Save changes' : 'Add student' }}
          </button>
        </div>
      </form>
    </AppModal>

    <!-- Toggle confirm -->
    <AppModal
      :open="confirming !== null"
      :title="confirming?.status === 'active' ? 'Deactivate student?' : 'Reactivate student?'"
      :description="confirming ? `${confirming.full_name} will be ${confirming.status === 'active' ? 'marked inactive and excluded from new fees' : 'reactivated and available for enrolment'}.` : ''"
      :confirm="{ label: confirming?.status === 'active' ? 'Deactivate' : 'Reactivate', tone: confirming?.status === 'active' ? 'danger' : 'success', loading: confirmBusy }"
      @close="confirming = null"
      @confirm="doToggle"
    />
  </div>
</template>
