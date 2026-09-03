import { Navigate } from 'react-router-dom'
import { PageHeading } from '@/components/page-heading'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { useClinic } from '@/state/clinic-store'

function Bars({ title, items }: { title: string; items: { label: string; total: number }[] }) { const max = Math.max(1, ...items.map((item) => item.total)); return <Card><CardHeader><CardTitle>{title}</CardTitle></CardHeader><CardContent className="grid gap-5">{items.map((item) => <div key={item.label}><div className="mb-2 flex justify-between text-sm"><span>{item.label}</span><strong>{item.total}</strong></div><div className="h-2 overflow-hidden rounded-full bg-muted"><div className="h-full rounded-full bg-primary" style={{ width: `${item.total * 100 / max}%` }} /></div></div>)}</CardContent></Card> }
export function ReportsPage() {
  const { data, user } = useClinic(); if (user?.role !== 'ADMIN') return <Navigate to="/" replace />
  const attended = data.appointments.filter((a) => a.status === 'ATENDIDA').length, noShow = data.appointments.filter((a) => a.status === 'NO_SHOW').length
  const stats = [{ label: 'Total citas', value: data.appointments.length }, { label: 'Atendidas', value: attended }, { label: 'Canceladas', value: data.appointments.filter((a) => a.status === 'CANCELADA').length }, { label: 'No-show', value: noShow }, { label: 'Asistencia', value: `${Math.round(100 * attended / Math.max(1, attended + noShow))}%` }]
  return <><PageHeading title="Reportes" description="Indicadores operativos de la agenda." /><section className="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-5">{stats.map((s) => <Card key={s.label}><CardContent className="p-5"><p className="text-xs text-muted-foreground">{s.label}</p><strong className="mt-2 block text-3xl">{s.value}</strong></CardContent></Card>)}</section><section className="grid gap-5 lg:grid-cols-2"><Bars title="Citas por profesional" items={data.professionals.map((p) => ({ label: p.name, total: data.appointments.filter((a) => a.professionalId === p.id).length }))} /><Bars title="Citas por especialidad" items={data.specialties.map((s) => ({ label: s.name, total: data.appointments.filter((a) => a.specialtyId === s.id).length }))} /></section></>
}
