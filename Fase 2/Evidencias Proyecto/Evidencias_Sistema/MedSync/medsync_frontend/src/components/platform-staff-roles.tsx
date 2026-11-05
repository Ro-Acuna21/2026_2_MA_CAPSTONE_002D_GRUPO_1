import { useEffect, useState } from 'react'
import { toast } from 'sonner'
import { Button } from './ui/button'
import { Select } from './ui/form-controls'
import { centerAccessApi, type CenterAccess } from '@/services/center-access-api'
import { firstApiErrorMessage } from '@/services/http'

export function PlatformStaffRoles({ centerId }: { centerId: string }) {
  const [staff, setStaff] = useState<CenterAccess[]>([])
  const [drafts, setDrafts] = useState<Record<number, CenterAccess['role']>>({})
  const [status, setStatus] = useState<'loading' | 'ready' | 'error'>('loading')
  const [error, setError] = useState('')
  const [reload, setReload] = useState(0)
  const [busyId, setBusyId] = useState<number | null>(null)

  useEffect(() => {
    let cancelled = false
    centerAccessApi.staff(centerId).then(({ data }) => {
      if (cancelled) return
      setStaff(data)
      setDrafts(Object.fromEntries(data.map((item) => [item.id, item.role])))
      setStatus('ready')
      setError('')
    }).catch((failure: unknown) => {
      if (cancelled) return
      setError(firstApiErrorMessage(failure, 'No fue posible cargar el equipo del centro.'))
      setStatus('error')
    })
    return () => { cancelled = true }
  }, [centerId, reload])

  useEffect(() => {
    const refresh = () => setReload((value) => value + 1)
    window.addEventListener('medsync:staff-updated', refresh)
    return () => window.removeEventListener('medsync:staff-updated', refresh)
  }, [])

  async function saveRole(item: CenterAccess) {
    const role = drafts[item.id]
    if (!role || role === item.role) return
    setBusyId(item.id)
    setError('')
    try {
      const { data } = await centerAccessApi.changeStaffRole(centerId, item.id, role)
      setStaff((current) => current.map((entry) => entry.id === data.id ? data : entry))
      window.dispatchEvent(new Event('medsync:staff-updated'))
      toast.success('Rol actualizado en este centro.')
    } catch (failure) {
      setError(firstApiErrorMessage(failure, 'No fue posible cambiar el rol.'))
    } finally {
      setBusyId(null)
    }
  }

  return <div className="mt-8 border-t pt-6">
    <h3 className="font-semibold">Roles del equipo</h3>
    <p className="mt-1 text-sm text-muted-foreground">La plataforma puede cambiar ADMIN o recepción en la misma membresía. Los accesos de paciente y profesional conservan su flujo propio.</p>
    {status === 'loading' && <p role="status" className="mt-3">Cargando equipo…</p>}
    {status === 'error' && <div className="mt-3"><p role="alert" className="text-sm text-rose-700">{error}</p><Button variant="outline" onClick={() => { setStatus('loading'); setReload((value) => value + 1) }}>Reintentar equipo</Button></div>}
    {status === 'ready' && <ul className="mt-3 divide-y text-sm">
      {staff.length === 0 && <li className="py-3 text-muted-foreground">Todavía no hay personal con acceso.</li>}
      {staff.map((item) => <li className="flex flex-wrap items-center justify-between gap-3 py-3" key={item.id}>
        <span><strong>{item.name}</strong> · {item.email}<span className="block text-xs text-muted-foreground">{item.is_active ? 'Activo' : 'Inactivo'}</span></span>
        <div className="flex gap-2"><Select aria-label={`Rol de ${item.name}`} value={drafts[item.id] ?? item.role} onChange={(event) => setDrafts((current) => ({ ...current, [item.id]: event.target.value as CenterAccess['role'] }))}>
          <option value="ADMIN">Administrador</option><option value="RECEPCIONISTA">Recepción</option>
        </Select><Button type="button" variant="outline" disabled={busyId === item.id || drafts[item.id] === item.role} onClick={() => void saveRole(item)}>Guardar rol</Button></div>
      </li>)}
    </ul>}
    {status === 'ready' && error && <p role="alert" className="mt-3 text-sm text-rose-700">{error}</p>}
  </div>
}
