<?php

namespace Database\Seeders;

use App\Models\Core\Plan;
use Illuminate\Database\Seeder;

class PlanCatalogSeeder extends Seeder
{
    public function run(): void
    {
        // Valores ya publicados en el catálogo de demostración anterior.
        // firstOrCreate evita sobrescribir precios editados desde plataforma.
        Plan::firstOrCreate(['code' => 'STARTER'], [
            'name' => 'Básico',
            'description' => 'Agenda, fichas y resultados clínicos.',
            'monthly_price_clp' => 49990,
            'professional_limit' => null,
            'user_limit' => null,
            'features' => ['Agenda y reservas', 'Fichas de pacientes', 'Resultados clínicos'],
            'is_active' => true,
        ]);
        Plan::firstOrCreate(['code' => 'PRO'], [
            'name' => 'Profesional',
            'description' => 'Operación clínica y reportes.',
            'monthly_price_clp' => 89990,
            'professional_limit' => null,
            'user_limit' => null,
            'features' => ['Agenda y reservas', 'Fichas de pacientes', 'Resultados clínicos', 'Reportes operacionales y comerciales'],
            'is_active' => true,
        ]);
    }
}
