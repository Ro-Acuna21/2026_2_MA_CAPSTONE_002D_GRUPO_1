/* eslint-disable react-refresh/only-export-components */
import { Field, Input, Select } from './ui/form-controls'
import { healthInsurances } from '@/domain/validation'
import { dateFromToday } from '@/lib/utils'

export function PatientFields() {
  return <><Field label="Nombres"><Input name="firstName" autoComplete="given-name" required minLength={2} maxLength={80} /></Field><Field label="Apellidos"><Input name="lastName" autoComplete="family-name" required minLength={2} maxLength={80} /></Field><Field label="RUT"><Input name="rut" placeholder="12.345.678-5" required maxLength={14} /></Field><Field label="Fecha de nacimiento"><Input name="birthDate" type="date" min="1900-01-01" max={dateFromToday()} required /></Field><Field label="Correo electrónico"><Input name="email" type="email" autoComplete="email" required maxLength={150} /></Field><Field label="Teléfono"><Input name="phone" type="tel" autoComplete="tel" placeholder="+56 9 1234 5678" required maxLength={20} /></Field><Field label="Previsión de salud"><Select name="healthInsurance" defaultValue="" required><option value="" disabled>Seleccionar previsión</option>{healthInsurances.map((name) => <option key={name}>{name}</option>)}</Select></Field><Field label="Seguro complementario (opcional)"><Input name="medicalInsurance" placeholder="Compañía o nombre del seguro" maxLength={100} /></Field><Field label="Dirección (opcional)"><Input name="address" autoComplete="street-address" maxLength={200} /></Field></>
}

export function ConsentField() {
  return <label className="col-span-full flex items-start gap-2 text-sm"><input name="consent" type="checkbox" required className="mt-1" /> Acepto el uso de estos datos para gestionar mi cuenta y atención en este centro médico.</label>
}
export const patientFromForm = (values: Record<string, string>) => ({ name: `${values.firstName.trim()} ${values.lastName.trim()}`, rut: values.rut, birthDate: values.birthDate, email: values.email, phone: values.phone, healthInsurance: values.healthInsurance, medicalInsurance: values.medicalInsurance, address: values.address, consent: values.consent === 'on' })
