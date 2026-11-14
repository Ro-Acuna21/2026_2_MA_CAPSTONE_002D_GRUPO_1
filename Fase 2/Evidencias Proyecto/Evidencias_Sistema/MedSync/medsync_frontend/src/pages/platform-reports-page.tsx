import { useEffect, useState } from 'react'
import { PageHeading } from '@/components/page-heading'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { firstApiErrorMessage } from '@/services/http'
import { platformReportApi, type PlatformReport } from '@/services/platform-report-api'

export function PlatformReportsPage() {
  const [report, setReport] = useState<PlatformReport | null>(null)
  const [error, setError] = useState('')
  const [reload, setReload] = useState(0)

  useEffect(() => {
    let active = true
    platformReportApi.summary().then(({ data }) => {
      if (active) { setReport(data); setError('') }
    }).catch((failure: unknown) => {
      if (active) setError(firstApiErrorMessage(failure, 'No fue posible cargar los reportes.'))
    })
    return () => { active = false }
  }, [reload])

  const stats = report ? [
    ['Centros registrados', report.total_centers],
    ['Centros habilitados', report.active_centers],
    ['Cuentas de plataforma', report.total_platform_users],
    ['Pacientes únicos con cuenta', report.unique_patients],
    ['Inscripciones de pacientes', report.patient_center_registrations],
    ['Pacientes en varios centros', report.patients_in_multiple_centers],
  ] as const : []

  return <>
    <PageHeading eyebrow="Plataforma MedSync" title="Reportes generales" description="Indicadores de cuentas e inscripciones de pacientes en los centros." />
    {!report && !error && <p role="status">Cargando reportes…</p>}
    {error && <div className="mb-6"><p role="alert" className="text-rose-700">{error}</p><Button variant="outline" onClick={() => setReload((value) => value + 1)}>Reintentar</Button></div>}
    {report && <>
      <section className="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">{stats.map(([label, value]) => <Card key={label}><CardContent className="p-5"><p className="text-xs text-muted-foreground">{label}</p><strong className="mt-2 block text-2xl">{value}</strong></CardContent></Card>)}</section>
      <Card><CardHeader><CardTitle>Pacientes por centro</CardTitle><p className="text-sm text-muted-foreground">Se cuentan cuentas activas con una inscripción de paciente activa y una ficha asociada. Una persona inscrita en dos centros aparece una vez en cada centro.</p></CardHeader><CardContent className="overflow-x-auto p-0"><table className="w-full text-left text-sm"><thead className="bg-muted/60 text-xs uppercase text-muted-foreground"><tr><th className="px-5 py-3">Centro</th><th className="px-5 py-3">Estado</th><th className="px-5 py-3 text-right">Pacientes inscritos</th></tr></thead><tbody>{report.centers.map((center) => <tr className="border-t" key={center.id}><td className="px-5 py-4"><strong>{center.name}</strong><span className="block text-xs text-muted-foreground">/{center.slug}</span></td><td className="px-5 py-4">{center.is_active ? 'Habilitado' : 'Deshabilitado'}</td><td className="px-5 py-4 text-right font-semibold">{center.patient_count}</td></tr>)}</tbody></table>{report.centers.length === 0 && <p className="p-5 text-sm text-muted-foreground">Todavía no hay centros registrados.</p>}</CardContent></Card>
      <p className="mt-4 text-sm text-muted-foreground">Las fichas locales sin cuenta de MedSync no se incluyen en estos indicadores de inscripción.</p>
    </>}
  </>
}
