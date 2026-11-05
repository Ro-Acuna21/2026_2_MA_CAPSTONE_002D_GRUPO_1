import { useEffect, useState } from 'react'
import { toast } from 'sonner'
import { ActionForm } from './action-form'
import { Button } from './ui/button'
import { Field, Input } from './ui/form-controls'
import { centerAccessApi, type CenterAccess } from '@/services/center-access-api'
import { firstApiErrorMessage } from '@/services/http'

export function AccessForm({ centerId, platform = false }: { centerId: string; platform?: boolean }) {
  const [memberships, setMemberships] = useState<CenterAccess[]>([])
  const [status, setStatus] = useState<'loading' | 'ready' | 'error'>('loading')
  const [error, setError] = useState('')
  const [reload, setReload] = useState(0)
  const [busyId, setBusyId] = useState<number | null>(null)

  useEffect(() => {
    let cancelled = false
    centerAccessApi.list(platform, centerId).then((response) => {
      if (cancelled) return
      setMemberships(response.data)
      setStatus('ready')
      setError('')
    }).catch((failure: unknown) => {
      if (cancelled) return
      setError(firstApiErrorMessage(failure, 'No fue posible cargar los accesos del centro.'))
      setStatus('error')
    })
    return () => { cancelled = true }
  }, [centerId, platform, reload])

  useEffect(() => {
    if (!platform) return
    const refresh = () => setReload((value) => value + 1)
    window.addEventListener('medsync:staff-updated', refresh)
    return () => window.removeEventListener('medsync:staff-updated', refresh)
  }, [platform])

  async function assign(values: Record<string, string>) {
    const response = await centerAccessApi.assign(platform, centerId, {
      name: values.name.trim(), email: values.email.trim().toLowerCase(),
    }).catch((failure: unknown) => { throw new Error(firstApiErrorMessage(failure, 'No fue posible guardar el acceso.')) })
    setMemberships((current) => [...current.filter((item) => item.id !== response.data.id), response.data])
    if (platform) window.dispatchEvent(new Event('medsync:staff-updated'))
    if (response.data.invitation_pending && !response.invitation_sent) toast.warning(response.message)
    else toast.success(response.message)
  }

  async function changeAccess(item: CenterAccess, operation: 'toggle' | 'resend') {
    setBusyId(item.id)
    setError('')
    try {
      const response = operation === 'toggle'
        ? await centerAccessApi.setActive(platform, centerId, item.id, !item.is_active)
        : await centerAccessApi.resend(platform, centerId, item.id)
      setMemberships((current) => current.map((entry) => entry.id === item.id ? response.data : entry))
      toast.success(operation === 'resend' && 'message' in response && typeof response.message === 'string' ? response.message : 'Acceso actualizado en el centro.')
    } catch (failure) {
      setError(firstApiErrorMessage(failure, 'No fue posible actualizar el acceso.'))
    } finally {
      setBusyId(null)
    }
  }

  return <div className="grid gap-5">
    <p className="text-sm text-muted-foreground">{platform ? 'Asigna administradores a este centro.' : 'Asigna cuentas de recepción a tu centro.'} Si el correo ya tiene una cuenta MedSync, conserva su contraseña. Las cuentas nuevas reciben una invitación para crearla.</p>
    {status === 'loading' && <p role="status">Cargando accesos…</p>}
    {status === 'error' && <div><p role="alert" className="text-sm text-rose-700">{error}</p><Button type="button" variant="outline" onClick={() => { setStatus('loading'); setReload((value) => value + 1) }}>Reintentar</Button></div>}
    {status === 'ready' && <>
      <ActionForm className="sm:grid-cols-2" label={platform ? 'Asignar administrador' : 'Asignar recepción'} onSave={assign}>
        <Field label="Nombre de la persona"><Input name="name" required minLength={3} maxLength={150} /></Field>
        <Field label="Correo de la cuenta"><Input name="email" type="email" required maxLength={150} /></Field>
      </ActionForm>
      {error && <p role="alert" className="text-sm text-rose-700">{error}</p>}
      <ul className="divide-y text-sm">
        {memberships.length === 0 && <li className="py-3 text-muted-foreground">Todavía no hay accesos asignados.</li>}
        {memberships.map((item) => <li className="flex flex-wrap items-center justify-between gap-3 py-3" key={item.id}>
          <div><strong>{item.name}</strong> · {item.email}<span className="block text-xs text-muted-foreground">{item.is_active ? 'Activo' : 'Inactivo'}{item.invitation_pending ? ' · Invitación pendiente' : ''}</span></div>
          <div className="flex gap-2">
            {item.invitation_pending && item.is_active && <Button type="button" size="sm" variant="outline" disabled={busyId === item.id} onClick={() => void changeAccess(item, 'resend')}>Reenviar invitación</Button>}
            <Button type="button" size="sm" variant="outline" disabled={busyId === item.id} onClick={() => void changeAccess(item, 'toggle')}>{item.is_active ? 'Desactivar' : 'Reactivar'}</Button>
          </div>
        </li>)}
      </ul>
    </>}
  </div>
}
