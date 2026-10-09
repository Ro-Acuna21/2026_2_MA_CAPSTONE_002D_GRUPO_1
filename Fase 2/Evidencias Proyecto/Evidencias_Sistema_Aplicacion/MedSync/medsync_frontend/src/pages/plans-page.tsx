import { Check, ChevronLeft } from 'lucide-react'
import { Link, Navigate } from 'react-router-dom'
import { commercialPlans } from '@/data/commercial-mock'
import { PageHeading } from '@/components/page-heading'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { useClinic } from '@/state/clinic-store'

const clp = new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 })

export function PlansPage() {
  const { user } = useClinic()
  if (user?.role !== 'SUPER_ADMIN') return <Navigate to="/" replace />
  return <>
    <PageHeading eyebrow="Comercial MedSync" title="Planes de suscripción" description="Catálogo de capacidades y servicios disponibles para centros MedSync." action={<Button asChild variant="outline"><Link to="/plataforma"><ChevronLeft className="size-4" /> Volver a suscripciones</Link></Button>} />
    <section className="grid gap-5 lg:grid-cols-3">{commercialPlans.map((plan) => <Card key={plan.id} className={plan.id === 'PRO' ? 'border-primary ring-1 ring-primary/20' : ''}><CardHeader><div className="flex items-center justify-between gap-3"><CardTitle>{plan.name}</CardTitle>{plan.id === 'PRO' && <span className="rounded-full bg-primary px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-primary-foreground">Más elegido</span>}</div><p className="pt-3 text-3xl font-bold">{clp.format(plan.monthlyPrice)}<span className="ml-1 text-sm font-normal text-muted-foreground">/ mes</span></p></CardHeader><CardContent><dl className="grid gap-3 text-sm"><div><dt className="text-muted-foreground">Profesionales</dt><dd className="font-medium">{plan.professionalLimit}</dd></div><div><dt className="text-muted-foreground">Usuarios</dt><dd className="font-medium">{plan.userLimit}</dd></div><div><dt className="text-muted-foreground">Reportes</dt><dd className="font-medium">{plan.reports}</dd></div></dl><ul className="mt-6 grid gap-3 border-t pt-5 text-sm">{plan.features.map((feature) => <li className="flex gap-2" key={feature}><Check className="mt-0.5 size-4 shrink-0 text-primary" />{feature}</li>)}</ul></CardContent></Card>)}</section>
  </>
}
