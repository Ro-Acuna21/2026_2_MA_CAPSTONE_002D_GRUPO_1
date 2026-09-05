import { BarChart3, Building2, CalendarDays, ChevronRight, ClipboardPlus, LayoutDashboard, LogOut, Menu, Settings2, UserRound, Users, X } from 'lucide-react'
import { useEffect, useState } from 'react'
import { NavLink, Outlet } from 'react-router-dom'
import { roleNames } from '@/domain/types'
import { cn } from '@/lib/utils'
import { useClinic } from '@/state/clinic-store'
import { Logo } from './logo'
import { Button } from './ui/button'
import { useCenterPath } from '@/lib/tenant'

export function AppLayout() {
  const { user, organization, logout } = useClinic(); const [open, setOpen] = useState(false); const centerPath = useCenterPath()
  const roleLinks = user?.role === 'SUPER_ADMIN' ? [{ to: '/plataforma', label: 'Centros y suscripciones', icon: Building2 }]
    : user?.role === 'ADMIN' ? [{ to: '/administracion', label: 'Administración del centro', icon: Settings2 }, { to: '/resultados', label: 'Tipos de informes', icon: ClipboardPlus }, { to: '/reportes', label: 'Reportes generales', icon: BarChart3 }]
    : user?.role === 'RECEPCIONISTA' ? [{ to: '/', label: 'Inicio', icon: LayoutDashboard }, { to: '/citas', label: 'Agenda', icon: CalendarDays }, { to: '/reservar', label: 'Reservar hora', icon: ClipboardPlus }, { to: '/pacientes', label: 'Pacientes', icon: Users }, ...(user.permissions.includes('results.upload') ? [{ to: '/resultados', label: 'Cargar informes', icon: ClipboardPlus }] : [])]
    : user?.role === 'PROFESIONAL' ? [{ to: '/', label: 'Inicio', icon: LayoutDashboard }, { to: '/citas', label: 'Mi agenda', icon: CalendarDays }, { to: '/resultados', label: 'Informes y resultados', icon: ClipboardPlus }]
    : [{ to: '/', label: 'Inicio', icon: LayoutDashboard }, { to: '/citas', label: 'Mis citas', icon: CalendarDays }, { to: '/reservar', label: 'Reservar hora', icon: ClipboardPlus }, { to: '/resultados', label: 'Mis informes y resultados', icon: ClipboardPlus }, { to: '/perfil', label: 'Mis datos', icon: UserRound }]
  const links = user?.role === 'SUPER_ADMIN' ? roleLinks : roleLinks.map((link) => ({ ...link, to: centerPath(link.to) }))
  useEffect(() => { document.title = user?.role === 'SUPER_ADMIN' ? 'MedSync · Plataforma' : organization?.name ?? 'Portal médico' }, [organization?.name, user?.role])
  return <div className="min-h-screen bg-background lg:pl-64">
    <Button variant="outline" size="icon" className="fixed left-4 top-4 z-40 bg-card lg:hidden" onClick={() => setOpen(!open)}>{open ? <X /> : <Menu />}</Button>
    {open && <button aria-label="Cerrar menú" className="fixed inset-0 z-20 bg-black/30 lg:hidden" onClick={() => setOpen(false)} />}
    <aside className={cn('fixed inset-y-0 left-0 z-30 flex w-64 flex-col bg-[#123f42] px-4 py-7 text-white transition-transform lg:translate-x-0', open ? 'translate-x-0' : '-translate-x-full')}>
      <div className="px-3"><Logo light name={user?.role === 'SUPER_ADMIN' ? 'MedSync' : organization?.name} /></div>
      <div className="mt-7 rounded-xl border border-white/10 bg-white/5 p-3"><div className="flex items-center gap-2"><Building2 className="size-4 text-emerald-200" /><div className="min-w-0"><p className="text-[10px] uppercase tracking-wider text-emerald-50/50">{user?.role === 'SUPER_ADMIN' ? 'Plataforma' : 'Centro médico'}</p><p className="truncate text-sm font-semibold">{user?.role === 'SUPER_ADMIN' ? 'Administración MedSync' : organization?.name}</p></div></div></div>
      <nav className="mt-5 grid gap-1">{links.map(({ to, label, icon: Icon }) => <NavLink key={to} to={to} end={to === '/plataforma' || to === centerPath()} onClick={() => setOpen(false)} className={({ isActive }) => cn('flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-emerald-50/75 transition-colors hover:bg-white/10 hover:text-white', isActive && 'bg-white/[0.14] text-white shadow-inner ring-1 ring-white/20')}><Icon className="size-[18px]" /><span>{label}</span><ChevronRight className="ml-auto size-4 opacity-40" /></NavLink>)}</nav>
      <div className="mt-auto border-t border-white/15 pt-5"><div className="flex items-center gap-3 rounded-xl bg-white/5 p-3"><span className="grid size-9 shrink-0 place-items-center rounded-full bg-emerald-100 font-bold text-primary">{user?.name[0]}</span><div className="min-w-0 flex-1"><p className="truncate text-sm font-semibold">{user?.name}</p><p className="truncate text-[11px] text-emerald-50/60">{user && roleNames[user.role]}</p></div><Button aria-label="Cerrar sesión" variant="ghost" size="icon" className="size-8 text-white hover:bg-white/10" onClick={logout}><LogOut className="size-4" /></Button></div></div>
    </aside>
    <main className="mx-auto max-w-[1500px] px-4 pb-12 pt-20 sm:px-7 lg:px-10 lg:pt-9"><Outlet key={organization?.id ?? user?.id} /></main>
  </div>
}
