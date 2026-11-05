import { useEffect, useState } from 'react'
import { PageHeading } from '@/components/page-heading'
import { commercialInquiryApi, type CommercialInquiry } from '@/services/commercial-inquiry-api'
import { firstApiErrorMessage } from '@/services/http'

export function PlatformInquiriesPage() {
  const [items, setItems] = useState<CommercialInquiry[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  useEffect(() => {
    commercialInquiryApi.list().then(({ data }) => setItems(data))
      .catch((cause) => setError(firstApiErrorMessage(cause, 'No fue posible cargar las solicitudes.')))
      .finally(() => setLoading(false))
  }, [])
  return <>
    <PageHeading title="Solicitudes comerciales" description="Consultas recibidas desde la página pública de planes." />
    {loading && <p role="status">Cargando solicitudes…</p>}
    {error && <p role="alert" className="text-destructive">{error}</p>}
    {!loading && !error && items.length === 0 && <p>No hay solicitudes comerciales registradas.</p>}
    <div className="grid gap-4">{items.map((item) => <article key={item.id} className="rounded-xl border bg-card p-5">
      <div className="flex flex-wrap justify-between gap-2"><h2 className="font-bold">{item.center_name}</h2><time className="text-xs text-muted-foreground">{new Date(item.created_at).toLocaleString('es-CL')}</time></div>
      <p className="mt-2 text-sm">{item.contact_name} · <a className="text-primary underline" href={`mailto:${item.email}`}>{item.email}</a>{item.phone ? ` · ${item.phone}` : ''}</p>
      <p className="mt-2 text-sm text-muted-foreground">Plan de interés: {item.plan?.name ?? 'Por definir'}</p>
      {item.message && <p className="mt-3 whitespace-pre-wrap text-sm">{item.message}</p>}
    </article>)}</div>
  </>
}
