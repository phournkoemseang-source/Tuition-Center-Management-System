<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import AppIcon, { type IconName } from '../components/AppIcon.vue'
import Avatar from '../components/Avatar.vue'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const nav: { to: string; label: string; icon: IconName }[] = [
  { to: '/', label: 'Dashboard', icon: 'layout-dashboard' },
  { to: '/students', label: 'Students', icon: 'users' },
  { to: '/teachers', label: 'Teachers', icon: 'graduation-cap' },
  { to: '/assignments', label: 'Assignments', icon: 'user-check' },
  { to: '/classes', label: 'Classes', icon: 'book-open' },
  { to: '/attendance', label: 'Attendance', icon: 'check-circle' },
  { to: '/payments', label: 'Payments', icon: 'wallet' },
  { to: '/reports', label: 'Reports', icon: 'bar-chart' },
]

const mobileOpen = ref(false)
watch(
  () => route.fullPath,
  () => (mobileOpen.value = false),
)

const pageTitle = computed(
  () => nav.find((n) => n.to === route.path)?.label ?? 'Tuition Center',
)

async function logout() {
  await auth.logout()
  router.push('/login')
}
</script>

<template>
  <div class="min-h-screen">
    <!-- ===== Sidebar (desktop) ===== -->
    <aside
      class="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col border-r border-slate-200 bg-white lg:flex"
    >
      <!-- Brand -->
      <div class="flex h-16 items-center gap-3 border-b border-slate-100 px-6">
        <div class="grid h-9 w-9 place-items-center rounded-xl bg-brand-600 text-sm font-bold text-white shadow-sm">
          TC
        </div>
        <div class="min-w-0">
          <p class="truncate text-sm font-bold text-slate-900">Tuition Center</p>
          <p class="text-[11px] font-medium uppercase tracking-wider text-slate-400">Management</p>
        </div>
      </div>

      <!-- Nav -->
      <nav class="flex-1 space-y-1 overflow-y-auto p-4">
        <p class="mb-2 px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400">Main menu</p>
        <RouterLink
          v-for="item in nav"
          :key="item.to"
          :to="item.to"
          class="nav-item"
          :class="route.path === item.to ? 'nav-item-active' : ''"
        >
          <AppIcon :name="item.icon" :size="18" class="shrink-0 opacity-90" />
          {{ item.label }}
        </RouterLink>
      </nav>

      <!-- Sidebar footer / user -->
      <div class="border-t border-slate-100 p-4">
        <div class="flex items-center gap-3 rounded-xl bg-slate-50 p-3">
          <Avatar :name="auth.user?.name ?? 'U'" size="md" />
          <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-semibold text-slate-800">{{ auth.user?.name }}</p>
            <p class="text-xs capitalize text-slate-500">{{ auth.user?.role }}</p>
          </div>
          <button
            class="rounded-lg p-2 text-slate-400 transition hover:bg-white hover:text-red-600"
            title="Log out"
            @click="logout"
          >
            <AppIcon name="log-out" :size="16" />
          </button>
          <p class="sr-only">Log out</p>
        </div>
      </div>
    </aside>

    <!-- ===== Mobile drawer ===== -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition-opacity duration-200"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-opacity duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div v-if="mobileOpen" class="fixed inset-0 z-40 bg-slate-950/45 lg:hidden" @click="mobileOpen = false" />
      </Transition>
      <Transition
        enter-active-class="transition-transform duration-200 ease-out"
        enter-from-class="-translate-x-full"
        enter-to-class="translate-x-0"
        leave-active-class="transition-transform duration-150 ease-in"
        leave-from-class="translate-x-0"
        leave-to-class="-translate-x-full"
      >
        <aside
          v-if="mobileOpen"
          class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-slate-200 bg-white lg:hidden"
        >
          <div class="flex h-16 items-center justify-between border-b border-slate-100 px-5">
            <div class="flex items-center gap-3">
              <div class="grid h-9 w-9 place-items-center rounded-xl bg-brand-600 text-sm font-bold text-white">TC</div>
              <p class="text-sm font-bold text-slate-900">Tuition Center</p>
            </div>
            <button class="rounded-lg p-2 text-slate-400 hover:bg-slate-100" @click="mobileOpen = false">
              <AppIcon name="x" :size="18" />
            </button>
          </div>
          <nav class="flex-1 space-y-1 overflow-y-auto p-4">
            <RouterLink
              v-for="item in nav"
              :key="item.to"
              :to="item.to"
              class="nav-item"
              :class="route.path === item.to ? 'nav-item-active' : ''"
            >
              <AppIcon :name="item.icon" :size="18" />
              {{ item.label }}
            </RouterLink>
          </nav>
          <div class="border-t border-slate-100 p-4">
            <button class="btn btn-secondary w-full" @click="logout">
              <AppIcon name="log-out" :size="16" />
              Log out
            </button>
          </div>
        </aside>
      </Transition>
    </Teleport>

    <!-- ===== Main column ===== -->
    <div class="lg:pl-64">
      <!-- Topbar -->
      <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/90 backdrop-blur">
        <div class="flex h-16 items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
          <div class="flex min-w-0 items-center gap-3">
            <button
              class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:hidden"
              aria-label="Open menu"
              @click="mobileOpen = true"
            >
              <AppIcon name="menu" :size="20" />
            </button>
            <div class="hidden min-w-0 items-center gap-2 text-sm sm:flex">
              <span class="text-slate-400">Tuition Center</span>
              <AppIcon name="chevron-right" :size="14" class="text-slate-300" />
              <span class="truncate font-semibold text-slate-800">{{ pageTitle }}</span>
            </div>
          </div>

          <div class="flex items-center gap-3">
            <span
              class="hidden items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/20 md:inline-flex"
            >
              <span class="relative flex h-2 w-2">
                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
              </span>
              Online
            </span>
            <div class="hidden text-right sm:block">
              <p class="text-sm font-semibold text-slate-800">{{ auth.user?.name }}</p>
              <p class="text-xs capitalize text-slate-500">{{ auth.user?.role }}</p>
            </div>
            <Avatar :name="auth.user?.name ?? 'U'" size="md" />
          </div>
        </div>
      </header>

      <!-- Page -->
      <main class="mx-auto min-w-0 max-w-7xl px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
        <RouterView v-slot="{ Component }">
          <Transition
            name="page"
            mode="out-in"
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="translate-y-1 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
          >
            <component :is="Component" />
          </Transition>
        </RouterView>
      </main>
    </div>

  </div>
</template>
