<?php

namespace Database\Seeders;

use App\Models\Spg;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SpgSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Master SPGs matching the company's official roster.
     */
    public function run(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF;');
        Spg::truncate();
        DB::statement('PRAGMA foreign_keys = ON;');

        $spgNames = [
            'AINI',
            'MEGA',
            'MEGHA',
            'ZILDA',
            'DWI',
            'SHELA',
            'DINNI',
            'MAY',
            'DESI',
            'DWIE',
            'IDA',
            'LINA',
            'JIHAN',
            'KARTIKA',
            'AJENG',
            'PITRI',
            'FEBRI',
            'TINA',
            'PIPIT',
            'UMAR',
            'NADILA',
            'WAHYU',
            'APRIL',
            'FITRI',
            'ALIF',
            'NOVI',
            'ANGEL',
            'NOFITA',
            'TRI',
            'IIN',
            'NIKMA',
            'YANI',
            'REFI',
            'NANA',
            'EKA',
            'ROMLAH',
            'YUYUN',
            'PUJI',
            'SARI',
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
