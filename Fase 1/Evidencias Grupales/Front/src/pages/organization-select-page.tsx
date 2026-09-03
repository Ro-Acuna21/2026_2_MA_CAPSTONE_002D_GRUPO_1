import { Building2, CheckCircle2, LogOut } from 'lucide-react'
import { Navigate, useNavigate } from 'react-router-dom'
import { Logo } from '@/components/logo'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { useClinic } from '@/state/clinic-store'

export function OrganizationSelectPage() {
  const { user, organization, availableOrganizations, selectOrganization, logout } = useClinic()
  const navigate = useNavigate()
  if (!user) return <Navigate to="/ingresar" replace />
  if (organization && availableOrganizations.length === 1) return <Navigate to="/" replace />
  const choose = (id: string) => { if (selectOrganization(id)) navigate('/') }
  return <main className="min-h-screen bg-[radial-gradient(circle_at_top_left,_#d7f0e8,_transparent_45%),linear-gradient(135deg,#f4faf8,#eef6f5)] p-5 sm:p-8"><div className="mx-auto max-w-4xl"><div className="flex items-center justify-between"><Logo /><Button variant="ghost" onClick={logout}><LogOut className="size-4" /> Salir</Button></div><section className="mx-auto mt-20 max-w-2xl text-center"><span className="mx-auto grid size-14 place-items-center rounded-2xl bg-emerald-100 text-primary"><Building2 className="size-7" /></span><h1 className="mt-5 text-3xl font-bold tracking-tight">Selecciona una empresa</h1><p className="mt-2 text-muted-foreground">Elige el espacio de trabajo al que quieres ingresar. Los datos permanecen separados entre organizaciones.</p><div className="mt-8 grid gap-4 sm:grid-cols-2">{availableOrganizations.map((item) => <Card key={item.id} className={`text-left transition hover:-translate-y-0.5 hover:border-primary/40 ${organization?.id === item.id ? 'border-primary ring-2 ring-primary/15' : ''}`}><CardContent className="p-5"><div className="flex items-start justify-between"><span className="grid size-11 place-items-center rounded-xl bg-secondary font-bold text-primary">{item.name[0]}</span><span className="rounded-full bg-muted px-2.5 py-1 text-[10px] font-bold text-muted-foreground">{organization?.id === item.id ? 'ACTIVA' : `PLAN ${item.plan}`}</span></div><h2 className="mt-5 font-semibold">{item.name}</h2><p className="mt-1 text-xs text-muted-foreground">{item.slug}.tusistema.cl</p><Button className="mt-5 w-full" variant={organization?.id === item.id ? 'secondary' : 'default'} onClick={() => choose(item.id)}>{organization?.id === item.id ? 'Continuar' : 'Ingresar'} <CheckCircle2 className="size-4" /></Button></CardContent></Card>)}</div></section></div></main>
}
