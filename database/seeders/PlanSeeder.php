<?php

namespace Database\Seeders;

use App\Enums\PlanInterval;
use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        Plan::query()->updateOrCreate(
            ['slug' => 'basic'],
            [
                'name' => 'Basic',
                'description' => 'Plano básico mensal.',
                'price' => 29.90,
                'currency' => 'BRL',
                'interval' => PlanInterval::Monthly,
                'interval_count' => 1,
                'trial_days' => 0,
                'is_active' => true,
            ],
        );

        Plan::query()->updateOrCreate(
            ['slug' => 'pro-mensal'],
            [
                'name' => 'Pro Mensal',
                'description' => 'Plano profissional com trial de 7 dias.',
                'price' => 99.90,
                'currency' => 'BRL',
                'interval' => PlanInterval::Monthly,
                'interval_count' => 1,
                'trial_days' => 7,
                'is_active' => true,
            ],
        );

        Plan::query()->updateOrCreate(
            ['slug' => 'pro-anual'],
            [
                'name' => 'Pro Anual',
                'description' => 'Plano profissional anual com desconto.',
                'price' => 999.90,
                'currency' => 'BRL',
                'interval' => PlanInterval::Yearly,
                'interval_count' => 1,
                'trial_days' => 14,
                'is_active' => true,
            ],
        );
    }
}
