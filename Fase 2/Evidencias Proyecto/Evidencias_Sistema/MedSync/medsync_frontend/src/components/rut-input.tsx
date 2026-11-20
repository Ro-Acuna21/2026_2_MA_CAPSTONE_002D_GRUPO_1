import { useState } from 'react'
import { Input } from '@/components/ui/form-controls'
import { normalizeRut, rutDv, validRut } from '@/domain/validation'

/** Calculates a DV while typing; completes an unambiguous numeric body on blur. */
export function RutInput({defaultValue = '', readOnly = false}: {defaultValue?: string; readOnly?: boolean}) {
  const [value, setValue] = useState(defaultValue)
  const body = normalizeRut(value)
  const bodyOnly = /^\d{7,8}$/.test(body) && !value.includes('-') && !validRut(value)
  const suggestion = bodyOnly ? rutDv(body) : null
  return <span className="grid gap-1"><Input name="rut" value={value} readOnly={readOnly} required maxLength={14} placeholder="12.345.678-5" onChange={event => setValue(event.target.value)} onBlur={() => {if (suggestion && !readOnly) setValue(`${body}-${suggestion}`)}} />{suggestion && !readOnly && <small className="font-normal text-muted-foreground">Dígito verificador calculado: {suggestion}. Se completará al salir del campo.</small>}</span>
}
