import { useEffect, useState } from 'react'
import { Button } from './ui/button'
import { centerPermissionApi, type CenterPermissionCatalog, type CenterRole } from '@/services/center-permission-api'
import { firstApiErrorMessage } from '@/services/http'

const roleLabels: Record<CenterRole, string> = {
  ADMIN: 'Administrador', RECEPCIONISTA: 'Recepción', PROFESIONAL: 'Profesional', PACIENTE: 'Paciente',
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
    <p className="text-sm text-muted-foreground">Los permisos de estos roles son fijos y los aplica Laravel con Spatie según el centro activo. Esta pantalla permite revisar el catálogo; no modifica permisos individuales.</p>
    {status === 'loading' && <p role="status">Cargando permisos del backend…</p>}
    {status === 'error' && <div><p role="alert" className="mb-3 text-sm text-rose-700">{error}</p><Button type="button" variant="outline" onClick={() => { setStatus('loading'); setReload((value) => value + 1) }}>Reintentar permisos</Button></div>}
    {status === 'ready' && catalog && <div className="grid gap-3 md:grid-cols-2">
      {(Object.keys(roleLabels) as CenterRole[]).map((role) => <div className="rounded-lg border p-4" key={role}>
        <h4 className="font-semibold">{roleLabels[role]}</h4>
        <ul className="mt-2 list-inside list-disc text-xs text-muted-foreground">
          {catalog.roles[role].map((permission) => <li key={permission}>{permission}</li>)}
        </ul>
      </div>)}
    </div>}
  </div>
}
