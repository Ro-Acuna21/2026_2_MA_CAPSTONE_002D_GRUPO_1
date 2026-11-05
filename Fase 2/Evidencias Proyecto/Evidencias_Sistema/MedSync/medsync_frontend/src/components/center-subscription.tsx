import { useEffect, useState } from 'react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { firstApiErrorMessage } from '@/services/http'
import { subscriptionApi, type CenterSubscription } from '@/services/subscription-api'
const clp = new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 })
const labels = { TRIAL: 'Prueba', ACTIVE: 'Activa', EXPIRED: 'Vencida', SUSPENDED: 'Suspendida' }
export function CenterSubscriptionView() {
  const [data, setData] = useState<CenterSubscription | null>(null)
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(true)
  const [reload, setReload] = useState(0)
  useEffect(() => {
    let live = true
    subscriptionApi.centerShow().then((response) => { if (live) { setData(response.data); setError('') } })
      .catch((cause) => { if (live) setError(firstApiErrorMessage(cause, 'No fue posible consultar la suscripción.')) })
      .finally(() => { if (live) setLoading(false) })
    return () => { live = false }
  }, [reload])
  return <Card><CardHeader><CardTitle>Suscripción del centro</CardTitle></CardHeader><CardContent>
    {loading && <p>Cargando estado de la suscripción…</p>}
    {error && <p role="alert" className="text-destructive">{error} <Button variant="outline" onClick={() => { setLoading(true); setReload((n) => n + 1) }}>Reintentar</Button></p>}
    {!loading && !error && !data?.current && <p>Sin suscripción registrada. La plataforma debe asignar un plan.</p>}
    {data?.current && <><p className="font-semibold">{data.current.plan?.name} · {labels[data.current.status]}</p><p className="mt-2 text-sm">Inicio: {data.current.started_at} · Renovación indicada: {data.current.renews_at ?? 'Sin fecha'}</p><p className="text-sm">Precio al contratar: {data.current.monthly_price_clp_snapshot == null ? 'No registrado' : clp.format(data.current.monthly_price_clp_snapshot)}</p><p className="mt-2 text-sm text-muted-foreground">Pagos registrados manualmente: {data.current.payments.length}. Estos registros no verifican cobros bancarios ni indican que la cuenta esté al día.</p></>}
  </CardContent></Card>
}
