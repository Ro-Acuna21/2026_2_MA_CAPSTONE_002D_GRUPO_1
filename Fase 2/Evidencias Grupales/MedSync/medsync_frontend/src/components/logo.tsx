import { Cross } from 'lucide-react'
import { cn } from '@/lib/utils'

export function Logo({ light = false, compact = false, name = 'Clínica Horizonte' }: { light?: boolean; compact?: boolean; name?: string }) {
  return <div className={cn('flex items-center gap-3 font-medium', light ? 'text-white' : 'text-foreground')}><span className="grid size-10 place-items-center rounded-xl bg-emerald-100 text-primary"><Cross className="size-5" strokeWidth={3} /></span>{!compact && <strong className="max-w-40 leading-tight">{name}</strong>}</div>
}
