import { LockKeyhole, ShieldCheck, Sparkles } from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { Navigate, useNavigate } from 'react-router-dom'
import { Logo } from '@/components/logo'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Field, Input } from '@/components/ui/form-controls'
import { useClinic } from '@/state/clinic-store'

const demos = [{ label: 'Administrador', email: 'admin@demo.cl' }, { label: 'Recepción', email: 'recepcion@demo.cl' }, { label: 'Profesional', email: 'camila.rojas@demo.cl' }, { label: 'Paciente', email: 'paciente1@demo.cl' }]

export function LoginPage() {
  const { user, login, reset } = useClinic(); const navigate = useNavigate(); const [email, setEmail] = useState('paciente1@demo.cl'); const [password, setPassword] = useState('Demo2026!'); const [error, setError] = useState('')
  if (user) return <Navigate to="/seleccionar-empresa" replace />
  const submit = (event: FormEvent) => { event.preventDefault(); if (login(email, password)) navigate('/seleccionar-empresa'); else setError('El correo o la contraseña no coinciden.') }
  return <main className="min-h-screen bg-[radial-gradient(circle_at_top_left,_#d7f0e8,_transparent_45%),linear-gradient(135deg,#f4faf8,#eef6f5)] p-5 sm:p-8"><div className="mx-auto max-w-6xl"><Logo /><div className="grid min-h-[78vh] items-center gap-16 lg:grid-cols-[1fr_440px]">
    <section className="hidden lg:block"><span className="inline-flex items-center gap-2 rounded-full bg-emerald-100 px-3 py-1.5 text-xs font-bold uppercase tracking-wide text-primary"><Sparkles className="size-3.5" /> Agenda inteligente</span><h1 className="mt-6 max-w-xl text-6xl font-bold leading-[1.04] tracking-tight text-[#17373d]">Atención simple, agenda ordenada.</h1><p className="mt-6 max-w-lg text-lg leading-8 text-muted-foreground">Una experiencia clara para pacientes, profesionales y equipos administrativos.</p><div className="mt-9 flex gap-5 text-sm font-medium text-primary"><span className="flex items-center gap-2"><ShieldCheck className="size-4" /> Acceso por roles</span><span className="flex items-center gap-2"><LockKeyhole className="size-4" /> Preparado para Sanctum</span></div></section>
    <Card className="border-white/80 bg-white/95"><CardHeader><CardTitle className="text-2xl">Bienvenido</CardTitle><CardDescription>Ingresa a tu espacio de Clínica Horizonte.</CardDescription></CardHeader><CardContent><form className="grid gap-5" onSubmit={submit}><Field label="Correo electrónico"><Input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required /></Field><Field label="Contraseña"><Input type="password" value={password} onChange={(e) => setPassword(e.target.value)} required /></Field>{error && <p role="alert" className="rounded-lg bg-rose-50 p-3 text-sm text-rose-700">{error}</p>}<Button size="lg">Ingresar</Button></form><div className="my-6 flex items-center gap-3 text-xs text-muted-foreground"><span className="h-px flex-1 bg-border" /> Cuentas demo <span className="h-px flex-1 bg-border" /></div><div className="grid grid-cols-2 gap-2">{demos.map((demo) => <Button key={demo.email} variant="outline" size="sm" onClick={() => { setEmail(demo.email); setPassword('Demo2026!') }}>{demo.label}</Button>)}</div><p className="mt-4 text-center text-xs text-muted-foreground">Contraseña: Demo2026!</p><Button variant="ghost" size="sm" className="mt-2 w-full" onClick={reset}>Restablecer datos demo</Button></CardContent></Card>
  </div></div></main>
}
