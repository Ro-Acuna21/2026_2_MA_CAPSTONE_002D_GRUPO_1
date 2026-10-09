import type { AppointmentStatus } from '@/domain/types'
import { statusNames } from '@/domain/types'
import { cn } from '@/lib/utils'

const styles: Record<AppointmentStatus, string> = {
  PENDIENTE: 'bg-amber-100 text-amber-800', CONFIRMADA: 'bg-blue-100 text-blue-800', ATENDIDA: 'bg-emerald-100 text-emerald-800', CANCELADA: 'bg-rose-100 text-rose-800', NO_SHOW: 'bg-violet-100 text-violet-800',
}
export function StatusBadge({ status }: { status: AppointmentStatus }) { return <span className={cn('inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide', styles[status])}>{statusNames[status]}</span> }
