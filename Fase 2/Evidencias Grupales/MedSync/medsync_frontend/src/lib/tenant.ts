import { useParams } from 'react-router-dom'

export const centerBase = (slug: string) => `/centro/${slug}`

export function useCenterPath() {
  const { centerSlug = 'clinica-horizonte' } = useParams()
  return (path = '') => `${centerBase(centerSlug)}${path === '/' ? '' : path.startsWith('/') ? path : `/${path}`}`
}
