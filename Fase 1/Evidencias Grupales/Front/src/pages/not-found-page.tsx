import { Link } from 'react-router-dom'
import { Button } from '@/components/ui/button'
export function NotFoundPage() { return <div className="grid min-h-[60vh] place-items-center text-center"><div><p className="text-7xl font-bold text-primary/25">404</p><h1 className="mt-3 text-2xl font-bold">Página no encontrada</h1><p className="my-5 text-muted-foreground">La sección que buscas no existe o fue movida.</p><Button asChild><Link to="/">Volver al inicio</Link></Button></div></div> }
