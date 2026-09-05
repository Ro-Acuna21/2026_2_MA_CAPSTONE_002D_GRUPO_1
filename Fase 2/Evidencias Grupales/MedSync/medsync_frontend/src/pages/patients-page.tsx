import * as Dialog from '@radix-ui/react-dialog'
import { Search, UserPlus, X } from 'lucide-react'
import { useState } from 'react'
import { Navigate } from 'react-router-dom'
import { PageHeading } from '@/components/page-heading'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/form-controls'
import { ActionForm } from '@/components/action-form'
import { PatientFields, patientFromForm } from '@/components/patient-fields'
import { useClinic } from '@/state/clinic-store'

export function PatientsPage() {
  const { data, user, createPatient } = useClinic(); const [search, setSearch] = useState(''); const [open, setOpen] = useState(false)
  if (user?.role !== 'RECEPCIONISTA') return <Navigate to="/" replace />
  const patients = data.patients.filter((p) => [p.name, p.rut, p.email].some((value) => value.toLowerCase().includes(search.toLowerCase())))
  return <><PageHeading title="Pacientes" description="Información administrativa y de contacto." action={<Dialog.Root open={open} onOpenChange={setOpen}><Dialog.Trigger asChild><Button><UserPlus className="size-4" /> Registrar paciente</Button></Dialog.Trigger><Dialog.Portal><Dialog.Overlay className="fixed inset-0 z-50 bg-black/40" /><Dialog.Content className="fixed left-1/2 top-1/2 z-50 max-h-[90vh] w-[calc(100%-2rem)] max-w-xl -translate-x-1/2 -translate-y-1/2 overflow-y-auto rounded-xl bg-card p-6 shadow-2xl"><div className="flex items-start justify-between"><div><Dialog.Title className="text-xl font-semibold">Nuevo paciente</Dialog.Title><Dialog.Description className="mt-1 text-sm text-muted-foreground">Registra los datos necesarios para gestionar sus reservas.</Dialog.Description></div><Dialog.Close asChild><Button variant="ghost" size="icon"><X className="size-4" /></Button></Dialog.Close></div><ActionForm className="mt-6 sm:grid-cols-2" label="Guardar paciente" onSave={(values) => { createPatient(patientFromForm(values)); setOpen(false) }}><PatientFields /></ActionForm></Dialog.Content></Dialog.Portal></Dialog.Root>} />
    <Card><CardContent className="p-0"><div className="relative border-b p-4"><Search className="absolute left-7 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" /><Input className="max-w-md pl-10" placeholder="Buscar por RUT, nombre o correo" value={search} onChange={(e) => setSearch(e.target.value)} /></div><div className="overflow-x-auto"><table className="w-full min-w-[760px] text-left text-sm"><thead className="bg-muted/60 text-[11px] uppercase tracking-wider text-muted-foreground"><tr><th className="px-5 py-3">Paciente</th><th className="px-4 py-3">RUT</th><th className="px-4 py-3">Contacto</th><th className="px-4 py-3">Consentimiento</th><th className="px-4 py-3">Estado</th></tr></thead><tbody>{patients.map((patient) => <tr className="border-t" key={patient.id}><td className="px-5 py-4 font-medium">{patient.name}<small className="block text-muted-foreground">Nacimiento: {patient.birthDate}</small></td><td className="px-4 py-4">{patient.rut}</td><td className="px-4 py-4">{patient.email}<small className="block text-muted-foreground">{patient.phone}</small></td><td className="px-4 py-4"><span className="rounded-full bg-emerald-100 px-2 py-1 text-xs text-emerald-800">Aceptado</span></td><td className="px-4 py-4"><span className="rounded-full bg-blue-100 px-2 py-1 text-xs text-blue-800">Activo</span></td></tr>)}</tbody></table></div></CardContent></Card></>
}
