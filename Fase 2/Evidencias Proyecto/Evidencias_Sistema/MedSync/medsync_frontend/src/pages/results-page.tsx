import { useCallback, useEffect, useState } from 'react'
import { Navigate } from 'react-router-dom'
import { toast } from 'sonner'
import { useClinic } from '@/state/clinic-store'
import { PageHeading } from '@/components/page-heading'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Field, Input, Select } from '@/components/ui/form-controls'
import { Button } from '@/components/ui/button'
import { appointmentApi, firstApiErrorMessage, type BackendAppointment } from '@/services/http'
import { resultApi, type MedicalResult, type ResultType } from '@/services/results-reports-api'
import { useCenterPath } from '@/lib/tenant'

export function ResultsPage() {
  const { user } = useClinic()
  const centerPath = useCenterPath()
  const [types, setTypes] = useState<ResultType[]>([])
  const [results, setResults] = useState<MedicalResult[]>([])
  const [appointments, setAppointments] = useState<BackendAppointment[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const [typeName, setTypeName] = useState('')
  const [appointmentId, setAppointmentId] = useState('')
  const [typeId, setTypeId] = useState('')
  const [performedAt, setPerformedAt] = useState(new Date().toISOString().slice(0, 10))
  const [file, setFile] = useState<File | null>(null)
  const [preview, setPreview] = useState<{ id: number; url: string } | null>(null)
  const admin = user?.role === 'ADMIN'
  const professional = user?.role === 'PROFESIONAL'

  const refresh = useCallback(async () => {
    try {
      setError('')
      const [typeResponse, resultResponse, appointmentResponse] = await Promise.all([
        admin || professional ? resultApi.types() : Promise.resolve({ data: [] }),
        admin ? Promise.resolve({ data: [] }) : resultApi.list(),
        professional ? appointmentApi.list() : Promise.resolve({ data: [] }),
      ])
      setTypes(typeResponse.data)
      setResults(resultResponse.data)
      setAppointments(appointmentResponse.data.filter((item) => item.status === 'ATENDIDA'))
    } catch (cause) {
      setError(firstApiErrorMessage(cause, 'No fue posible cargar los resultados.'))
    } finally { setLoading(false) }
  }, [admin, professional])
  useEffect(() => { if (user && ['ADMIN', 'PROFESIONAL', 'PACIENTE'].includes(user.role)) void refresh() }, [refresh, user])
  useEffect(() => () => { if (preview) URL.revokeObjectURL(preview.url) }, [preview])
  if (!user || !['ADMIN', 'PROFESIONAL', 'PACIENTE'].includes(user.role)) return <Navigate to={centerPath()} replace />

  async function createType(event: React.FormEvent) {
    event.preventDefault(); setBusy(true)
    try { await resultApi.createType(typeName); setTypeName(''); await refresh(); toast.success('Tipo creado.') }
    catch (cause) { toast.error(firstApiErrorMessage(cause, 'No fue posible crear el tipo.')) }
    finally { setBusy(false) }
  }
  async function createResult(event: React.FormEvent) {
    event.preventDefault()
    if (!file || file.size > 5 * 1024 * 1024) { toast.error('Selecciona un archivo de hasta 5 MB.'); return }
    const form = new FormData()
    form.set('appointment_id', appointmentId); form.set('result_type_id', typeId); form.set('performed_at', performedAt); form.set('file', file)
    setBusy(true)
    try { await resultApi.create(form); setFile(null); await refresh(); toast.success('Borrador guardado.') }
    catch (cause) { toast.error(firstApiErrorMessage(cause, 'No fue posible guardar el borrador.')) }
    finally { setBusy(false) }
  }
  async function changeStatus(result: MedicalResult, action: 'publish' | 'void') {
    const reason = action === 'void' ? window.prompt('Motivo de anulación (mínimo 5 caracteres):') : null
    if (action === 'void' && reason === null) return
    setBusy(true)
    try {
      if (action === 'publish') await resultApi.publish(result.id)
      else await resultApi.void(result.id, reason ?? '')
      await refresh(); toast.success(action === 'publish' ? 'Resultado publicado.' : 'Resultado anulado.')
    } catch (cause) { toast.error(firstApiErrorMessage(cause, 'No fue posible cambiar el estado.')) }
    finally { setBusy(false) }
  }
  async function openFile(result: MedicalResult) {
    const first = result.files[0]; if (!first) return
    try { const blob = await resultApi.download(result.id, first.id); setPreview({ id: result.id, url: URL.createObjectURL(blob) }) }
    catch (cause) { toast.error(firstApiErrorMessage(cause, 'No fue posible abrir el documento.')) }
  }
  return <>
    <PageHeading title={admin ? 'Tipos de informes' : professional ? 'Informes y resultados' : 'Mis informes y resultados'} description="Documentos clínicos del centro, con acceso según tu rol." />
    {loading && <p>Cargando resultados…</p>}
    {error && <p role="alert" className="mb-4 text-destructive">{error}</p>}
    {admin && <Card className="mb-5"><CardHeader><CardTitle>Tipos de informe</CardTitle></CardHeader><CardContent>
      <form onSubmit={createType} className="flex flex-wrap items-end gap-3"><Field label="Nombre"><Input required maxLength={120} value={typeName} onChange={(event) => setTypeName(event.target.value)} /></Field><Button disabled={busy}>Crear tipo</Button></form>
      <div className="mt-4 grid gap-2">{types.map((type) => <div key={type.id} className="flex items-center justify-between rounded border p-3"><span>{type.name} · {type.is_active ? 'Activo' : 'Inactivo'}</span><Button variant="outline" disabled={busy} onClick={async () => { try { await resultApi.updateType(type.id, { is_active: !type.is_active }); await refresh() } catch (cause) { toast.error(firstApiErrorMessage(cause, 'No se pudo actualizar.')) } }}>{type.is_active ? 'Desactivar' : 'Activar'}</Button></div>)}</div>
    </CardContent></Card>}
    {professional && <Card className="mb-5"><CardHeader><CardTitle>Cargar borrador</CardTitle></CardHeader><CardContent><form onSubmit={createResult} className="grid gap-4 sm:grid-cols-2">
      <Field label="Atención realizada"><Select required value={appointmentId} onChange={(event) => setAppointmentId(event.target.value)}><option value="">Seleccionar atención</option>{appointments.map((item) => <option key={item.id} value={item.id}>{item.appointment_date} · {item.patient?.first_name} {item.patient?.last_name} · {item.service?.name}</option>)}</Select></Field>
      <Field label="Tipo de informe"><Select required value={typeId} onChange={(event) => setTypeId(event.target.value)}><option value="">Seleccionar tipo</option>{types.map((type) => <option key={type.id} value={type.id}>{type.name}</option>)}</Select></Field>
      <Field label="Fecha de realización"><Input type="date" required max={new Date().toISOString().slice(0, 10)} value={performedAt} onChange={(event) => setPerformedAt(event.target.value)} /></Field>
      <Field label="Documento ficticio (PDF, PNG, JPEG o TXT; máximo 5 MB)"><Input type="file" required accept=".pdf,.png,.jpg,.jpeg,.txt" onChange={(event) => setFile(event.target.files?.[0] ?? null)} /></Field>
      <Button disabled={busy || !appointments.length}>Guardar borrador</Button>
    </form>{!appointments.length && <p className="mt-3 text-sm text-muted-foreground">Necesitas una atención marcada como atendida para cargar un resultado.</p>}</CardContent></Card>}
    {!admin && !loading && !error && <div className="grid gap-4">{!results.length && <Card><CardContent className="p-8 text-center text-muted-foreground">No hay resultados disponibles.</CardContent></Card>}{results.map((result) => <Card key={result.id}><CardContent className="p-5">
      <div className="flex justify-between gap-3"><div><h2 className="font-semibold">{result.result_type_name}</h2><p className="text-sm text-muted-foreground">{professional ? result.patient_name : result.professional_name} · {result.performed_at}</p></div><span>{result.status === 'PUBLISHED' ? 'Publicado' : result.status === 'VOIDED' ? 'Anulado' : 'Borrador'}</span></div>
      {result.void_reason && <p className="mt-2 text-sm">Motivo: {result.void_reason}</p>}
      <div className="mt-4 flex flex-wrap gap-2"><Button variant="outline" onClick={() => void openFile(result)}>Abrir documento</Button>{professional && result.status === 'DRAFT' && <Button disabled={busy} onClick={() => void changeStatus(result, 'publish')}>Publicar</Button>}{professional && result.status !== 'VOIDED' && <Button variant="destructive" disabled={busy} onClick={() => void changeStatus(result, 'void')}>Anular</Button>}</div>
      {preview?.id === result.id && <div className="mt-4"><a className="text-primary underline" href={preview.url} download={result.files[0]?.filename}>Descargar {result.files[0]?.filename}</a><iframe sandbox="" title="Documento clínico" src={preview.url} className="mt-3 h-96 w-full rounded border" /></div>}
    </CardContent></Card>)}</div>}
  </>
}
