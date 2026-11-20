// @vitest-environment jsdom
import { cleanup, fireEvent, render, screen } from '@testing-library/react'
import { afterEach, expect, it } from 'vitest'
import { Field } from '@/components/ui/form-controls'
import { RutInput } from './rut-input'

afterEach(cleanup)

it('calcula y completa el dígito verificador de un cuerpo numérico', () => {
  render(<Field label="RUT"><RutInput /></Field>)
  const input = screen.getByLabelText('RUT') as HTMLInputElement
  fireEvent.change(input, {target: {value: '12345678'}})
  expect(screen.getByText(/Dígito verificador calculado: 5/)).toBeTruthy()
  fireEvent.blur(input)
  expect(input.value).toBe('12345678-5')
})

it('conserva un RUT completo válido', () => {
  render(<Field label="RUT"><RutInput defaultValue="12345678-5" /></Field>)
  const input = screen.getByLabelText('RUT') as HTMLInputElement
  fireEvent.blur(input)
  expect(input.value).toBe('12345678-5')
})
