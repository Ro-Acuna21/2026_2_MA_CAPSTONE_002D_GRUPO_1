import { ReportsPage } from '@/pages/reports-page'

export function ClinicFinancePage({ reports = false }: { reports?: boolean }) {
  return <ReportsPage commercial={reports} prices={!reports} />
}
