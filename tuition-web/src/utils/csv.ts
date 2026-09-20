/**
 * Shared export helpers: build Excel-friendly CSV (UTF-8 BOM) or clipboard-ready
 * TSV for Google Sheets, and deliver them to the user.
 */

export type Cell = string | number | null | undefined

/** Build a CSV string. Every cell is quoted; the BOM keeps Excel happy with Khmer names. */
export function toCsv(rows: Cell[][]): string {
  return (
    '\uFEFF' +
    rows
      .map((r) => r.map((c) => `"${String(c ?? '').replace(/"/g, '""')}"`).join(','))
      .join('\r\n')
  )
}

/** Build a TSV string (paste straight into Google Sheets / Excel). */
export function toTsv(rows: Cell[][]): string {
  return rows.map((r) => r.map((c) => String(c ?? '').replace(/\t|\n/g, ' '))).join('\n')
}

/** Trigger a browser download of raw content as a file. */
export function download(content: string, filename: string, mime = 'text/csv;charset=utf-8'): void {
  const url = URL.createObjectURL(new Blob([content], { type: mime }))
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  document.body.appendChild(a)
  a.click()
  a.remove()
  URL.revokeObjectURL(url)
}

/** Build + download CSV in one call. */
export function downloadCsv(rows: Cell[][], filename: string): void {
  download(toCsv(rows), filename)
}

/** Copy rows as TSV to the clipboard. Returns false if the clipboard is unavailable. */
export async function copyTsv(rows: Cell[][]): Promise<boolean> {
  try {
    await navigator.clipboard.writeText(toTsv(rows))
    return true
  } catch {
    return false
  }
}
