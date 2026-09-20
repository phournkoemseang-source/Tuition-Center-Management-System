<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import PageHeader from '../components/PageHeader.vue'
import Avatar from '../components/Avatar.vue'
import EmptyState from '../components/EmptyState.vue'
import AppModal from '../components/AppModal.vue'
import AppIcon from '../components/AppIcon.vue'
import LastUpdated from '../components/LastUpdated.vue'
import { errorMessage } from '../services/api'
import { teacherService } from '../services/tcms'
import type { Teacher } from '../types'
import { toast } from '../utils/toast'
import { usePolling } from '../composables/usePolling'
import { useLastUpdated } from '../composables/useLastUpdated'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()

const teachers = ref<Teacher[]>([])
const loading = ref(true)
const error = ref<string | null>(null)
const search = ref('')
const searchTimer = ref<ReturnType<typeof setTimeout> | null>(null)

// Form state
const showForm = ref(false)
const editing = ref<Teacher | null>(null)
const saving = ref(false)
const actionError = ref<string | null>(null)
const form = ref({ full_name: '', subject: '', email: '', password: '' })

// Delete confirm
const confirming = ref<Teacher | null>(null)
const confirmBusy = ref(false)

const { lastUpdated, markUpdated } = useLastUpdated()

// Auto-refresh every 15s, paused while a modal is open or a save is in flight
usePolling(
  () => load({ silent: true }),
  15000,
  () => showForm.value || confirming.value !== null || saving.value || confirmBusy.value,
)

/** Common school subjects + any subject already used by a teacher. */
const subjectSuggestions = computed(() => {
  const common = [
    'English', 'Khmer Literature', 'Math', 'Physics', 'Chemistry', 'Biology',
    'Computer', 'Accounting', 'Economics', 'Geography', 'History',
  ]
  return [...new Set([...common, ...teachers.value.map((t) => t.subject)])]
})

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return teachers.value
  return teachers.value.filter(
    (t) =>
      t.full_name.toLowerCase().includes(q) ||
      t.subject.toLowerCase().includes(q) ||
      (t.user?.email ?? '').toLowerCase().includes(q),
  )
})

async function load(opts: { silent?: boolean } = {}) {
  const silent = opts.silent ?? false
  if (!silent) loading.value = true
  try {
    teachers.value = await teacherService.list()
    error.value = null
    markUpdated()
  } catch (e) {
    if (!silent) error.value = errorMessage(e)
  } finally {
    if (!silent) loading.value = false
  }
}

function debouncedSearch() {
  if (searchTimer.value) clearTimeout(searchTimer.value)
  searchTimer.value = setTimeout(() => load(), 300)
}

function openAdd() {
  editing.value = null
  form.value = { full_name: '', subject: '', email: '', password: '' }
  actionError.value = null
  showForm.value = true
}

function openEdit(t: Teacher) {
  editing.value = t
  form.value = { full_name: t.full_name, subject: t.subject, email: t.user?.email ?? '', password: '' }
  actionError.value = null
  showForm.value = true
}

async function save() {
  saving.value = true
  actionError.value = null
  try {
    if (editing.value) {
      const payload: Parameters<typeof teacherService.update>[1] = {
        full_name: form.value.full_name,
        subject: form.value.subject,
        ...(form.value.email ? { email: form.value.email } : {}),
        ...(form.value.password ? { password: form.value.password } : {}),
      }
      await teacherService.update(editing.value.id, payload)
      toast('Teacher updated')
    } else {
      const created = await teacherService.create({
        full_name: form.value.full_name,
        subject: form.value.subject,
        email: form.value.email,
        password: form.value.password,
      })
      teachers.value = [...teachers.value, created].sort((a, b) => a.full_name.localeCompare(b.full_name))
      toast(`${created.full_name} added — they can now sign in with their email`)
    }
    showForm.value = false
    if (!editing.value) await load({ silent: true })
  } catch (e) {
    actionError.value = errorMessage(e)
  } finally {
    saving.value = false
  }
}

function askDelete(t: Teacher) {
  confirming.value = t
}

async function doDelete() {
  const t = confirming.value
  if (!t) return
  confirmBusy.value = true
  try {
    await teacherService.remove(t.id)
    teachers.value = teachers.value.filter((x) => x.id !== t.id)
    toast(`${t.full_name} removed`, 'info')
    confirming.value = null
  } catch (e) {
    // e.g. teacher still teaches classes — backend sends a clear message
    toast(errorMessage(e), 'error')
    confirming.value = null
  } finally {
    confirmBusy.value = false
  }
}

onMounted(() => load())
</script>

<template>
  <div>
    <PageHeader title="Teachers" :subtitle="`${teachers.length} teachers at the center`">
      <LastUpdated :at="lastUpdated" />
      <button v-if="auth.isAdmin" class="btn btn-primary" @click="openAdd">
        <AppIcon name="plus" :size="16" />
        Add teacher
      </button>
    </PageHeader>

    <!-- Toolbar -->
    <div class="mb-4 flex flex-wrap items-center gap-3">
      <div class="relative w-full max-w-sm">
        <AppIcon name="search" :size="16" class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
        <input
          v-model="search"
          type="search"
          placeholder="Search by name, subject or email…"
          class="input pl-10"
          @input="debouncedSearch"
        />
      </div>
      <p v-if="!auth.isAdmin" class="text-xs text-slate-400">Ask an admin to add or edit teachers.</p>
    </div>

    <!-- Loading skeleton -->
    <div v-if="loading" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
      <div v-for="i in 3" :key="i" class="card card-pad space-y-3">
        <div class="flex items-center gap-3">
          <div class="skeleton h-10 w-10 rounded-full"></div>
          <div class="skeleton h-4 flex-1"></div>
        </div>
        <div class="skeleton h-10 w-full"></div>
        <div class="skeleton h-9 w-full"></div>
      </div>
    </div>

    <div v-else-if="error" class="card border-red-200 bg-red-50 p-6 text-red-700">{{ error }}</div>

    <EmptyState
      v-else-if="teachers.length === 0"
      icon="graduation-cap"
      title="No teachers yet"
      description="Add your first teacher with a subject and a login account."
    >
      <button v-if="auth.isAdmin" class="btn btn-primary btn-sm" @click="openAdd">Add teacher</button>
    </EmptyState>

    <EmptyState
      v-else-if="filtered.length === 0"
      icon="users"
      title="No teachers match your search"
      description="Try a different name, subject or email."
    />

    <!-- Teacher cards -->
    <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
      <article
        v-for="t in filtered"
        :key="t.id"
        class="card flex flex-col p-5 transition hover:border-brand-200 hover:shadow-pop"
      >
        <div class="flex items-start justify-between gap-3">
          <div class="flex min-w-0 items-center gap-3">
            <Avatar :name="t.full_name" size="md" />
            <div class="min-w-0">
              <h2 class="truncate font-bold text-slate-900">{{ t.full_name }}</h2>
              <p class="truncate text-xs text-slate-500">{{ t.user?.email ?? 'no login email' }}</p>
            </div>
          </div>
          <span class="shrink-0 rounded-full bg-brand-50 px-2.5 py-1 text-xs font-bold text-brand-700">
            {{ t.subject }}
          </span>
        </div>

        <div class="mt-4 mb-4 flex items-center gap-2 rounded-xl bg-slate-50 px-3 py-2.5">
          <AppIcon name="book-open" :size="14" class="shrink-0 text-slate-400" />
          <span class="truncate text-sm font-medium text-slate-700">
            {{ t.classes_count ?? 0 }} {{ (t.classes_count ?? 0) === 1 ? 'class' : 'classes' }} taught
          </span>
        </div>

        <div v-if="auth.isAdmin" class="mt-auto flex gap-2 border-t border-slate-100 pt-4">
          <button class="btn btn-secondary flex-1 btn-sm py-2" @click="openEdit(t)">
            <AppIcon name="edit" :size="14" />
            Edit
          </button>
          <button
            class="btn btn-ghost btn-sm py-2 text-slate-400 hover:bg-red-50 hover:text-red-600"
            title="Remove teacher"
            @click="askDelete(t)"
          >
            <AppIcon name="trash" :size="14" />
          </button>
        </div>
      </article>
    </div>

    <!-- Add/Edit teacher modal -->
    <AppModal
      :open="showForm"
      :title="editing ? 'Edit teacher' : 'Add teacher'"
      :description="editing ? editing.full_name : 'Creates their login account too'"
      @close="showForm = false"
    >
      <form @submit.prevent="save">
        <label class="label">Full name *</label>
        <input v-model="form.full_name" required class="input mb-4" placeholder="e.g. Sokha Kim" />

        <label class="label">Subject *</label>
        <input
          v-model="form.subject"
          required
          list="subject-options"
          class="input mb-1"
          placeholder="Pick or type a subject…"
        />
        <datalist id="subject-options">
          <option v-for="s in subjectSuggestions" :key="s" :value="s" />
        </datalist>
        <p class="mb-4 text-xs text-slate-400">Choose a suggestion or type your own.</p>

        <label class="label">Login email *</label>
        <input
          v-model="form.email"
          required
          type="email"
          class="input mb-4"
          placeholder="e.g. sokha@tuition.test"
        />

        <label class="label">{{ editing ? 'New password (leave blank to keep current)' : 'Password *' }}</label>
        <input
          v-model="form.password"
          :required="!editing"
          type="password"
          :minlength="form.password ? 6 : undefined"
          class="input"
          placeholder="At least 6 characters"
        />

        <p v-if="actionError" class="mb-4 mt-4 rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700">{{ actionError }}</p>

        <div class="mt-4 flex justify-end gap-2 border-t border-slate-100 pt-4">
          <button type="button" class="btn btn-ghost" @click="showForm = false">Cancel</button>
          <button type="submit" :disabled="saving" class="btn btn-primary">
            {{ saving ? 'Saving…' : editing ? 'Save changes' : 'Add teacher' }}
          </button>
        </div>
      </form>
    </AppModal>

    <!-- Delete confirm -->
    <AppModal
      :open="confirming !== null"
      title="Remove teacher?"
      :description="confirming ? `${confirming.full_name} will lose their login account. Teachers still teaching classes cannot be removed.` : ''"
      :confirm="{ label: 'Remove', tone: 'danger', loading: confirmBusy }"
      @close="confirming = null"
      @confirm="doDelete"
    />
  </div>
</template>
