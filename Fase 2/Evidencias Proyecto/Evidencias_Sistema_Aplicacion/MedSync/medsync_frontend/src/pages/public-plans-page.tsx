import { ArrowRight, Check } from 'lucide-react'
import { useEffect, useState, type FormEvent } from 'react'
import { Link, useLocation, useSearchParams } from 'react-router-dom'
import { PublicFooter, PublicNavbar } from '@/components/public-site'
import { Button } from '@/components/ui/button'
import { Field, Input, Select } from '@/components/ui/form-controls'
import { commercialInquiryApi } from '@/services/commercial-inquiry-api'
import { firstApiErrorMessage } from '@/services/http'
import { planApi, type Plan } from '@/services/subscription-api'

const clp = new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 })

export function PublicPlansPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const location = useLocation()
  const [plans, setPlans] = useState<Plan[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [sending, setSending] = useState(false)
  const [result, setResult] = useState('')
  const [submitError, setSubmitError] = useState('')
  const selectedId = Number(searchParams.get('plan')) || null

  useEffect(() => {
    planApi.publicList().then(({ data }) => { setPlans(data); setError('') })
      .catch((cause) => setError(firstApiErrorMessage(cause, 'No fue posible cargar los planes.')))
      .finally(() => setLoading(false))
  }, [])

  useEffect(() => {
    if (location.hash === '#solicitar') requestAnimationFrame(() => document.getElementById('solicitar')?.scrollIntoView({ behavior: 'smooth' }))
  }, [location.hash, selectedId])

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = event.currentTarget
    const values = new FormData(form)
    setSending(true)
    setResult('')
    setSubmitError('')
    try {
      const response = await commercialInquiryApi.create({
        plan_id: selectedId && plans.some((plan) => plan.id === selectedId) ? selectedId : null,
        contact_name: String(values.get('contact_name')).trim(),
        center_name: String(values.get('center_name')).trim(),
        email: String(values.get('email')).trim(),
        phone: String(values.get('phone')).trim() || null,
        message: String(values.get('message')).trim() || null,
      })
      setResult(response.message)
      form.reset()
    } catch (cause) {
      setSubmitError(firstApiErrorMessage(cause, 'No fue posible enviar la solicitud.'))
    } finally {
      setSending(false)
    }
  }

  return <main className="min-h-screen bg-background">
    <PublicNavbar />
    <section className="bg-[#103d42] px-5 py-16 text-white lg:py-24">
      <div className="mx-auto max-w-6xl"><p className="text-sm font-semibold text-emerald-200">Planes comerciales MedSync</p>
        <h1 className="mt-3 text-4xl font-bold sm:text-5xl">Elige la capacidad adecuada para tu centro</h1>
        <p className="mt-5 max-w-2xl text-lg text-emerald-50/80">Compara funcionalidades, límites y precios publicados. Puedes solicitar información antes de decidir; el envío de este formulario no crea una suscripción ni registra un pago.</p>
      </div>
    </section>
    <section className="mx-auto max-w-6xl px-5 py-14 lg:py-20" aria-label="Detalle de planes">
      {loading && <p role="status">Cargando planes…</p>}
      {error && <p role="alert" className="text-destructive">{error}</p>}
      {!loading && !error && plans.length === 0 && <p>Aún no hay planes comerciales publicados. Puedes dejarnos una consulta abajo.</p>}
      <div className="grid gap-6 lg:grid-cols-3">{plans.map((plan) => <article key={plan.id} className={`rounded-2xl border bg-card p-7 shadow-soft ${selectedId === plan.id ? 'border-primary ring-2 ring-primary/20' : ''}`}>
        <p className="text-sm font-semibold text-primary">Plan {plan.name}</p>
        <h2 className="mt-3 text-3xl font-bold">{plan.monthly_price_clp > 0 ? `${clp.format(plan.monthly_price_clp)} / mes` : 'Precio a consultar'}</h2>
        {plan.description && <p className="mt-4 text-sm leading-6 text-muted-foreground">{plan.description}</p>}
        <div className="mt-6 border-t pt-5 text-sm"><p><strong>Profesionales:</strong> {plan.professional_limit ?? 'Según propuesta'}</p><p className="mt-2"><strong>Usuarios:</strong> {plan.user_limit ?? 'Según propuesta'}</p></div>
        <h3 className="mt-6 font-semibold">Funcionalidades</h3>
        {plan.features?.length ? <ul className="mt-3 space-y-3 text-sm">{plan.features.map((feature) => <li key={feature} className="flex gap-2"><Check className="mt-0.5 size-4 shrink-0 text-primary" />{feature}</li>)}</ul> : <p className="mt-3 text-sm text-muted-foreground">Consulta las funcionalidades con el equipo comercial.</p>}
        <Button asChild className="mt-7 w-full"><Link to={`/planes?plan=${plan.id}#solicitar`}>Solicitar información <ArrowRight className="size-4" /></Link></Button>
      </article>)}</div>
    </section>
    <section id="solicitar" className="scroll-mt-24 bg-muted/55 px-5 py-14 lg:py-20"><div className="mx-auto max-w-3xl rounded-2xl border bg-card p-6 shadow-soft sm:p-9">
      <h2 className="text-3xl font-bold">Solicitar información comercial</h2>
      <p className="mt-3 text-sm text-muted-foreground">La solicitud queda registrada para la administración de plataforma. No compartas datos de pacientes ni antecedentes clínicos.</p>
      <form onSubmit={submit} className="mt-7 grid gap-5 sm:grid-cols-2">
        <Field label="Nombre de contacto"><Input name="contact_name" minLength={2} maxLength={150} required /></Field>
        <Field label="Nombre del centro"><Input name="center_name" minLength={2} maxLength={150} required /></Field>
        <Field label="Correo electrónico"><Input name="email" type="email" maxLength={150} required /></Field>
        <Field label="Teléfono (opcional)"><Input name="phone" type="tel" maxLength={30} /></Field>
        <Field label="Plan de interés" className="sm:col-span-2"><Select value={selectedId ?? ''} onChange={(event) => setSearchParams(event.target.value ? { plan: event.target.value } : {})}><option value="">Quiero orientación</option>{plans.map((plan) => <option key={plan.id} value={plan.id}>{plan.name}</option>)}</Select></Field>
        <Field label="Consulta (opcional)" className="sm:col-span-2"><textarea name="message" maxLength={2000} className="min-h-28 w-full rounded-md border border-input bg-background p-3 text-sm" /></Field>
        {submitError && <p role="alert" className="sm:col-span-2 text-sm text-destructive">{submitError}</p>}
        {result && <p role="status" className="sm:col-span-2 text-sm text-emerald-700">{result}</p>}
        <Button disabled={sending} className="sm:col-span-2">{sending ? 'Enviando…' : 'Enviar solicitud'}</Button>
      </form>
    </div></section>
    <PublicFooter />
  </main>
}
