import { createRouter, createWebHistory } from 'vue-router'
import { session } from '../services/api'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('../views/LoginView.vue'),
      meta: { public: true, title: 'Sign in' },
    },
    {
      path: '/',
      component: () => import('../layouts/AppLayout.vue'),
      children: [
        { path: '', name: 'dashboard', component: () => import('../views/DashboardView.vue'), meta: { title: 'Dashboard' } },
        { path: 'students', name: 'students', component: () => import('../views/StudentsView.vue'), meta: { title: 'Students' } },
        { path: 'teachers', name: 'teachers', component: () => import('../views/TeachersView.vue'), meta: { title: 'Teachers' } },
        { path: 'assignments', name: 'assignments', component: () => import('../views/AssignmentsView.vue'), meta: { title: 'Assignments' } },
        { path: 'classes', name: 'classes', component: () => import('../views/ClassesView.vue'), meta: { title: 'Classes' } },
        { path: 'attendance', name: 'attendance', component: () => import('../views/AttendanceView.vue'), meta: { title: 'Attendance' } },
        { path: 'payments', name: 'payments', component: () => import('../views/PaymentsView.vue'), meta: { title: 'Payments' } },
        { path: 'reports', name: 'reports', component: () => import('../views/ReportsView.vue'), meta: { title: 'Reports' } },
      ],
    },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
})

router.beforeEach((to) => {
  const isAuthed = session.getToken() !== null
  if (!to.meta.public && !isAuthed) {
    return { name: 'login' }
  }
  if (to.name === 'login' && isAuthed) {
    return { name: 'dashboard' }
  }
})

router.afterEach((to) => {
  document.title = to.meta.title
    ? `${to.meta.title} · Tuition Center`
    : 'Tuition Center Management'
})

export default router
