<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import AppIcon from '../components/AppIcon.vue'
import { errorMessage } from '../services/api'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const auth = useAuthStore()

const email = ref('admin@tuition.test')
const password = ref('password')
const error = ref<string | null>(null)
const showPassword = ref(false)

async function submit() {
  error.value = null
  try {
    await auth.login(email.value, password.value)
    router.push('/')
  } catch (e) {
    error.value = errorMessage(e)
  }
}

function fill(kind: 'admin' | 'teacher') {
  if (kind === 'admin') {
    email.value = 'admin@tuition.test'
    password.value = 'password'
  } else {
    email.value = 'sokha@tuition.test'
    password.value = 'password'
  }
}
</script>

<template>
  <div class="grid min-h-screen lg:grid-cols-2">
    <!-- Brand panel -->
    <div class="relative hidden flex-col justify-between overflow-hidden bg-brand-950 p-10 text-white lg:flex">
      <!-- Decorative glows -->
      <div class="pointer-events-none absolute -left-32 -top-32 h-96 w-96 rounded-full bg-brand-600/30 blur-3xl"></div>
      <div class="pointer-events-none absolute -bottom-40 -right-24 h-[28rem] w-[28rem] rounded-full bg-brand-500/20 blur-3xl"></div>

      <div class="relative flex items-center gap-3">
        <div class="grid h-10 w-10 place-items-center rounded-xl bg-white/10 text-sm font-bold backdrop-blur">TC</div>
        <p class="font-bold tracking-tight">Tuition Center</p>
      </div>

      <div class="relative">
        <h1 class="max-w-md text-4xl font-bold leading-tight tracking-tight">
          Run your tuition center with clarity.
        </h1>
        <p class="mt-4 max-w-md text-base leading-relaxed text-brand-200">
          Students, classes, attendance, and fees — one clean workspace for everything that keeps
          your center running.
        </p>

        <ul class="mt-10 space-y-4">
          <li v-for="f in [
            { icon: 'users', text: 'Track every student and guardian contact' },
            { icon: 'check-circle', text: 'Take attendance for any class in seconds' },
            { icon: 'wallet', text: 'See who owes what — and collect faster' },
          ]" :key="f.icon" class="flex items-center gap-3 text-sm text-brand-100">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-white/10">
              <AppIcon :name="f.icon" :size="16" />
            </span>
            {{ f.text }}
          </li>
        </ul>
      </div>

      <p class="relative text-xs text-brand-300/70">© {{ new Date().getFullYear() }} Tuition Center Management</p>
    </div>

    <!-- Form panel -->
    <div class="grid place-items-center bg-canvas px-4 py-12">
      <div class="w-full max-w-sm">
        <div class="mb-8 text-center lg:hidden">
          <div class="mx-auto mb-3 grid h-12 w-12 place-items-center rounded-xl bg-brand-600 text-lg font-bold text-white">TC</div>
          <h1 class="text-xl font-bold text-slate-900">Tuition Center</h1>
        </div>

        <h2 class="text-2xl font-bold tracking-tight text-slate-900">Welcome back</h2>
        <p class="mt-1 text-sm text-slate-500">Sign in to manage your center</p>

        <form class="mt-8 space-y-5" @submit.prevent="submit">
          <div>
            <label class="label">Email</label>
            <input
              v-model="email"
              type="email"
              required
              autocomplete="username"
              class="input"
              placeholder="you@center.com"
            />
          </div>

          <div>
            <label class="label">Password</label>
            <div class="relative">
              <input
                v-model="password"
                :type="showPassword ? 'text' : 'password'"
                required
                autocomplete="current-password"
                class="input pr-11"
                placeholder="••••••••"
              />
              <button
                type="button"
                class="absolute right-3 top-1/2 -translate-y-1/2 rounded-md p-1 text-slate-400 hover:text-slate-600"
                :aria-label="showPassword ? 'Hide password' : 'Show password'"
                @click="showPassword = !showPassword"
              >
                <AppIcon :name="showPassword ? 'eye-off' : 'eye'" :size="16" />
              </button>
            </div>
          </div>

          <p v-if="error" class="flex items-center gap-2 rounded-xl bg-red-50 px-3.5 py-2.5 text-sm font-medium text-red-700">
            <AppIcon name="alert-circle" :size="16" />
            {{ error }}
          </p>

          <button type="submit" :disabled="auth.loading" class="btn btn-primary w-full py-3">
            {{ auth.loading ? 'Signing in…' : 'Sign in' }}
          </button>
        </form>

        <div class="mt-8 card p-4">
          <p class="mb-2.5 text-xs font-semibold uppercase tracking-wide text-slate-400">Demo accounts</p>
          <div class="flex gap-2">
            <button type="button" class="btn btn-secondary btn-sm flex-1" @click="fill('admin')">
              Admin
            </button>
            <button type="button" class="btn btn-secondary btn-sm flex-1" @click="fill('teacher')">
              Teacher
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
