import { useEffect, useState, type FormEvent } from 'react'
import { Navigate } from 'react-router-dom'
import { PageHeading } from '@/components/page-heading'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Field, Input } from '@/components/ui/form-controls'
import { useClinic } from '@/state/clinic-store'
import { firstApiErrorMessage, patientApi, type BackendPatient } from '@/services/http'
import { toast } from 'sonner'

export function ProfilePage() {
  const { user } = useClinic()
  const [patient, setPatient] = useState<BackendPatient | null>(null)
  const [values, setValues] = useState({ firstName: '', lastName: '', phone: '', address: '' })
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    if (user?.role !== 'PACIENTE') return
    if (!user.patientId) {
      setError('Tu cuenta no tiene una ficha de paciente vinculada en este centro.')
      setLoading(false)
      return
    }
    let active = true
    patientApi.show(Number(user.patientId)).then(({ data }) => {
      if (!active) return
      setPatient(data)
      setValues({ firstName: data.first_name, lastName: data.last_name, phone: data.phone, address: data.address ?? '' })
    }).catch((failure) => {
      if (active) setError(firstApiErrorMessage(failure, 'No fue posible cargar tu ficha.'))
    }).finally(() => { if (active) setLoading(false) })
    return () => { active = false }
  }, [user?.role, user?.patientId])

  if (user?.role !== 'PACIENTE') return <Navigate to="/" replace />

  const submit = async (event: FormEvent) => {
    event.preventDefault()
    if (!patient) return
    setBusy(true)
    try {
      const response = await patientApi.update(patient.id, {
        first_name: values.firstName, last_name: values.lastName,
        phone: values.phone, address: values.address,
      })
      setPatient(response.data)
      toast.success('Datos actualizados')
    } catch (failure) {
      toast.error(firstApiErrorMessage(failure, 'No fue posible guardar tus datos.'))
    } finally {
      setBusy(false)
    }
  }

  return <><PageHeading title="Mis datos" description="Información administrativa utilizada por este centro para gestionar tus reservas." />
    <Card className="max-w-2xl"><CardHeader><CardTitle>Perfil del paciente</CardTitle></CardHeader><CardContent>
      {loading && <p>Cargando tus datos…</p>}
      {error && <p role="alert" className="text-rose-700">{error}</p>}
      {patient && <form className="grid gap-5 sm:grid-cols-2" onSubmit={(event) => void submit(event)}>
        <Field label="Nombres"><Input value={values.firstName} onChange={(e) => setValues({ ...values, firstName: e.target.value })} required /></Field>
        <Field label="Apellidos"><Input value={values.lastName} onChange={(e) => setValues({ ...values, lastName: e.target.value })} required /></Field>
        <Field label="RUT"><Input value={patient.rut} disabled /></Field>
        <Field label="Correo"><Input type="email" value={patient.email} disabled /></Field>
        <Field label="Teléfono"><Input value={values.phone} onChange={(e) => setValues({ ...values, phone: e.target.value })} required /></Field>
        <Field className="sm:col-span-2" label="Dirección"><Input value={values.address} onChange={(e) => setValues({ ...values, address: e.target.value })} /></Field>
        <Field label="Previsión"><Input value={patient.health_insurance ?? 'Sin registrar'} disabled /></Field>
        <Field label="Seguro complementario"><Input value={patient.medical_insurance || 'Sin seguro complementario'} disabled /></Field>
        <Button className="sm:col-span-2" disabled={busy}>{busy ? 'Guardando…' : 'Guardar cambios'}</Button>
      </form>}
    </CardContent></Card>
  </>
}
