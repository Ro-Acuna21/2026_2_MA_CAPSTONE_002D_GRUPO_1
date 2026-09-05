import { useState, type FormEvent, type ReactNode } from 'react'
import { Button } from './ui/button'

export function ActionForm({ children, onSave, label = 'Guardar', className = '', reset = true }: { children: ReactNode; onSave: (values: Record<string, string>) => void | Promise<void>; label?: string; className?: string; reset?: boolean }) {
  const [error, setError] = useState(''), [busy, setBusy] = useState(false)
  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault(); const form = event.currentTarget; setError(''); setBusy(true)
    try { await onSave(Object.fromEntries(Array.from(new FormData(form).entries()).map(([key, value]) => [key, String(value)]))); if (reset) form.reset() }
    catch (failure) { setError(failure instanceof Error ? failure.message : 'No fue posible guardar los cambios.') }
    finally { setBusy(false) }
  }
  return <form onSubmit={submit} className={`grid gap-4 ${className}`}><fieldset disabled={busy} className="contents">{children}<div className="col-span-full">{error && <p role="alert" className="mb-3 rounded-lg bg-rose-50 p-3 text-sm text-rose-700">{error}</p>}<Button disabled={busy}>{busy ? 'Guardando…' : label}</Button></div></fieldset></form>
}
