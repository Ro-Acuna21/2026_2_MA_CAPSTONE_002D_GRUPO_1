import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { toast } from 'sonner'
import { ActionForm } from '@/components/action-form'
import { Logo } from '@/components/logo'
import { ConsentField, PatientFields } from '@/components/patient-fields'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { firstApiErrorMessage, sanctum } from '@/services/http'

type Pending = { center: { id: number; name: string; slug: string }; email: string }

export function PatientEnrollmentPage() {
  const { centerSlug = '' } = useParams()
  const navigate = useNavigate()
  const [pending, setPending] = useState<Pending | null>(null)
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    let active = true
    sanctum.pendingEnrollment().then(({ data }) => {
      if (!active) return
      if (data.center.slug !== centerSlug) {
        setError('La sesión pertenece a otro centro. Vuelve a iniciar sesión.')
      } else {
        setPending(data)
      }
    }).catch((failure: unknown) => {
      if (active) setError(firstApiErrorMessage(failure, 'Inicia sesión para crear tu ficha en este centro.'))
    }).finally(() => { if (active) setLoading(false) })
    return () => { active = false }
  }, [centerSlug])

  const base = `/centro/${centerSlug}`
  return <main className="min-h-screen bg-secondary p-5 sm:p-8"><div className="mx-auto max-w-3xl">
    <Logo name={pending?.center.name} />
    <Card className="mt-8"><CardHeader><CardTitle>Crear ficha en {pending?.center.name ?? 'el centro'}</CardTitle>
      <p className="text-sm text-muted-foreground">Tu cuenta global ya existe. Si confirmas, crearemos una ficha solo en este centro. Tu información de otros centros permanece separada.</p>
    </CardHeader><CardContent>
      {loading && <p role="status">Verificando tu sesión…</p>}
      {error && <p role="alert" className="mb-4 text-sm text-rose-700">{error}</p>}
      {pending && <><p className="mb-5 text-sm">Cuenta: <strong>{pending.email}</strong></p>
        <ActionForm className="sm:grid-cols-2" label="Crear mi ficha en este centro" onSave={async (values) => {
          try {
            await sanctum.enrollPatient({
              first_name: values.firstName.trim(), last_name: values.lastName.trim(),
              rut: values.rut, birth_date: values.birthDate, phone: values.phone,
              health_insurance: values.healthInsurance, medical_insurance: values.medicalInsurance || null,
              address: values.address || null, region_code: values.region_code || null,
              commune_code: values.commune_code || null, consent: values.consent === 'on',
            })
            toast.success('Ficha creada en este centro.')
            window.location.assign(base)
          } catch (failure) {
            throw new Error(firstApiErrorMessage(failure, 'No fue posible crear tu ficha.'))
          }
        }}><PatientFields includeEmail={false} /><ConsentField /></ActionForm>
        <Button className="mt-5" variant="outline" onClick={() => { void sanctum.logout().finally(() => navigate(`${base}/ingresar`)) }}>Cancelar</Button>
      </>}
      {!pending && !loading && <Link className="text-sm font-medium text-primary underline" to={`${base}/ingresar`}>Volver al acceso</Link>}
    </CardContent></Card>
  </div></main>
}
