import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { session } from '../services/api'
import { authService } from '../services/tcms'
import type { AuthUser } from '../types'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<AuthUser | null>(session.getUser())
  const token = ref<string | null>(session.getToken())
  const loading = ref(false)
  const error = ref<string | null>(null)

  const isAuthenticated = computed(() => token.value !== null)
  const isAdmin = computed(() => user.value?.role === 'admin')

  async function login(email: string, password: string) {
    loading.value = true
    error.value = null
    try {
      const { token: newToken, user: newUser } = await authService.login(email, password)
      token.value = newToken
      user.value = newUser
      session.setToken(newToken)
      session.setUser(newUser)
    } finally {
      loading.value = false
    }
  }

  async function logout() {
    try {
      await authService.logout()
    } catch {
      // token may already be invalid; clear locally regardless
    }
    token.value = null
    user.value = null
    session.clear()
  }

  return { user, token, loading, error, isAuthenticated, isAdmin, login, logout }
})
