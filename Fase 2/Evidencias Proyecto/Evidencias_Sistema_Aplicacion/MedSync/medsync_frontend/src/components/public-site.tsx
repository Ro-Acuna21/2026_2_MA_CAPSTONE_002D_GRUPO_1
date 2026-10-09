import { Activity, ArrowRight, Menu, X } from "lucide-react";
import type { ComponentProps } from "react";
import { useState } from "react";
import { Link, useLocation, useNavigate } from "react-router-dom";
import { Button } from "@/components/ui/button";

const navItems = [["Producto", "producto"], ["Cómo funciona", "como-funciona"], ["Planes", "planes"]];

function scrollToPublicSection(sectionId: string) {
  document.getElementById(sectionId)?.scrollIntoView({ behavior: "smooth", block: "start" });
}

export function PublicSectionLink({ sectionId, children, ...props }: ComponentProps<"a"> & { sectionId: string }) {
  const location = useLocation();
  const navigate = useNavigate();
  const hash = `#${sectionId}`;

  return <a {...props} href={`/${hash}`} onClick={(event) => {
    props.onClick?.(event);
    if (event.defaultPrevented) return;
    event.preventDefault();
    if (location.pathname === "/" && location.hash === hash) {
      scrollToPublicSection(sectionId);
      return;
    }
    navigate({ pathname: "/", hash });
  }}>{children}</a>;
}

export function MediSyncMark({ inverse = false }: { inverse?: boolean }) {
  return <Link className={`group inline-flex items-center gap-2.5 ${inverse ? "text-white" : "text-foreground"}`} to="/"><span className={`grid size-9 place-items-center rounded-xl transition-transform group-hover:scale-105 ${inverse ? "bg-white/15 text-white" : "bg-primary text-primary-foreground"}`}><Activity className="size-5" strokeWidth={2.6} /></span><span className="text-lg font-bold tracking-tight">Medi<span className={inverse ? "text-emerald-200" : "text-primary"}>Sync</span></span></Link>;
}

export function PublicNavbar() {
  const [isOpen, setIsOpen] = useState(false);
  const close = () => setIsOpen(false);
  return <header className="sticky top-0 z-50 border-b border-border/60 bg-background/85 backdrop-blur-xl"><div className="mx-auto flex h-[72px] max-w-7xl items-center justify-between px-5 lg:px-8"><MediSyncMark /><nav className="hidden items-center gap-7 lg:flex" aria-label="Navegación pública">{navItems.map(([label, sectionId]) => <PublicSectionLink className="text-sm font-medium text-muted-foreground transition-colors hover:text-primary focus-visible:outline-none focus-visible:text-primary" key={label} sectionId={sectionId}>{label}</PublicSectionLink>)}<Link className="text-sm font-medium text-muted-foreground transition-colors hover:text-primary focus-visible:outline-none focus-visible:text-primary" to="/centros">Centros asociados</Link></nav><div className="hidden items-center gap-2 sm:flex"><Button asChild variant="ghost"><Link to="/ingresar">Ingresar</Link></Button><Button asChild><Link to="/centros">Conocer MediSync <ArrowRight className="size-4" /></Link></Button></div><Button aria-expanded={isOpen} aria-label="Abrir navegación" className="sm:hidden" onClick={() => setIsOpen((open) => !open)} size="icon" variant="ghost">{isOpen ? <X className="size-5" /> : <Menu className="size-5" />}</Button></div>{isOpen && <div className="border-t border-border bg-background px-5 py-4 sm:hidden"><nav className="flex flex-col gap-1" aria-label="Navegación pública móvil">{navItems.map(([label, sectionId]) => <PublicSectionLink className="rounded-lg px-3 py-2.5 text-sm font-medium hover:bg-accent" key={label} onClick={close} sectionId={sectionId}>{label}</PublicSectionLink>)}<Link className="rounded-lg px-3 py-2.5 text-sm font-medium hover:bg-accent" onClick={close} to="/centros">Centros asociados</Link><Link className="mt-2 rounded-lg bg-primary px-3 py-2.5 text-sm font-semibold text-primary-foreground" onClick={close} to="/ingresar">Ingresar</Link></nav></div>}</header>;
}

export function PublicFooter() {
  return <footer className="bg-[#103d42] text-white"><div className="mx-auto grid max-w-7xl gap-10 px-5 py-12 sm:grid-cols-2 lg:grid-cols-[1.5fr_1fr_1fr] lg:px-8"><div><MediSyncMark inverse /><p className="mt-4 max-w-sm text-sm leading-6 text-emerald-50/70">Tecnología simple y cercana para que los centros médicos se concentren en entregar una mejor atención.</p></div><div><h2 className="text-sm font-bold">Explora</h2><div className="mt-4 grid gap-3 text-sm text-emerald-50/70"><PublicSectionLink className="hover:text-white" sectionId="producto">Producto</PublicSectionLink><Link className="hover:text-white" to="/centros">Centros asociados</Link><PublicSectionLink className="hover:text-white" sectionId="planes">Planes</PublicSectionLink></div></div><div><h2 className="text-sm font-bold">Acceso</h2><div className="mt-4 grid gap-3 text-sm text-emerald-50/70"><Link className="hover:text-white" to="/ingresar">Ingresar al sistema</Link><Link className="hover:text-white" to="/plataforma/acceso">Acceso de plataforma</Link></div></div></div><div className="mx-auto flex max-w-7xl flex-col gap-2 border-t border-white/10 px-5 py-5 text-xs text-emerald-50/55 sm:flex-row sm:items-center sm:justify-between lg:px-8"><span>© {new Date().getFullYear()} MediSync. Salud conectada.</span><span>Plataforma para centros médicos.</span></div></footer>;
}
