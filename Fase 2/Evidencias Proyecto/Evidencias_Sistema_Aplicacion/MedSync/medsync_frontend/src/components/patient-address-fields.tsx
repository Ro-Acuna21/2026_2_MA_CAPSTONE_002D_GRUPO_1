import { useEffect, useMemo, useState } from 'react'
import { firstApiErrorMessage, territorialCatalogApi, type TerritorialRegion } from '@/services/http'
import { Field, Input, Select } from '@/components/ui/form-controls'

function normalizeName(value: string) {
  return value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es-CL').replace(/^region\s+(?:(?:de|del)\s+)?/, '').trim()
}

function regionForName(regions: TerritorialRegion[], name: string) {
  const normalized = normalizeName(name)
  return regions.find((region) => normalizeName(region.name) === normalized)
    ?? regions.find((region) => region.code === '13' && normalized === 'metropolitana')
}

export function PatientAddressFields({
  address = '',
  region = '',
  commune = '',
}: {
  address?: string | null
  region?: string | null
  commune?: string | null
}) {
  const [regions, setRegions] = useState<TerritorialRegion[]>([])
  const [selectedRegion, setSelectedRegion] = useState('')
  const [selectedCommune, setSelectedCommune] = useState('')
  const [addressLine, setAddressLine] = useState(address ?? '')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    let active = true
    territorialCatalogApi.list()
      .then(({ data }) => {
        if (active) setRegions(data)
      })
      .catch((failure: unknown) => {
        if (active) setError(firstApiErrorMessage(failure, 'No fue posible cargar las regiones y comunas.'))
      })
      .finally(() => { if (active) setLoading(false) })
    return () => { active = false }
  }, [])

  useEffect(() => {
    if (!regions.length || !region) return
    const matchingRegion = regionForName(regions, region)
    if (!matchingRegion) return
    setSelectedRegion(matchingRegion.code)
    const matchingCommune = matchingRegion.communes.find((item) => normalizeName(item.name) === normalizeName(commune ?? ''))
    setSelectedCommune(matchingCommune?.code ?? '')
  }, [regions, region, commune])

  const communes = useMemo(
    () => regions.find((item) => item.code === selectedRegion)?.communes ?? [],
    [regions, selectedRegion],
  )
  const hasAddressDetails = Boolean(addressLine.trim() || selectedRegion || selectedCommune)

  return <>
    <Field className="sm:col-span-2" label="Dirección (opcional)">
      <Input
        name="address"
        autoComplete="street-address"
        maxLength={200}
        placeholder="Calle y número"
        value={addressLine}
        required={hasAddressDetails}
        onChange={(event) => setAddressLine(event.target.value)}
      />
    </Field>
    <Field label="Región (opcional)">
      <Select
        name="region_code"
        value={selectedRegion}
        required={hasAddressDetails}
        disabled={loading || Boolean(error)}
        onChange={(event) => {
          setSelectedRegion(event.target.value)
          setSelectedCommune('')
        }}
      >
        <option value="">Seleccionar región</option>
        {regions.map((item) => <option key={item.code} value={item.code}>{item.name}</option>)}
      </Select>
    </Field>
    <Field label="Comuna (opcional)">
      <Select
        name="commune_code"
        value={selectedCommune}
        required={hasAddressDetails}
        disabled={loading || Boolean(error) || !selectedRegion}
        onChange={(event) => setSelectedCommune(event.target.value)}
      >
        <option value="">{selectedRegion ? 'Seleccionar comuna' : 'Primero selecciona una región'}</option>
        {communes.map((item) => <option key={item.code} value={item.code}>{item.name}</option>)}
      </Select>
    </Field>
    <p className="col-span-full text-xs text-muted-foreground">Puedes dejar toda la dirección vacía. Si agregas dirección, completa también región y comuna.</p>
    {loading && <p className="col-span-full text-xs text-muted-foreground" role="status">Cargando catálogo de regiones y comunas…</p>}
    {error && <p className="col-span-full text-xs text-rose-700" role="alert">{error}</p>}
  </>
}
