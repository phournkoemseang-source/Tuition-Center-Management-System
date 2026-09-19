export interface AuthUser {
  id: number
  name: string
  email: string
  role: 'admin' | 'teacher'
}

export interface Student {
  id: number
  full_name: string
  phone: string | null
  parent_contact: string | null
  enrolled_date: string
  status: 'active' | 'inactive'
  class_rooms?: ClassRoom[]
  payments?: Payment[]
}

export interface Teacher {
  id: number
  full_name: string
  subject: string
  user_id: number
}

export interface ClassRoom {
  id: number
  name: string
  teacher_id: number
  schedule: string
  fee_amount: string
  teacher: Teacher
  students: Student[]
  active_students_count: number
}

export interface Payment {
  id: number
  student_id: number
  class_room_id: number
  amount: string
  due_date: string
  paid_date: string | null
  status: 'unpaid' | 'paid' | 'overdue'
  method: string | null
  reference?: string | null
  student: Student
  classRoom: ClassRoom
}

export type PaymentMethod = 'cash' | 'khqr' | 'aba' | 'wing' | 'bank'

export interface AttendanceRecord {
  student_id: number
  status: 'present' | 'absent' | 'late'
}

export interface DashboardStats {
  total_students: number
  total_classes: number
  unpaid_this_month: { total: number; count: number }
  collected_this_month: number
  attendance_today: { present: number; late: number; absent: number; rate: number | null }
  unpaid_by_class: { id: number; name: string; unpaid_count: number; unpaid_total: string }[]
}

export type AttendanceMap = Record<string, 'present' | 'absent' | 'late'>
