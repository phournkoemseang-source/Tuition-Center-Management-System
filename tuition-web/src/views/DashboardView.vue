<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import StatCard from '../components/StatCard.vue'
import BarList from '../components/charts/BarList.vue'
import EmptyState from '../components/EmptyState.vue'
import LastUpdated from '../components/LastUpdated.vue'
import AppIcon from '../components/AppIcon.vue'
import { errorMessage } from '../services/api'
import { dashboardService } from '../services/tcms'
import type { DashboardStats } from '../types'
import { formatLongDate, money } from '../utils/format'
import { usePolling } from '../composables/usePolling'
import { useLastUpdated } from '../composables/useLastUpdated'

const stats = ref<DashboardStats | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)
const { lastUpdated, markUpdated } = useLastUpdated()

const today = formatLongDate()

const hour = new Date().getHours()
const greeting = hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening'

const collectionRate = computed(() => {
  if (!stats.value) return null
  const billed = stats.value.unpaid_this_month.total + stats.value.collected_this_month
  return billed > 0 ? Math.round((stats.value.collected_this_month / billed) * 100) : null
})

const unpaidBars = computed(() =>
  (stats.value?.unpaid_by_class ?? []).map((c) => ({
    label: c.name,
    sub: `${c.unpaid_count} unpaid`,
    value: Number(c.unpaid_total),
    display: money(c.unpaid_total),
    tone: 'danger' as const,
  })),
)

async function refresh(opts: { silent?: boolean } = {}) {
  const silent = opts.silent ?? false
  if (!silent) loading.value = true
  try {
    stats.value = await dashboardService.stats()
    error.value = null
    markUpdated()
  } catch (e) {
    // Silent background refreshes keep the last good numbers on screen
    if (!silent) error.value = errorMessage(e)
  } finally {
    if (!silent) loading.value = false
  }
}

onMounted(() => refresh())

// Live dashboard: re-fetch stats every 10s so the numbers update without reloading
usePolling(() => refresh({ silent: true }), 10000)
</script>

<template>
  <div>
    <div class="mb-6">
      <p class="text-[13px] font-medium text-brand-600">{{ greeting }} 👋</p>
      <h1 class="mt-0.5 text-2xl font-bold tracking-tight text-slate-900">Today at a glance</h1>
      <p class="mt-0.5 flex items-center gap-2.5 text-sm text-slate-500">
        {{ today }}
        <LastUpdated :at="lastUpdated" />
      </p>
    </div>

    <!-- Skeleton loading -->
    <div v-if="loading" class="space-y-6">
      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div v-for="i in 4" :key="i" class="card card-pad space-y-3">
          <div class="skeleton h-4 w-24"></div>
          <div class="skeleton h-8 w-32"></div>
        </div>
      </div>
      <div class="grid gap-4 lg:grid-cols-2">
        <div class="card card-pad space-y-3">
          <div class="skeleton h-5 w-40"></div>
          <div class="skeleton h-24 w-full"></div>
        </div>
        <div class="card card-pad space-y-3">
          <div class="skeleton h-5 w-40"></div>
          <div class="skeleton h-24 w-full"></div>
        </div>
      </div>
    </div>

    <div v-else-if="error" class="card border-red-200 bg-red-50 p-6 text-red-700">
      <p class="flex items-center gap-2 font-medium"><AppIcon name="alert-circle" :size="18" /> {{ error }}</p>
    </div>

    <template v-else-if="stats">
      <!-- KPI cards -->
      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard label="Active students" :value="stats.total_students" sub="Currently enrolled" icon="users" />
        <StatCard label="Classes" :value="stats.total_classes" sub="Running this term" icon="book-open" />
        <StatCard
          label="Not yet collected"
          :value="money(stats.unpaid_this_month.total)"
          :sub="`${stats.unpaid_this_month.count} unpaid fees this month`"
          icon="alert-circle"
          tone="danger"
        />
        <StatCard
          label="Collected this month"
          :value="money(stats.collected_this_month)"
          :sub="collectionRate !== null ? `${collectionRate}% collection rate` : 'No fees billed yet'"
          icon="wallet"
          tone="success"
        />
      </div>

      <div class="mt-6 grid gap-4 lg:grid-cols-2">
        <!-- Attendance today -->
        <section class="card card-pad">
          <div class="mb-4 flex items-center justify-between">
            <h2 class="text-sm font-bold uppercase tracking-wide text-slate-500">Attendance today</h2>
            <span
              v-if="stats.attendance_today.rate !== null"
              class="rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-bold text-brand-700"
            >
              {{ stats.attendance_today.rate }}% present
            </span>
          </div>

          <EmptyState
            v-if="stats.attendance_today.rate === null"
            icon="calendar"
            title="No attendance marked yet today"
            description="Start the day by taking attendance for your classes."
          >
            <RouterLink to="/attendance" class="btn btn-primary btn-sm">Take attendance</RouterLink>
          </EmptyState>

          <template v-else>
            <div class="grid grid-cols-3 gap-3">
              <div class="rounded-xl bg-emerald-50 p-4 text-center">
                <p class="text-2xl font-bold text-emerald-700">{{ stats.attendance_today.present }}</p>
                <p class="text-xs font-medium text-emerald-600">Present</p>
              </div>
              <div class="rounded-xl bg-amber-50 p-4 text-center">
                <p class="text-2xl font-bold text-amber-700">{{ stats.attendance_today.late }}</p>
                <p class="text-xs font-medium text-amber-600">Late</p>
              </div>
              <div class="rounded-xl bg-red-50 p-4 text-center">
                <p class="text-2xl font-bold text-red-700">{{ stats.attendance_today.absent }}</p>
                <p class="text-xs font-medium text-red-600">Absent</p>
              </div>
            </div>
            <RouterLink
              to="/attendance"
              class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-brand-600 hover:text-brand-700"
            >
              Manage attendance <AppIcon name="chevron-right" :size="14" />
            </RouterLink>
          </template>
        </section>

        <!-- Unpaid by class -->
        <section class="card card-pad">
          <div class="mb-4 flex items-center justify-between">
            <h2 class="text-sm font-bold uppercase tracking-wide text-slate-500">Outstanding by class</h2>
            <RouterLink
              to="/payments"
              class="inline-flex items-center gap-1 text-sm font-semibold text-brand-600 hover:text-brand-700"
            >
              View payments <AppIcon name="chevron-right" :size="14" />
            </RouterLink>
          </div>
          <BarList :items="unpaidBars" />
        </section>
      </div>
    </template>
  </div>
</template>
