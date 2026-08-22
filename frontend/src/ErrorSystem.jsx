import { Component, createRef, useEffect, useState } from 'react'
import { AlertTriangle, Clipboard, MapPin, RefreshCw, X } from 'lucide-react'
import { createClientError, ERROR_EVENT } from './error-utils'
import { useDialogFocus } from './dialog-focus'

const titleFor = error => {
  if (error.code === 'VALIDATION_ERROR' || error.status === 422) return 'Periksa data yang diisi'
  if (error.status === 401) return 'Sesi masuk diperlukan'
  if (error.status === 403) return 'Akses tidak diizinkan'
  if (error.status === 404) return 'Data tidak ditemukan'
  if (error.code === 'CONNECTION_FAILED') return 'Koneksi ke server terputus'
  return 'Tindakan belum berhasil'
}

const errorText = error => [
  titleFor(error),
  error.message,
  `Modul: ${error.location?.module || '-'}`,
  `Halaman: ${error.location?.page || '-'}`,
  `Endpoint: ${error.location?.endpoint || '-'}`,
  ...error.fields.flatMap(field => field.messages.map(message => `${field.label}: ${message}`)),
  `ID Error: ${error.errorId}`,
].join('\n')

const conciseValidationMessage = (field, message) => {
  const size = String(message).match(/maksimal\s+(\d+)\s+kilobita/i)
  if (size) {
    const megabytes = Number(size[1]) / 1024
    const capacity = Number.isInteger(megabytes) ? megabytes : megabytes.toFixed(1)
    return `${field.label} melewati kapasitas ${capacity} MB.`
  }
  return String(message).replace(/^\w/, letter => letter.toUpperCase())
}

function ErrorCard({error, close}) {
  const [copied, setCopied] = useState(false)
  const dialogRef = useDialogFocus(true, close)
  const validation = error.code === 'VALIDATION_ERROR' || error.status === 422
  const validationMessages = error.fields.flatMap(field => field.messages.map(message => conciseValidationMessage(field, message)))
  const copy = async () => {
    try {
      await navigator.clipboard.writeText(errorText(error))
      setCopied(true)
      setTimeout(() => setCopied(false), 1800)
    } catch {
      setCopied(false)
    }
  }

  if (validation) return <div className="responsive-dialog-shell z-[200] bg-ink/65 backdrop-blur-sm">
    <section ref={dialogRef} tabIndex="-1" role="alertdialog" aria-modal="true" aria-labelledby="validation-error-title" className="responsive-dialog-panel max-w-md border border-rose-200 bg-white p-5 shadow-2xl focus:outline-none focus-visible:ring-4 focus-visible:ring-rose-300 sm:p-6">
      <div className="flex items-start gap-3">
        <span className="grid size-11 shrink-0 place-items-center rounded-2xl bg-rose-600 text-white"><AlertTriangle size={21}/></span>
        <div className="min-w-0 flex-1">
          <h2 id="validation-error-title" className="font-display text-lg font-bold text-slate-900 sm:text-xl">{validationMessages.length===1?validationMessages[0]:'Periksa data berikut'}</h2>
          {validationMessages.length>1&&<ul className="mt-3 grid gap-2 text-sm leading-6 text-slate-600">{validationMessages.map((message,index)=><li key={index} className="rounded-xl bg-rose-50 px-3 py-2">{message}</li>)}</ul>}
          {!validationMessages.length&&<p className="mt-1 text-sm leading-6 text-slate-600">{error.message}</p>}
        </div>
        <button type="button" onClick={close} aria-label="Tutup pesan error" className="grid size-9 shrink-0 place-items-center rounded-full bg-slate-100 text-slate-500"><X size={17}/></button>
      </div>
    </section>
  </div>

  return <div className="responsive-dialog-shell z-[200] bg-ink/65 backdrop-blur-sm">
    <section ref={dialogRef} tabIndex="-1" role="alertdialog" aria-modal="true" aria-labelledby="application-error-title" aria-describedby="application-error-message" className="responsive-dialog-panel max-w-lg border border-rose-200 bg-white shadow-2xl focus:outline-none focus-visible:ring-4 focus-visible:ring-rose-300">
    <div className="flex shrink-0 items-start gap-3 border-b border-rose-100 bg-rose-50 p-4 sm:p-5">
      <span className="grid size-11 shrink-0 place-items-center rounded-2xl bg-rose-600 text-white"><AlertTriangle size={21}/></span>
      <div className="min-w-0 flex-1"><div className="text-xs font-black uppercase tracking-[.14em] text-rose-600">{error.code}</div><h2 id="application-error-title" className="mt-1 font-display text-lg font-bold text-slate-900 sm:text-xl">{titleFor(error)}</h2><p id="application-error-message" className="mt-1 text-sm leading-6 text-slate-600">{error.message}</p></div>
      <button type="button" onClick={close} aria-label="Tutup pesan error" className="grid size-9 shrink-0 place-items-center rounded-full bg-white text-slate-500"><X size={17}/></button>
    </div>
    <div className="responsive-dialog-scroll space-y-4 p-4 sm:p-5">
      <div className="rounded-2xl bg-slate-50 p-4"><div className="flex items-center gap-2 text-xs font-black uppercase tracking-wider text-slate-500"><MapPin size={14}/>Lokasi terdeteksi</div><div className="mt-2 font-bold text-slate-900">{error.location?.module}</div><div className="mt-1 break-all font-mono text-xs leading-5 text-slate-500">Halaman {error.location?.page}<br/>{error.location?.endpoint}</div></div>
      {!!error.fields.length&&<div><div className="text-xs font-black uppercase tracking-wider text-slate-500">Bagian yang perlu diperiksa</div><div className="mt-2 grid gap-2">{error.fields.map(field=><div key={field.key} className="rounded-xl border border-rose-100 p-3"><b className="text-sm text-rose-700">{field.label}</b>{field.messages.map((message,index)=><p key={index} className="mt-1 text-xs leading-5 text-slate-600">{message}</p>)}</div>)}</div></div>}
      {error.technical&&<details className="rounded-xl border p-3 text-xs"><summary className="cursor-pointer font-bold text-slate-600">Detail teknis (mode debug)</summary><pre className="mt-3 overflow-x-auto whitespace-pre-wrap break-all text-[11px] leading-5 text-slate-500">{JSON.stringify(error.technical,null,2)}</pre></details>}
      <div className="flex flex-wrap items-center justify-between gap-3 border-t pt-4"><div><div className="text-[10px] font-bold uppercase tracking-wider text-slate-400">ID Error</div><code className="text-xs font-bold text-slate-700">{error.errorId}</code></div><button type="button" onClick={copy} className="btn-ghost py-2 text-xs"><Clipboard size={14}/>{copied?'Tersalin':'Salin detail'}</button></div>
    </div>
  </section>
  </div>
}

export function ApplicationErrorCenter() {
  const [error, setError] = useState(null)
  useEffect(() => {
    const receive = event => setError(event.detail)
    const browserError = event => {
      if (!event.error?.reported) setError(createClientError(event.error || event.message))
    }
    const rejected = event => {
      if (!event.reason?.reported) setError(createClientError(event.reason, 'Proses halaman'))
    }
    window.addEventListener(ERROR_EVENT, receive)
    window.addEventListener('error', browserError)
    window.addEventListener('unhandledrejection', rejected)
    return () => {
      window.removeEventListener(ERROR_EVENT, receive)
      window.removeEventListener('error', browserError)
      window.removeEventListener('unhandledrejection', rejected)
    }
  }, [])
  return error ? <ErrorCard error={error} close={() => setError(null)}/> : null
}

export class ApplicationErrorBoundary extends Component {
  constructor(props) {
    super(props)
    this.state = {error: null}
    this.errorRef = createRef()
  }

  static getDerivedStateFromError(cause) {
    return {error: createClientError(cause, 'Render antarmuka')}
  }

  componentDidCatch(error, info) {
    if (import.meta.env.DEV) this.setState({error: {...this.state.error, technical: {...this.state.error.technical, componentStack: info.componentStack}}})
  }

  componentDidUpdate(previousProps, previousState) {
    if (!previousState.error && this.state.error) this.errorRef.current?.focus({preventScroll: true})
  }

  render() {
    if (!this.state.error) return this.props.children
    const error = this.state.error
    return <main className="grid min-h-[100dvh] place-items-center bg-slate-100 p-4 sm:p-6"><section ref={this.errorRef} tabIndex="-1" role="alert" aria-labelledby="fatal-error-title" className="max-h-[calc(100dvh-2rem)] w-full max-w-2xl overflow-y-auto rounded-[24px] bg-white p-5 shadow-xl focus:outline-none focus-visible:ring-4 focus-visible:ring-rose-300 sm:rounded-[30px] sm:p-7"><span className="grid size-14 place-items-center rounded-2xl bg-rose-600 text-white"><AlertTriangle/></span><h1 id="fatal-error-title" className="mt-5 font-display text-2xl font-bold sm:text-3xl">Halaman tidak dapat ditampilkan</h1><p className="mt-3 leading-7 text-slate-600">Terjadi kesalahan pada antarmuka. Muat ulang halaman; jika masih terjadi, salin ID error untuk pengelola.</p><div className="mt-5 break-words rounded-2xl bg-slate-50 p-4 text-sm"><b>Lokasi:</b> {error.location.module}<br/><b>Halaman:</b> {error.location.page}<br/><b>ID Error:</b> <code>{error.errorId}</code></div><button type="button" onClick={() => window.location.reload()} className="btn-dark mt-6 w-full sm:w-auto"><RefreshCw size={16}/>Muat ulang halaman</button></section></main>
  }
}
