import { describe, expect, it } from 'vitest'
import { businessDateAt } from './business-date'

describe('businessDateAt (America/Santiago)', () => {
  it.each([
    ['2026-11-22T23:59:00-03:00', '2026-11-22'],
    ['2026-11-22T21:00:00-03:00', '2026-11-22'],
    ['2026-11-22T22:00:00-03:00', '2026-11-22'],
    ['2026-11-23T00:00:00-03:00', '2026-11-23'],
    ['2026-11-23T00:01:00-03:00', '2026-11-23'],
    ['2026-12-31T23:59:00-03:00', '2026-12-31'],
    ['2027-01-01T00:00:00-03:00', '2027-01-01'],
    // Inicio del horario de verano: el reloj salta de 23:59 a 01:00.
    ['2026-09-06T03:59:00Z', '2026-09-05'],
    ['2026-09-06T04:00:00Z', '2026-09-06'],
    // Fin del horario de verano: la fecha cambia al llegar a medianoche local.
    ['2026-04-05T03:59:00Z', '2026-04-04'],
    ['2026-04-05T04:00:00Z', '2026-04-05'],
  ])('interpreta %s como fecha comercial %s', (instant, expected) => {
    expect(businessDateAt(new Date(instant))).toBe(expected)
  })
})
