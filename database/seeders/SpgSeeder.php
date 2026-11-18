<?php

namespace Database\Seeders;

use App\Models\Spg;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class SpgSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Exact 23 Distinct Master SPGs.
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        Spg::truncate();
        Schema::enableForeignKeyConstraints();

        $spgNames = [
            'PITRI',
            'FITRI',
            'MEGA',
            'MEGHA',
            'DWI',
            'DWIE',
            'JIHAN',
            'TRI',
            'YANI',
            'TINA',
            'ZILDA',
            'NADILA',
            'WAHYU',
            'DINNI',
            'LINA',
            'UMAR',
            'SHELA',
            'IDA',
            'IIN',
            'NANA',
            'NOFITA',
            'KARTIKA',
            'PUJI',
        ];

        foreach ($spgNames as $name) {
            Spg::create([
                'spg_name' => $name,
                'default_outlet_id' => null,
                'assigned_outlet_id' => null,
                'is_active' => true,
                'aliases' => [strtolower($name)],
            ]);
        }
    }
}
