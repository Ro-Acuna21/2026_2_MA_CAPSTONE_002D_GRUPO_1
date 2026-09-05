import { Navigate } from 'react-router-dom'
import { useClinic } from '@/state/clinic-store'
import { PageHeading } from '@/components/page-heading'
import { ActionForm } from '@/components/action-form'
import { AccessForm } from '@/components/access-form'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Field, Input, Select } from '@/components/ui/form-controls'
import { type Organization, type SubscriptionStatus } from '@/domain/types'

const statuses: Record<SubscriptionStatus, string> = { TRIAL: 'En prueba', ACTIVE: 'Activa', EXPIRED: 'Vencida', SUSPENDED: 'Suspendida' }
function CenterForm({ center }: { center?: Organization }) {
  const { saveOrganization } = useClinic()
  return <ActionForm reset={!center} className="sm:grid-cols-2" label={center ? 'Actualizar centro' : 'Crear centro'} onSave={(v) => saveOrganization({ id: center?.id ?? '', name: v.name, slug: v.slug, plan: v.plan as Organization['plan'], subscription: v.subscription as SubscriptionStatus, active: v.active === 'true' })}><Field label="Nombre del centro"><Input name="name" defaultValue={center?.name} required minLength={3} /></Field><Field label="Dirección del centro en la plataforma"><Input name="slug" defaultValue={center?.slug} placeholder="centro-alameda" pattern="[a-z0-9]+(-[a-z0-9]+)*" required /></Field><Field label="Plan"><Select name="plan" defaultValue={center?.plan ?? 'STARTER'}><option>STARTER</option><option>PRO</option></Select></Field><Field label="Suscripción"><Select name="subscription" defaultValue={center?.subscription ?? 'TRIAL'}>{Object.entries(statuses).map(([key, label]) => <option key={key} value={key}>{label}</option>)}</Select></Field><Field label="Acceso al centro"><Select name="active" defaultValue={String(center?.active ?? true)}><option value="true">Habilitado</option><option value="false">Deshabilitado</option></Select></Field></ActionForm>
}
export function PlatformPage() {
  const { user, data } = useClinic()
  if (user?.role !== 'SUPER_ADMIN') return <Navigate to="/" replace />
  return <><PageHeading title="Administración de plataforma" description="Centros médicos, responsables y suscripciones de MedSync." /><div className="mb-6 grid gap-4 sm:grid-cols-3">{[['Centros registrados', data.organizations.length], ['Acceso habilitado', data.organizations.filter((o) => o.active).length], ['Suscripciones activas', data.organizations.filter((o) => o.subscription === 'ACTIVE').length]].map(([label, total]) => <Card key={label}><CardContent className="p-5"><p className="text-sm text-muted-foreground">{label}</p><p className="mt-2 text-3xl font-bold">{total}</p></CardContent></Card>)}</div><Card className="mb-6"><CardHeader><CardTitle>Nuevo centro médico</CardTitle></CardHeader><CardContent><CenterForm /></CardContent></Card><p className="mb-5 text-sm text-muted-foreground">Los estados de suscripción son informativos en esta etapa. Deshabilitar el acceso impide ingresar al centro y conserva su información.</p><div className="grid gap-5">{data.organizations.map((o) => <Card key={o.id}><CardHeader><CardTitle>{o.name}</CardTitle></CardHeader><CardContent><details><summary className="cursor-pointer font-medium text-primary">Editar centro y suscripción</summary><div className="mt-4"><CenterForm center={o} /></div></details><details className="mt-5"><summary className="cursor-pointer font-medium text-primary">Administradores de este centro</summary><div className="mt-4"><AccessForm centerId={o.id} platform /></div></details></CardContent></Card>)}</div></>
}
