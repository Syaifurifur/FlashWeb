import { useEffect, useRef } from 'react'

const focusableSelector = [
  'a[href]',
  'button:not([disabled])',
  'input:not([disabled])',
  'select:not([disabled])',
  'textarea:not([disabled])',
  'details > summary:first-of-type',
  '[tabindex]:not([tabindex="-1"])',
].join(',')

export function useDialogFocus(open, onClose) {
  const dialogRef = useRef(null)
  const closeRef = useRef(onClose)

  useEffect(() => {
    closeRef.current = onClose
  }, [onClose])

  useEffect(() => {
    if (!open) return undefined

    const dialog = dialogRef.current
    const previousFocus = document.activeElement
    const previousOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'

    const focusDialog = () => dialog?.focus({preventScroll: true})
    const frame = window.requestAnimationFrame(focusDialog)

    const handleKeyDown = event => {
      if (event.key === 'Escape') {
        event.preventDefault()
        closeRef.current?.()
        return
      }

      if (event.key !== 'Tab' || !dialog) return
      const focusable = [...dialog.querySelectorAll(focusableSelector)]
        .filter(element => element.getClientRects().length > 0)

      if (!focusable.length) {
        event.preventDefault()
        focusDialog()
        return
      }

      const first = focusable[0]
      const last = focusable[focusable.length - 1]
      if (!dialog.contains(document.activeElement)) {
        event.preventDefault()
        ;(event.shiftKey ? last : first).focus()
      } else if (event.shiftKey && (document.activeElement === first || document.activeElement === dialog)) {
        event.preventDefault()
        last.focus()
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault()
        first.focus()
      }
    }

    document.addEventListener('keydown', handleKeyDown)
    return () => {
      window.cancelAnimationFrame(frame)
      document.removeEventListener('keydown', handleKeyDown)
      document.body.style.overflow = previousOverflow
      if (previousFocus instanceof HTMLElement && previousFocus.isConnected) {
        window.requestAnimationFrame(() => previousFocus.focus({preventScroll: true}))
      }
    }
  }, [open])

  return dialogRef
}
