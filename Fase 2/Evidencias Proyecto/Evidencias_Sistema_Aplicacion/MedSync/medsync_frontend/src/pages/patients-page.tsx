import * as Dialog from '@radix-ui/react-dialog'
import { Search, UserPlus, X } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Navigate } from 'react-router-dom'
import { toast } from 'sonner'
import { PageHeading } from '@/components/page-heading'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Field, Input, Select } from '@/components/ui/form-controls'
import { ActionForm } from '@/components/action-form'
import { ConsentField, PatientFields } from '@/components/patient-fields'
import { healthInsurances } from '@/domain/validation'
import { dateFromToday } from '@/lib/utils'
import { firstApiErrorMessage, patientApi, type BackendPatient } from '@/services/http'
import { useClinic } from '@/state/clinic-store'

export function PatientsPage() {
  const { user } = useClinic()
  const [patients, setPatients] = useState<BackendPatient[]>([])
  const [search, setSearch] = useState('')
  const [creating, setCreating] = useState(false)
  const [selected, setSelected] = useState<BackendPatient | null>(null)
  const [editing, setEditing] = useState(false)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const activeCenterIds = user?.organizationIds.join(',')

  useEffect(() => {
    if (user?.role !== 'RECEPCIONISTA') return
    let active = true
    patientApi.list().then((response) => {
      if (active) setPatients(response.data)
    }).catch((failure) => {
      if (active) setError(firstApiErrorMessage(failure, 'No fue posible cargar pacientes.'))
    }).finally(() => { if (active) setLoading(false) })
    return () => { active = false }
  }, [user?.role, activeCenterIds])

  if (user?.role !== 'RECEPCIONISTA') return <Navigate to="/" replace />

  const openPatient = async (id: number) => {
    try {
      const response = await patientApi.show(id)
      setSelected(response.data)
      setEditing(false)
    } catch (failure) {
      toast.error(firstApiErrorMessage(failure, 'No fue posible consultar la ficha.'))
    }
  }

  const visible = patients.filter((patient) =>
    [patient.first_name, patient.last_name, patient.rut, patient.email].some((value) =>
      value.toLowerCase().includes(search.toLowerCase())))

  return <>
    <PageHeading title="Pacientes" description="Información administrativa y de contacto." action={
      <Dialog.Root open={creating} onOpenChange={setCreating}>
        <Dialog.Trigger asChild><Button><UserPlus className="size-4" /> Registrar paciente</Button></Dialog.Trigger>
        <Dialog.Portal><Dialog.Overlay className="fixed inset-0 z-50 bg-black/40" />
          <Dialog.Content className="fixed left-1/2 top-1/2 z-50 max-h-[90vh] w-[calc(100%-2rem)] max-w-xl -translate-x-1/2 -translate-y-1/2 overflow-y-auto rounded-xl bg-card p-6 shadow-2xl">
            <div className="flex items-start justify-between"><div><Dialog.Title className="text-xl font-semibold">Nuevo paciente</Dialog.Title><Dialog.Description className="mt-1 text-sm text-muted-foreground">Registra los datos necesarios para gestionar sus reservas.</Dialog.Description></div><Dialog.Close asChild><Button variant="ghost" size="icon"><X className="size-4" /></Button></Dialog.Close></div>
            <ActionForm className="mt-6 sm:grid-cols-2" label="Guardar paciente" onSave={async (values) => {
              try {
                const response = await patientApi.create({
                  first_name: values.firstName, last_name: values.lastName, rut: values.rut,
                  birth_date: values.birthDate, email: values.email, phone: values.phone,
                  health_insurance: values.healthInsurance, medical_insurance: values.medicalInsurance,
                  address: values.address, consent: values.consent === 'on',
                })
                setPatients((current) => [...current, response.data].sort((a, b) => a.first_name.localeCompare(b.first_name)))
                setCreating(false)
                toast.success('Ficha registrada. El acceso se gestiona por separado.')
              } catch (failure) {
                throw new Error(firstApiErrorMessage(failure, 'No fue posible registrar al paciente.'))
              }
            }}><PatientFields /><ConsentField /></ActionForm>
          </Dialog.Content></Dialog.Portal>
      </Dialog.Root>
    } />
    <Card><CardContent className="p-0"><div className="relative border-b p-4"><Search className="absolute left-7 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" /><Input className="max-w-md pl-10" placeholder="Buscar por RUT, nombre o correo" value={search} onChange={(e) => setSearch(e.target.value)} /></div>
      {loading && <p className="p-5 text-sm text-muted-foreground">Cargando pacientes…</p>}
      {error && <p role="alert" className="p-5 text-sm text-rose-700">{error}</p>}
      {!loading && !error && <div className="overflow-x-auto"><table className="w-full min-w-[760px] text-left text-sm"><thead className="bg-muted/60 text-[11px] uppercase tracking-wider text-muted-foreground"><tr><th className="px-5 py-3">Paciente</th><th className="px-4 py-3">RUT</th><th className="px-4 py-3">Contacto</th><th className="px-4 py-3">Consentimiento</th><th className="px-4 py-3">Estado</th><th className="px-4 py-3">Ficha</th></tr></thead><tbody>{visible.map((patient) => <tr className="border-t" key={patient.id}><td className="px-5 py-4 font-medium">{patient.first_name} {patient.last_name}<small className="block text-muted-foreground">Nacimiento: {patient.birth_date ?? 'Sin registrar'}</small></td><td className="px-4 py-4">{patient.rut}</td><td className="px-4 py-4">{patient.email}<small className="block text-muted-foreground">{patient.phone}</small></td><td className="px-4 py-4">{patient.consent ? 'Aceptado' : 'Pendiente'}</td><td className="px-4 py-4">{patient.is_active ? 'Activo' : 'Inactivo'}</td><td className="px-4 py-4"><Button variant="ghost" onClick={() => void openPatient(patient.id)}>Ver detalle</Button></td></tr>)}</tbody></table>{visible.length === 0 && <p className="p-5 text-sm text-muted-foreground">No hay pacientes para mostrar.</p>}</div>}
    </CardContent></Card>
    <Dialog.Root open={selected !== null} onOpenChange={(open) => { if (!open) { setSelected(null); setEditing(false) } }}>
      <Dialog.Portal><Dialog.Overlay className="fixed inset-0 z-50 bg-black/40" /><Dialog.Content className="fixed left-1/2 top-1/2 z-50 max-h-[90vh] w-[calc(100%-2rem)] max-w-xl -translate-x-1/2 -translate-y-1/2 overflow-y-auto rounded-xl bg-card p-6 shadow-2xl">
        <div className="flex items-start justify-between"><div><Dialog.Title className="text-xl font-semibold">Ficha de paciente</Dialog.Title><Dialog.Description className="mt-1 text-sm text-muted-foreground">Datos guardados en el centro médico.</Dialog.Description></div><Dialog.Close asChild><Button variant="ghost" size="icon"><X className="size-4" /></Button></Dialog.Close></div>
        {selected && (editing ? <ActionForm key={selected.id} className="mt-6 sm:grid-cols-2" label="Guardar cambios" onSave={async (values) => {
          try {
            const response = await patientApi.update(selected.id, {
              first_name: values.firstName, last_name: values.lastName, birth_date: values.birthDate,
              email: values.email, phone: values.phone, health_insurance: values.healthInsurance,
              medical_insurance: values.medicalInsurance, address: values.address,
            })
            setSelected(response.data)
            setPatients((current) => current.map((item) => item.id === response.data.id ? response.data : item))
            setEditing(false)
            toast.success('Ficha actualizada')
          } catch (failure) {
            throw new Error(firstApiErrorMessage(failure, 'No fue posible actualizar la ficha.'))
          }
        }}>
          <Field label="Nombres"><Input name="firstName" defaultValue={selected.first_name} required minLength={2} /></Field>
          <Field label="Apellidos"><Input name="lastName" defaultValue={selected.last_name} required minLength={2} /></Field>
          <Field label="RUT"><Input value={selected.rut} disabled /></Field>
          <Field label="Fecha de nacimiento"><Input name="birthDate" type="date" defaultValue={selected.birth_date ?? ''} max={dateFromToday()} required /></Field>
          <Field label="Correo electrónico"><Input name="email" type="email" defaultValue={selected.email} disabled={selected.has_account} required /></Field>
          <Field label="Teléfono"><Input name="phone" type="tel" defaultValue={selected.phone} required /></Field>
          <Field label="Previsión de salud"><Select name="healthInsurance" defaultValue={selected.health_insurance ?? ''} required>{healthInsurances.map((name) => <option key={name}>{name}</option>)}</Select></Field>
          <Field label="Seguro complementario"><Input name="medicalInsurance" defaultValue={selected.medical_insurance ?? ''} /></Field>
          <Field className="sm:col-span-2" label="Dirección"><Input name="address" defaultValue={selected.address ?? ''} /></Field>
        </ActionForm> : <div className="mt-6 space-y-3 text-sm"><p><strong>Nombre:</strong> {selected.first_name} {selected.last_name}</p><p><strong>RUT:</strong> {selected.rut}</p><p><strong>Nacimiento:</strong> {selected.birth_date ?? 'Sin registrar'}</p><p><strong>Correo:</strong> {selected.email}</p><p><strong>Teléfono:</strong> {selected.phone}</p><p><strong>Previsión:</strong> {selected.health_insurance ?? 'Sin registrar'}</p><p><strong>Seguro complementario:</strong> {selected.medical_insurance ?? 'Sin registrar'}</p><p><strong>Dirección:</strong> {selected.address ?? 'Sin registrar'}</p><p><strong>Consentimiento:</strong> {selected.consent ? 'Aceptado' : 'Pendiente'}</p><Button onClick={() => setEditing(true)}>Editar ficha</Button></div>)}
      </Dialog.Content></Dialog.Portal>
    </Dialog.Root>
  </>
}
