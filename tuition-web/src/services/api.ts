import axios from 'axios'

const BASE_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'
const TOKEN_KEY = 'tcms_token'
const USER_KEY = 'tcms_user'

export const session = {
  getToken: (): string | null => localStorage.getItem(TOKEN_KEY),
  setToken: (token: string) => localStorage.setItem(TOKEN_KEY, token),
  clear: () => {
    localStorage.removeItem(TOKEN_KEY)
    localStorage.removeItem(USER_KEY)
  },
  getUser: (): import('../types').AuthUser | null => {
    const raw = localStorage.getItem(USER_KEY)
    return raw ? (JSON.parse(raw) as import('../types').AuthUser) : null
  },
  setUser: (user: import('../types').AuthUser) => localStorage.setItem(USER_KEY, JSON.stringify(user)),
}

export const http = axios.create({
  baseURL: BASE_URL,
  headers: { Accept: 'application/json' },
})

http.interceptors.request.use((config) => {
  const token = session.getToken()
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

/** Extract a friendly message from any API error for display to non-technical users. */
export function errorMessage(e: unknown): string {
  if (axios.isAxiosError(e)) {
    const data = e.response?.data as { message?: string; errors?: Record<string, string[]> } | undefined
    if (data?.errors) {
      const first = Object.values(data.errors)[0]
      if (first?.length) return first[0]
    }
    if (data?.message) return data.message
    if (!e.response) return `Cannot reach the server. Is it running at ${BASE_URL}?`
    return `Something went wrong (${e.response.status}).`
  }
  return 'Unexpected error. Please try again.'
}
