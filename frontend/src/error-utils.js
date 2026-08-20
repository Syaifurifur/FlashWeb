export const ERROR_EVENT = 'bsi-flash:application-error'

const fieldLabel = key => key.split('.').map(part => /^\d+$/.test(part)
  ? `Data ${Number(part) + 1}`
  : part.replaceAll('_', ' ').replace(/\b\w/g, letter => letter.toUpperCase())).join(' › ')

const moduleFromPath = path => {
  if (/\/(login|logout|forgot-password|reset-password)/.test(path)) return 'Autentikasi akun'
  if (/\/manage\/(accounts|roles)/.test(path)) return 'Kelola akun dan akses'
  if (/\/(manage\/venues|manage\/city-staff|venues)/.test(path)) return 'Tempat dan kota'
  if (/\/(manage\/registrations|participant|registrations)/.test(path)) return 'Pendaftaran peserta'
  if (path.includes('/notifications')) return 'Notifikasi'
  if (/\/(judging|judge)/.test(path)) return 'Penilaian'
  if (path.includes('/tournaments')) return 'Drawing dan bagan'
  if (path.includes('/schedules')) return 'Jadwal pertandingan'
  if (path.includes('/content')) return 'Konten website'
  if (path.includes('/competitions')) return 'Manajemen lomba'
  if (path.includes('/editions')) return 'Tahun kegiatan'
  return 'Layanan aplikasi'
}

const localErrorId = () => `WEB-${new Date().toISOString().replace(/\D/g, '').slice(0, 14)}-${Math.random().toString(36).slice(2, 7).toUpperCase()}`

export function createApiError({data = {}, status = 0, path, method = 'GET', cause, network = false}) {
  const payload = data && typeof data === 'object' && !(data instanceof Blob) ? data : {}
  const serverError = payload.error || {}
  const errors = payload.errors || {}
  const fields = serverError.fields || Object.entries(errors).map(([key, messages]) => ({
    key,
    label: fieldLabel(key),
    messages: Array.isArray(messages) ? messages : [String(messages)],
  }))
  const message = network
    ? 'Tidak dapat terhubung ke server. Periksa koneksi dan pastikan layanan aplikasi berjalan.'
    : payload.message || 'Permintaan gagal diproses. Silakan coba kembali.'
  const error = new Error(message, cause ? {cause} : undefined)
  error.name = 'ApplicationError'
  error.status = status
  error.code = serverError.code || (network ? 'CONNECTION_FAILED' : 'REQUEST_FAILED')
  error.errorId = serverError.id || localErrorId()
  error.detectedAt = serverError.detected_at || new Date().toISOString()
  error.location = {
    module: serverError.location?.module || moduleFromPath(path),
    endpoint: serverError.location?.endpoint || `${method.toUpperCase()} /api${path}`,
    page: window.location.pathname,
  }
  error.fields = fields
  error.errors = fields.length ? {
    ...errors,
    general: [`${message} ${[...new Set(fields.flatMap(field => field.messages))].join(' ')}`.trim()],
  } : {general: [message]}
  error.technical = serverError.technical
  error.conflicts = payload.conflicts
  return error
}

export function createClientError(cause, module = 'Antarmuka halaman') {
  const error = cause instanceof Error ? cause : new Error(String(cause || 'Terjadi kesalahan pada antarmuka.'))
  if (error.location) return error
  error.name = 'ApplicationError'
  error.code = 'INTERFACE_ERROR'
  error.errorId = localErrorId()
  error.detectedAt = new Date().toISOString()
  error.location = {module, endpoint: window.location.pathname, page: window.location.pathname}
  error.fields = []
  if (import.meta.env.DEV) error.technical = {message: error.message, stack: error.stack}
  return error
}

export function reportApplicationError(error) {
  const normalized = error?.location ? error : createClientError(error)
  normalized.reported = true
  window.dispatchEvent(new CustomEvent(ERROR_EVENT, {detail: normalized}))
  return normalized
}
