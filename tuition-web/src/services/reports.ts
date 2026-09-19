import { http } from './api'
import type { Payment } from '../types'

export interface MonthlyReport {
  period: { year: number; month: number }
  totals: {
    billed: number
    collected: number
    outstanding: number
    invoice_count: number
    paid_count: number
    unpaid_count: number
    overdue_count: number
  }
  per_class: {
    id: number
    name: string
    invoices: number
    billed_total: string
    collected_total: string
    outstanding_total: string
    overdue_count: number
  }[]
  by_method: { method: string; total: string; count: number }[]
}

export interface ReportRow {
  id: number
  student: string
  class: string
  amount: string
  due_date: string
  paid_date: string | null
  status: Payment['status']
  method: string
}

export const reportService = {
  async monthly(year: number, month: number): Promise<MonthlyReport> {
    const { data } = await http.get<{ data: MonthlyReport }>('/reports/monthly', { params: { year, month } })
    return data.data
  },

  async monthlyStudents(year: number, month: number): Promise<ReportRow[]> {
    const { data } = await http.get<{ data: ReportRow[] }>('/reports/monthly/students', { params: { year, month } })
    return data.data
  },

  /** Download the CSV export via authenticated blob request. */
  async exportCsv(year: number, month: number): Promise<void> {
    const response = await http.get('/reports/monthly/export', {
      params: { year, month },
      responseType: 'blob',
    })

    const disposition = String(response.headers['content-disposition'] ?? '')
    const match = /filename="?([^";]+)"?/.exec(disposition)
    const filename = match?.[1] ?? `tuition-report-${year}-${String(month).padStart(2, '0')}.csv`

    const url = URL.createObjectURL(response.data as Blob)
    const a = document.createElement('a')
    a.href = url
    a.download = filename
    document.body.appendChild(a)
    a.click()
    a.remove()
    URL.revokeObjectURL(url)
  },
}
