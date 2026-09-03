import { useState, type FormEvent } from 'react'
import { Navigate } from 'react-router-dom'
import { PageHeading } from '@/components/page-heading'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Field, Input } from '@/components/ui/form-controls'
import { useClinic } from '@/state/clinic-store'

export function ProfilePage() {
  const { data, user, updateProfile } = useClinic(); const patient = data.patients.find((p) => p.id === user?.patientId); const [values, setValues] = useState(() => ({ name: patient?.name ?? '', email: patient?.email ?? '', phone: patient?.phone ?? '', address: patient?.address ?? '' }))
  if (!patient || user?.role !== 'PACIENTE') return <Navigate to="/" replace />
  const submit = (e: FormEvent) => { e.preventDefault(); updateProfile(values) }
  return <><PageHeading title="Mis datos" description="Información administrativa utilizada para gestionar tus reservas." /><Card className="max-w-2xl"><CardHeader><CardTitle>Perfil del paciente</CardTitle></CardHeader><CardContent><form className="grid gap-5 sm:grid-cols-2" onSubmit={submit}><Field label="Nombre completo"><Input value={values.name} onChange={(e) => setValues({ ...values, name: e.target.value })} required /></Field><Field label="RUT"><Input value={patient.rut} disabled /></Field><Field label="Correo"><Input type="email" value={values.email} onChange={(e) => setValues({ ...values, email: e.target.value })} required /></Field><Field label="Teléfono"><Input value={values.phone} onChange={(e) => setValues({ ...values, phone: e.target.value })} required /></Field><Field className="sm:col-span-2" label="Dirección"><Input value={values.address} onChange={(e) => setValues({ ...values, address: e.target.value })} /></Field><Button className="sm:col-span-2">Guardar cambios</Button></form></CardContent></Card></>
}
