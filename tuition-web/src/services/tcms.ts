import { http } from './api'
import type {
  AttendanceMap,
  AttendanceRecord,
  AuthUser,
  ClassRoom,
  DashboardStats,
  Payment,
  Student,
  Teacher,
} from '../types'

export const authService = {
  async login(email: string, password: string): Promise<{ token: string; user: AuthUser }> {
    const { data } = await http.post<{ token: string; user: AuthUser }>('/login', { email, password })
    return data
  },
  async logout(): Promise<void> {
    await http.post('/logout')
  },
}

export const dashboardService = {
  async stats(): Promise<DashboardStats> {
    const { data } = await http.get<{ data: DashboardStats }>('/dashboard')
    return data.data
  },
}

export const studentService = {
  async list(search = ''): Promise<Student[]> {
    const { data } = await http.get<{ data: Student[] }>('/students', { params: search ? { search } : {} })
    return data.data
  },
  async create(payload: Partial<Student>): Promise<Student> {
    const { data } = await http.post<{ data: Student }>('/students', payload)
    return data.data
  },
  async update(id: number, payload: Partial<Student>): Promise<Student> {
    const { data } = await http.put<{ data: Student }>(`/students/${id}`, payload)
    return data.data
  },
  async remove(id: number): Promise<void> {
    await http.delete(`/students/${id}`)
  },
}

export const teacherService = {
  async list(): Promise<Teacher[]> {
    const { data } = await http.get<{ data: Teacher[] }>('/teachers')
    return data.data
  },
  async create(payload: { full_name: string; subject: string; email: string; password: string }): Promise<Teacher> {
    const { data } = await http.post<{ data: Teacher }>('/teachers', payload)
    return data.data
  },
  async update(
    id: number,
    payload: Partial<{ full_name: string; subject: string; email: string; password: string }>,
  ): Promise<Teacher> {
    const { data } = await http.put<{ data: Teacher }>(`/teachers/${id}`, payload)
    return data.data
  },
  async remove(id: number): Promise<void> {
    await http.delete(`/teachers/${id}`)
  },
}

export const classService = {
  async list(): Promise<ClassRoom[]> {
    const { data } = await http.get<{ data: ClassRoom[] }>('/classes')
    return data.data
  },
  async create(payload: { name: string; teacher_id: number; schedule: string; fee_amount: number }): Promise<ClassRoom> {
    const { data } = await http.post<{ data: ClassRoom }>('/classes', payload)
    return data.data
  },
  async update(id: number, payload: Partial<{ name: string; teacher_id: number; schedule: string; fee_amount: number }>): Promise<ClassRoom> {
    const { data } = await http.put<{ data: ClassRoom }>(`/classes/${id}`, payload)
    return data.data
  },
  async remove(id: number): Promise<void> {
    await http.delete(`/classes/${id}`)
  },
  async attendance(classId: number, date: string): Promise<AttendanceMap> {
    const { data } = await http.get<{ data: AttendanceMap }>(`/classes/${classId}/attendance`, { params: { date } })
    return data.data
  },
  async saveAttendance(classId: number, date: string, records: AttendanceRecord[]): Promise<void> {
    await http.post(`/classes/${classId}/attendance`, { date, records })
  },
  async enroll(classId: number, studentId: number): Promise<void> {
    await http.post('/enrollments', { class_room_id: classId, student_id: studentId })
  },
  async unenroll(studentId: number, classId: number): Promise<void> {
    await http.delete(`/students/${studentId}/classes/${classId}`)
  },
}

export const paymentService = {
  async list(status = '', search = ''): Promise<Payment[]> {
    const { data } = await http.get<{ data: Payment[] }>('/payments', {
      params: { ...(status && status !== 'all' ? { status } : {}), ...(search ? { search } : {}) },
    })
    return data.data
  },
  async markPaid(
    id: number,
    method: import('../types').PaymentMethod = 'cash',
    reference?: string,
  ): Promise<Payment> {
    const { data } = await http.patch<{ data: Payment }>(`/payments/${id}/mark-paid`, {
      method,
      ...(reference ? { reference } : {}),
    })
    return data.data
  },
}
