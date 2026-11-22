import { expect, test, type Page } from '@playwright/test'
import { mkdir } from 'node:fs/promises'
import path from 'node:path'

const centerSlug = 'clinica-horizonte'
const centerBase = `/centro/${centerSlug}`
const evidenceDirectory = process.env.MEDSYNC_EVIDENCE_DIR ?? 'C:/Users/rober/Desktop/Evidencias prueba'

type FictionalPatient = {
  firstName: string
  lastName: string
  rut: string
  birthDate: string
  email: string
  phone: string
  password: string
}

function rutDigit(body: string) {
  let sum = 0
  let factor = 2
  for (let index = body.length - 1; index >= 0; index -= 1) {
    sum += Number(body[index]) * factor
    factor = factor === 7 ? 2 : factor + 1
  }
  const remainder = 11 - (sum % 11)
  return remainder === 11 ? '0' : remainder === 10 ? 'K' : String(remainder)
}

function makeFictionalPatient(): FictionalPatient {
  const unique = `${Date.now()}${Math.floor(Math.random() * 1000)}`
  const rutBody = String(18_000_000 + (Number(unique.slice(-8)) % 60_000_000))
  return {
    firstName: 'Paciente Playwright',
    lastName: 'Prueba Automatizada MedSync',
    rut: `${rutBody}-${rutDigit(rutBody)}`,
    birthDate: '1995-05-15',
    email: `medsync.e2e.${unique}@example.com`,
    phone: '+56912345678',
    password: `PruebaE2E${unique.slice(-6)}A`,
  }
}

async function saveEvidence(page: Page, filename: string, fullPage = false) {
  await mkdir(evidenceDirectory, { recursive: true })
  await page.screenshot({ path: path.join(evidenceDirectory, filename), fullPage })
}

async function registerFictionalPatient(page: Page, patient: FictionalPatient, evidencePrefix?: string, base = centerBase) {
  await page.goto(`${base}/crear-cuenta`)
  await expect(page.getByRole('heading', { name: 'Crear cuenta de paciente' })).toBeVisible()
  await page.getByLabel('Nombres').fill(patient.firstName)
  await page.getByLabel('Apellidos').fill(patient.lastName)
  await page.getByLabel('RUT').fill(patient.rut)
  await page.getByLabel('Fecha de nacimiento').fill(patient.birthDate)
  await page.getByLabel('Correo electrónico').fill(patient.email)
  await page.getByLabel('Teléfono').fill(patient.phone)
  await page.getByLabel('Previsión de salud').selectOption({ label: 'Fonasa' })
  await page.getByRole('checkbox').check()
  await page.getByLabel('Contraseña', { exact: true }).fill(patient.password)
  await page.getByLabel('Confirmar contraseña').fill(patient.password)
  if (evidencePrefix) await saveEvidence(page, `${evidencePrefix}_cuenta_formulario_completado.png`, true)
  await page.getByRole('button', { name: 'Crear mi cuenta' }).click()
  await expect(page).toHaveURL(new RegExp(`${base}/?$`))
  if (evidencePrefix) await saveEvidence(page, `${evidencePrefix}_cuenta_creada.png`)
}

async function selectFirstOption(page: Page, label: string) {
  const select = page.getByLabel(label)
  await expect.poll(() => select.locator('option').count()).toBeGreaterThan(1)
  const value = await select.locator('option').nth(1).getAttribute('value')
  if (!value) throw new Error(`No hay una opción disponible en ${label}.`)
  await select.selectOption(value)
}

async function chooseBookingCatalog(page: Page, evidencePath?: string) {
  await page.goto(`${centerBase}/reservar`)
  await expect(page.getByRole('heading', { name: 'Selecciona la atención' })).toBeVisible()
  await selectFirstOption(page, 'Especialidad')
  await selectFirstOption(page, 'Prestación')
  await selectFirstOption(page, 'Profesional')
  if (evidencePath) await saveEvidence(page, evidencePath)
  await page.getByRole('button', { name: 'Continuar' }).click()
  await expect(page.getByRole('heading', { name: 'Elige fecha y horario' })).toBeVisible()
}

function dateAfter(days: number) {
  const date = new Date()
  date.setHours(12, 0, 0, 0)
  date.setDate(date.getDate() + days)
  return date.toISOString().slice(0, 10)
}

async function slotsForDate(page: Page, date: string, fieldLabel = 'Fecha') {
  const response = page.waitForResponse((candidate) => {
    if (!candidate.url().includes('/api/v1/appointments/available-slots')) return false
    try {
      return new URL(candidate.url()).searchParams.get('date') === date
    } catch {
      return false
    }
  })
  await page.getByLabel(fieldLabel).fill(date)
  const result = await response
  expect(result.ok(), `La consulta de disponibilidad falló para ${date}.`).toBe(true)
  const body = await result.json() as { data?: { slots?: unknown[] } }
  const expectedCount = body.data?.slots?.length ?? 0
  const times = page.getByRole('button', { name: /^\d{2}:\d{2}$/ })
  await expect.poll(() => times.count()).toBe(expectedCount)
  return times
}

async function findDateWithAvailability(page: Page, firstOffset: number, lastOffset: number, fieldLabel = 'Fecha') {
  for (let offset = firstOffset; offset <= lastOffset; offset += 1) {
    const date = dateAfter(offset)
    const times = await slotsForDate(page, date, fieldLabel)
    if (await times.count()) return { date, times }
  }
  throw new Error(`No se encontraron horarios disponibles entre ${firstOffset} y ${lastOffset} días.`)
}

async function findDateWithoutAvailability(page: Page, firstOffset: number, lastOffset: number, fieldLabel = 'Fecha') {
  for (let offset = firstOffset; offset <= lastOffset; offset += 1) {
    const date = dateAfter(offset)
    const times = await slotsForDate(page, date, fieldLabel)
    if (!(await times.count())) return date
  }
  throw new Error(`No se encontró una fecha sin horarios entre ${firstOffset} y ${lastOffset} días.`)
}

async function createAppointment(page: Page, evidencePrefix?: string) {
  await chooseBookingCatalog(page, evidencePrefix ? `${evidencePrefix}_reserva_atencion_completada.png` : undefined)
  const { date, times } = await findDateWithAvailability(page, 3, 45)
  if (evidencePrefix) await saveEvidence(page, `${evidencePrefix}_reserva_fecha_y_horarios.png`)
  await expect(page.getByText(new RegExp(`Precio para el ${date}`))).toBeVisible()
  await times.first().click()
  await expect(page.getByText(new RegExp(`Precio para el ${date}`))).toBeVisible()
  if (evidencePrefix) await saveEvidence(page, `${evidencePrefix}_reserva_horario_seleccionado.png`)
  await page.getByRole('button', { name: 'Continuar' }).click()
  await expect(page.getByRole('heading', { name: 'Revisa tu reserva' })).toBeVisible()
  if (evidencePrefix) await saveEvidence(page, `${evidencePrefix}_reserva_revision.png`)

  const creation = page.waitForResponse((response) =>
    new URL(response.url()).pathname.endsWith('/api/v1/appointments')
      && response.request().method() === 'POST',
  )
  await page.getByRole('button', { name: 'Confirmar reserva' }).click()
  const response = await creation
  const body = await response.json() as {
    data?: {
      id?: number | string
      status?: string
      patient?: { first_name?: string; last_name?: string } | null
    }
  }
  expect(response.status()).toBe(201)
  const id = String(body.data?.id ?? '')
  expect(id).not.toBe('')
  await expect(page.getByRole('heading', { name: 'Mis citas' })).toBeVisible()
  if (evidencePrefix) await saveEvidence(page, `${evidencePrefix}_reserva_resultado.png`)
  return { id, date, body: body.data }
}

test('Ficha 6 · paciente crea una reserva pendiente y la ve persistida en Mis citas', async ({ page }) => {
  const patient = makeFictionalPatient()
  await registerFictionalPatient(page, patient, '01')
  const created = await createAppointment(page, '02')

  // Consulta posterior al backend para comprobar el registro persistido,
  // además del resultado que muestra la interfaz.
  const readBack = await page.evaluate(async () => {
    const apiUrl = `${window.location.protocol}//${window.location.hostname}:8000/api/v1/appointments/my`
    const response = await fetch(apiUrl, {
      credentials: 'include',
      headers: { Accept: 'application/json' },
    })
    return { status: response.status, text: await response.text() }
  })
  expect(readBack.status).toBe(200)
  const readBody = JSON.parse(readBack.text) as {
      data?: Array<{
        id: number | string
        status: string
        patient?: { first_name?: string; last_name?: string } | null
      }>
    }
  const persisted = readBody.data?.find((item) => String(item.id) === created.id)
  expect.soft(persisted, 'La API no devolvió la cita creada al volver a consultarla.').toBeTruthy()
  if (persisted) {
    expect.soft(persisted.status).toBe('PENDIENTE')
    expect.soft(persisted.patient?.first_name).toBe(patient.firstName)
    expect.soft(persisted.patient?.last_name).toBe(patient.lastName)
  }
  // El nombre debe estar disponible inmediatamente, sin recargar la pantalla.
  await expect(page.getByRole('heading', { name: 'Mis citas' })).toBeVisible()
  const appointmentRow = page.getByRole('row').filter({ has: page.locator(`a[href="${centerBase}/citas/${created.id}"]`) })
  await expect(appointmentRow).toBeVisible()
  await expect(appointmentRow).toContainText(`${patient.firstName} ${patient.lastName}`)
  console.log(`Reserva id=${created.id}; API POST=${created.body?.status}; lectura posterior=${persisted?.status ?? 'no encontrada'}; paciente=${persisted?.patient?.first_name ?? 'sin nombre'}`)
  await saveEvidence(page, '03_reserva_creada_mis_citas.png')
  expect.soft(await appointmentRow.innerText()).toContain('PENDIENTE')
  expect.soft(await appointmentRow.innerText()).toContain(`${created.date.slice(8, 10)}-${created.date.slice(5, 7)}-${created.date.slice(0, 4)}`)
})

test('Ficha 6 · cuenta MedSync existente inicia sesión y crea su ficha local en otro centro', async ({ page, request }) => {
  const patient = makeFictionalPatient()
  const centersResponse = await request.get('http://127.0.0.1:8000/api/public/centers')
  expect(centersResponse.ok()).toBe(true)
  const centersBody = await centersResponse.json() as { data?: Array<{ name: string; slug: string }> }
  const targetCenter = centersBody.data?.find((center) => center.slug !== centerSlug)
  test.skip(!targetCenter, 'Se necesita otro centro activo para probar la inscripción local.')
  if (!targetCenter) return
  const targetBase = `/centro/${targetCenter.slug}`

  // Crear la cuenta global y su primera ficha en Horizonte.
  await registerFictionalPatient(page, patient)

  // La misma cuenta no debe volver a registrarse en otro centro.
  await page.goto(`${targetBase}/crear-cuenta`)
  await expect(page.getByRole('heading', { name: 'Crear cuenta de paciente' })).toBeVisible()
  await page.getByLabel('Nombres').fill(patient.firstName)
  await page.getByLabel('Apellidos').fill(patient.lastName)
  await page.getByLabel('RUT').fill(patient.rut)
  await page.getByLabel('Fecha de nacimiento').fill(patient.birthDate)
  await page.getByLabel('Correo electrónico').fill(patient.email)
  await page.getByLabel('Teléfono').fill(patient.phone)
  await page.getByLabel('Previsión de salud').selectOption({ label: 'Fonasa' })
  await page.getByRole('checkbox').check()
  await page.getByLabel('Contraseña', { exact: true }).fill(patient.password)
  await page.getByLabel('Confirmar contraseña').fill(patient.password)
  await saveEvidence(page, '04_cuenta_existente_registro_formulario.png', true)

  const duplicateRegistration = page.waitForResponse((response) =>
    new URL(response.url()).pathname.endsWith('/api/register')
      && response.request().method() === 'POST',
  )
  await page.getByRole('button', { name: 'Crear mi cuenta' }).click()
  const duplicateResponse = await duplicateRegistration
  expect(duplicateResponse.status()).toBe(422)
  await expect(page.getByRole('alert')).toContainText(
    'Ya tienes una cuenta MedSync. Inicia sesión en este centro para crear tu ficha local.',
  )
  await saveEvidence(page, '05_cuenta_existente_mensaje_de_validacion.png', true)

  // Iniciar sesión en el centro nuevo lleva al formulario de inscripción local.
  await page.goto(`${targetBase}/ingresar`)
  await expect(page.getByRole('heading', { name: new RegExp(`Acceso a ${targetCenter.name}`) })).toBeVisible()
  await page.getByLabel('Correo electrónico').fill(patient.email)
  await page.getByLabel('Contraseña').fill(patient.password)
  await saveEvidence(page, '06_acceso_al_centro_con_cuenta_existente.png')
  const centerLogin = page.waitForResponse((response) =>
    new URL(response.url()).pathname.endsWith('/api/login')
      && response.request().method() === 'POST',
  )
  await page.getByRole('button', { name: 'Ingresar', exact: true }).click()
  expect((await centerLogin).status()).toBe(409)
  await expect(page).toHaveURL(new RegExp(`${targetBase}/crear-ficha$`))
  await expect(page.getByRole('heading', { name: `Crear ficha en ${targetCenter.name}` })).toBeVisible()
  await expect(page.getByLabel('Nombres')).toHaveValue(patient.firstName)
  await expect(page.getByLabel('Apellidos')).toHaveValue(patient.lastName)
  await expect(page.getByLabel('RUT')).toHaveValue(patient.rut)
  await expect(page.getByLabel('Fecha de nacimiento')).toHaveValue(patient.birthDate)
  await expect(page.getByText(`Cuenta: ${patient.email}`)).toBeVisible()
  await page.getByLabel('Previsión de salud').selectOption({ label: 'Fonasa' })
  await page.getByRole('checkbox').check()
  await saveEvidence(page, '07_ficha_local_formulario_completado.png', true)

  const enrollment = page.waitForResponse((response) =>
    new URL(response.url()).pathname.endsWith('/api/v1/patient-enrollment')
      && response.request().method() === 'POST',
  )
  await page.getByRole('button', { name: 'Crear mi ficha en este centro' }).click()
  const enrollmentResponse = await enrollment
  expect(enrollmentResponse.status()).toBe(201)
  await expect(page).toHaveURL(new RegExp(`${targetBase}/?$`))
  await expect(page.getByRole('link', { name: 'Mis citas' })).toBeVisible()
  await saveEvidence(page, '08_ficha_local_creada_en_el_centro.png')

  const me = await page.evaluate(async () => {
    const response = await fetch(`${window.location.protocol}//${window.location.hostname}:8000/api/v1/me`, {
      credentials: 'include',
      headers: { Accept: 'application/json' },
    })
    return { status: response.status, body: await response.json() }
  }) as { status: number; body: { data?: { role?: string; medical_center?: { slug?: string }; patient?: { id?: number | string } } } }
  expect(me.status).toBe(200)
  expect(me.body.data?.role).toBe('PACIENTE')
  expect(me.body.data?.medical_center?.slug).toBe(targetCenter.slug)
  expect(me.body.data?.patient?.id).toBeTruthy()
  console.log(`Cuenta existente ${patient.email}; registro duplicado HTTP ${duplicateResponse.status()}; ficha local ${me.body.data?.patient?.id} creada en ${targetCenter.name}.`)
})

test('Reserva · una fecha sin disponibilidad no permite avanzar ni enviar la cita', async ({ page }) => {
  const patient = makeFictionalPatient()
  await registerFictionalPatient(page, patient)
  await chooseBookingCatalog(page)
  await findDateWithoutAvailability(page, 1, 21)
  await expect(page.getByText('No hay horas disponibles para este día.')).toBeVisible()
  await expect(page.getByRole('status')).toBeHidden()

  let appointmentPostSent = false
  page.on('request', (request) => {
    if (new URL(request.url()).pathname.endsWith('/api/v1/appointments') && request.method() === 'POST') {
      appointmentPostSent = true
    }
  })
  await saveEvidence(page, '09_reserva_sin_horarios_antes_de_continuar.png')
  await page.getByRole('button', { name: 'Continuar' }).click()
  await page.waitForTimeout(500)
  const alert = page.getByRole('alert')
  if (await alert.isVisible().catch(() => false)) {
    console.log(`Al pulsar Continuar sin horario, la aplicación mostró: ${await alert.innerText()}`)
  }
  await expect(page.getByRole('heading', { name: 'Elige fecha y horario' })).toBeVisible()
  expect(appointmentPostSent).toBe(false)
  await saveEvidence(page, '10_reserva_sin_horario_despues_de_continuar.png')
})

test('Reserva · reprogramar mantiene la cita y registra el cambio en el historial', async ({ page }) => {
  const patient = makeFictionalPatient()
  await registerFictionalPatient(page, patient)
  const original = await createAppointment(page)
  await page.goto(`${centerBase}/citas`)
  const appointmentRow = page.getByRole('row').filter({ has: page.locator(`a[href="${centerBase}/citas/${original.id}"]`) })
  await expect(appointmentRow).toBeVisible()
  await saveEvidence(page, '11_cita_original_antes_de_reprogramar.png')
  await appointmentRow.getByRole('link', { name: 'Reprogramar' }).click()
  await expect(page.getByRole('heading', { name: 'Reprogramar cita' })).toBeVisible()

  const { date, times } = await findDateWithAvailability(page, 30, 75, 'Nueva fecha')
  expect(date).not.toBe(original.date)
  await times.first().click()
  await saveEvidence(page, '12_reprogramacion_formulario_completado.png')
  const reschedule = page.waitForResponse((response) =>
    new URL(response.url()).pathname.endsWith(`/api/v1/appointments/${original.id}/reschedule`)
      && response.request().method() === 'PATCH',
  )
  await page.getByRole('button', { name: 'Guardar reprogramación' }).click()
  const response = await reschedule
  expect(response.status()).toBe(200)
  const body = await response.json() as { data?: { id?: number | string } }
  expect(String(body.data?.id)).toBe(original.id)

  const updatedRow = page.getByRole('row').filter({ has: page.locator(`a[href="${centerBase}/citas/${original.id}"]`) })
  await expect(updatedRow).toContainText(`${date.slice(8, 10)}-${date.slice(5, 7)}-${date.slice(0, 4)}`)
  await saveEvidence(page, '13_reprogramacion_guardada.png')
  await updatedRow.getByRole('link', { name: 'Historial' }).click()
  await expect(page.getByText('REPROGRAMACION')).toBeVisible()
  await expect(page.getByText(new RegExp(`${original.date}.*${date}`))).toBeVisible()
  await saveEvidence(page, '14_historial_reprogramacion.png')
})
