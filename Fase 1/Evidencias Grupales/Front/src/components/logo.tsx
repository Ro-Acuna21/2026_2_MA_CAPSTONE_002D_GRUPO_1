import { Cross } from 'lucide-react'
import { cn } from '@/lib/utils'

export function Logo({ light = false, compact = false }: { light?: boolean; compact?: boolean }) {
  return <div className={cn('flex items-center gap-3 font-medium', light ? 'text-white' : 'text-foreground')}><span className="grid size-10 place-items-center rounded-xl bg-emerald-100 text-primary"><Cross className="size-5" strokeWidth={3} /></span>{!compact && <span className="leading-tight">Clínica<br /><strong>Horizonte</strong></span>}</div>
}
