import { useEffect } from 'react'
import { Link, Navigate, useNavigate, useParams } from 'react-router-dom'
import { Logo } from '@/components/logo'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Field, Input } from '@/components/ui/form-controls'
import { ActionForm } from '@/components/action-form'
import { ConsentField, PatientFields, patientFromForm } from '@/components/patient-fields'
import { useClinic } from '@/state/clinic-store'
import { toast } from 'sonner'
import { ApiError, sanctum } from '@/services/http'

// Centro ya conectado al backend real de Laravel en esta iteración. El resto
// de los centros de la demo sigue funcionando solo con datos mock locales.
const BACKEND_READY_SLUG = 'clinica-horizonte'

async function syncRegisterWithBackend(v: Record<string, string>) {
  try {
    await sanctum.register({
      first_name: v.firstName.trim(),
      last_name: v.lastName.trim(),
      rut: v.rut,
      birth_date: v.birthDate,
      email: v.email.trim(),
      phone: v.phone,
      health_insurance: v.healthInsurance,
      medical_insurance: v.medicalInsurance || null,
      address: v.address || null,
      password: v.password,
      password_confirmation: v.confirmation,
      consent: v.consent === 'on',
    })
    toast.success('Guardado en la base de datos real (Laravel + Postgres).')
  } catch (error) {
    const detail = error instanceof ApiError ? error.message : 'No se pudo conectar con el backend (¿está corriendo "php artisan serve"?).'
    console.error('No fue posible registrar en el backend real:', error)
    toast.error(`Backend real: ${detail}`)
  }
}

export function RegisterPage() {
  const { user, register, publicOrganizations, organization, logout } = useClinic(); const navigate = useNavigate(); const { centerSlug = '' } = useParams()
  const center = publicOrganizations.find((item) => item.slug === centerSlug); const base = `/centro/${centerSlug}`
  const incompatibleSession = Boolean(user && (user.role === 'SUPER_ADMIN' || organization?.id !== center?.id))
  useEffect(() => { document.title = center?.name ?? 'Portal médico' }, [center?.name])
  useEffect(() => { if (incompatibleSession) logout() }, [incompatibleSession, logout])
  if (!center) return <Navigate to="/" replace />
  if (incompatibleSession) return <main className="grid min-h-screen place-items-center"><p>Abriendo el registro del centro…</p></main>
  if (user) return <Navigate to={organization?.id === center.id ? base : `${base}/ingresar`} replace />
  return <main className="min-h-screen bg-secondary p-5 sm:p-8"><div className="mx-auto max-w-3xl"><Logo name={center.name} /><Card className="mt-8"><CardHeader><CardTitle>Crear cuenta de paciente</CardTitle><p className="text-sm text-muted-foreground">Crea tu acceso privado a {center.name}.</p><p className="text-sm text-muted-foreground">Si recepción ya creó tu ficha, usa el mismo RUT y correo para activar tu acceso sin duplicar tus datos.</p><p className="text-sm text-amber-800">Demostración local: utiliza datos ficticios. No se envían correos de verificación.</p></CardHeader><CardContent><ActionForm className="sm:grid-cols-2" label="Crear mi cuenta" onSave={async (v) => { await register(patientFromForm(v), v.password, v.confirmation, center.id); if (center.slug === BACKEND_READY_SLUG) await syncRegisterWithBackend(v); toast.success('Cuenta creada. Ya puedes ingresar.'); navigate(`${base}/ingresar`) }}><PatientFields /><Field label="Contraseña"><Input name="password" type="password" autoComplete="new-password" minLength={8} required /></Field><Field label="Confirmar contraseña"><Input name="confirmation" type="password" autoComplete="new-password" minLength={8} required /></Field><ConsentField /><p className="col-span-full text-xs text-muted-foreground">Mínimo 8 caracteres, con mayúscula, minúscula y número.</p></ActionForm><Link className="mt-6 inline-block text-sm font-medium text-primary underline" to={`${base}/ingresar`}>Ya tengo cuenta · Ingresar</Link></CardContent></Card></div></main>
}
