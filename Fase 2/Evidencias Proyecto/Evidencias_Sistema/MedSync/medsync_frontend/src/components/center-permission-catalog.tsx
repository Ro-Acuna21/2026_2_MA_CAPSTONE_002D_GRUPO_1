import { useEffect, useState } from 'react'
import { Button } from './ui/button'
import { centerPermissionApi, type CenterPermissionCatalog, type CenterRole } from '@/services/center-permission-api'
import { firstApiErrorMessage } from '@/services/http'

const roleLabels: Record<CenterRole, string> = {
  ADMIN: 'Administrador', RECEPCIONISTA: 'Recepción', PROFESIONAL: 'Profesional', PACIENTE: 'Paciente',
}

const permissionLabels: Record<string, string> = {
  'professionals.manage': 'Registrar y editar profesionales del centro',
  'professionals.access.manage': 'Habilitar el acceso de profesionales',
  'services.manage': 'Administrar prestaciones',
  'availability.manage': 'Administrar horarios disponibles',
  'center.specialties.manage': 'Seleccionar especialidades del centro',
  'results.types.manage': 'Administrar tipos de informes',
  'service_prices.manage': 'Administrar precios de prestaciones',
  'reports.view': 'Consultar reportes del centro',
  'patients.manage': 'Registrar y editar pacientes del centro',
  'appointments.view.all': 'Consultar la agenda del centro',
  'appointments.create.for_patient': 'Reservar horas para pacientes',
  'appointments.reschedule.for_patient': 'Reprogramar citas de pacientes',
  'appointments.status.manage': 'Actualizar el estado de citas del centro',
  'appointments.view.own': 'Consultar las citas propias',
  'appointments.status.manage.own': 'Actualizar el estado de las citas propias',
  'results.manage.own': 'Crear y publicar resultados de atenciones propias',
  'patients.view.own': 'Consultar la ficha propia',
  'patients.update.own': 'Actualizar la ficha propia',
  'appointments.create.own': 'Reservar una cita propia',
  'appointments.reschedule.own': 'Reprogramar una cita propia',
  'appointments.cancel.own': 'Cancelar una cita propia',
  'results.view.own': 'Consultar los resultados propios',
}

export function CenterPermissionCatalogView() {
  const [catalog, setCatalog] = useState<CenterPermissionCatalog | null>(null)
  const [status, setStatus] = useState<'loading' | 'ready' | 'error'>('loading')
  const [error, setError] = useState('')
  const [reload, setReload] = useState(0)

  useEffect(() => {
    let cancelled = false
    centerPermissionApi.list().then(({ data }) => {
      if (cancelled) return
      setCatalog(data)
      setStatus('ready')
    }).catch((failure: unknown) => {
      if (cancelled) return
      setError(firstApiErrorMessage(failure, 'No fue posible cargar los permisos del centro.'))
      setStatus('error')
    })
    return () => { cancelled = true }
  }, [reload])

  return <div className="space-y-4">
    <p className="text-sm text-muted-foreground">Estas acciones corresponden al centro activo y están definidas por rol. Aquí puedes consultarlas; los permisos individuales no se modifican desde esta pantalla.</p>
    {status === 'loading' && <p role="status">Cargando acciones por rol…</p>}
    {status === 'error' && <div><p role="alert" className="mb-3 text-sm text-rose-700">{error}</p><Button type="button" variant="outline" onClick={() => { setStatus('loading'); setReload((value) => value + 1) }}>Reintentar permisos</Button></div>}
    {status === 'ready' && catalog && <div className="grid gap-3 md:grid-cols-2">
      {(Object.keys(roleLabels) as CenterRole[]).map((role) => <div className="rounded-lg border p-4" key={role}>
        <h4 className="font-semibold">{roleLabels[role]}</h4>
        <ul className="mt-2 list-inside list-disc text-xs text-muted-foreground">
          {catalog.roles[role].map((permission) => <li key={permission}>{permissionLabels[permission] ?? 'Otra acción asignada al rol'}</li>)}
        </ul>
      </div>)}
    </div>}
  </div>
}
