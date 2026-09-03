export function PageHeading({ eyebrow = 'Gestión administrativa', title, description, action }: { eyebrow?: string; title: string; description?: string; action?: React.ReactNode }) {
  return <header className="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p className="mb-1 text-[11px] font-bold uppercase tracking-[.16em] text-primary">{eyebrow}</p><h1 className="text-2xl font-bold tracking-tight sm:text-3xl">{title}</h1>{description && <p className="mt-2 text-sm text-muted-foreground">{description}</p>}</div>{action}</header>
}
