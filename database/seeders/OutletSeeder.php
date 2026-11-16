<?php

namespace Database\Seeders;

use App\Models\Outlet;
use App\Models\OutletAlias;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OutletSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 33 Main Regular Outlets (NO PSBB variants).
     */
    public function run(): void
    {
        // Truncate outlets and aliases safely
        DB::statement('PRAGMA foreign_keys = OFF;');
        OutletAlias::truncate();
        Outlet::truncate();
        DB::statement('PRAGMA foreign_keys = ON;');

        $outletsData = [
            ['code' => 'R-1', 'name' => 'Royal Plaza 1', 'utama' => 28250, 'pokok' => 350000],
            ['code' => 'R-2', 'name' => 'Royal Plaza 2', 'utama' => 28250, 'pokok' => 350000],
            ['code' => 'R-4', 'name' => 'Royal Plaza 4', 'utama' => 36750, 'pokok' => 350000],
            ['code' => 'R-5', 'name' => 'Royal Plaza 5', 'utama' => 36750, 'pokok' => 350000],
            ['code' => 'PA-1', 'name' => 'Pakuwon Mall 1', 'utama' => 21500, 'pokok' => 325000],
            ['code' => 'PA-2', 'name' => 'Pakuwon Mall 2', 'utama' => 30000, 'pokok' => 325000],
            ['code' => 'PA-3', 'name' => 'Pakuwon Mall 3', 'utama' => 30000, 'pokok' => 325000],
            ['code' => 'ITC', 'name' => 'ITC Mega Grosir', 'utama' => 45713, 'pokok' => 350000],
            ['code' => 'ITC-UG', 'name' => 'ITC Mega Grosir UG', 'utama' => 30000, 'pokok' => 350000],
            ['code' => 'ITC-FC1', 'name' => 'ITC Food Court 1', 'utama' => 33750, 'pokok' => 350000],
            ['code' => 'ITC-FC2', 'name' => 'ITC Food Court 2', 'utama' => 30000, 'pokok' => 350000],
            ['code' => 'ITC-PTT', 'name' => 'ITC PTT', 'utama' => 24500, 'pokok' => 350000],
            ['code' => 'TP', 'name' => 'Tunjungan Plaza', 'utama' => 30000, 'pokok' => 350000],
            ['code' => 'TP-1', 'name' => 'Tunjungan Plaza 1', 'utama' => 45000, 'pokok' => 350000],
            ['code' => 'TP-2', 'name' => 'Tunjungan Plaza 2', 'utama' => 30000, 'pokok' => 350000],
            ['code' => 'TP-3', 'name' => 'Tunjungan Plaza 3', 'utama' => 30000, 'pokok' => 350000],
            ['code' => 'BG-LL', 'name' => 'BG Junction LL', 'utama' => 41063, 'pokok' => 350000],
            ['code' => 'BG-L2', 'name' => 'BG Junction L2', 'utama' => 41063, 'pokok' => 350000],
            ['code' => 'BG-LG', 'name' => 'BG Junction LG', 'utama' => 41063, 'pokok' => 350000],
            ['code' => 'BG-GL', 'name' => 'BG Junction GL', 'utama' => 41063, 'pokok' => 325000],
            ['code' => 'LIPPO', 'name' => 'Lippo Plaza Sidoarjo', 'utama' => 36000, 'pokok' => 350000],
            ['code' => 'CT-1', 'name' => 'City of Tomorrow 1', 'utama' => 36750, 'pokok' => 350000],
            ['code' => 'CT-2', 'name' => 'City of Tomorrow 2', 'utama' => 24500, 'pokok' => 350000],
            ['code' => 'SNCT', 'name' => 'Suncity Mall Sidoarjo', 'utama' => 36000, 'pokok' => 350000],
            ['code' => 'RMY', 'name' => 'Ramayana', 'utama' => 26500, 'pokok' => 350000],
            ['code' => 'KAZA', 'name' => 'Kaza City Mall', 'utama' => 30000, 'pokok' => 350000],
            ['code' => 'GNT', 'name' => 'Grand City Mall', 'utama' => 42375, 'pokok' => 350000],
            ['code' => 'WTC', 'name' => 'World Trade Center', 'utama' => 30000, 'pokok' => 350000],
            ['code' => 'CWS', 'name' => 'Ciputra World Surabaya', 'utama' => 39750, 'pokok' => 350000],
            ['code' => 'PTC', 'name' => 'Pakuwon Trade Center', 'utama' => 45000, 'pokok' => 350000],
            ['code' => 'DTC', 'name' => 'Darmo Trade Center', 'utama' => 31125, 'pokok' => 350000],
            ['code' => 'GC', 'name' => 'Galaxy Mall', 'utama' => 36000, 'pokok' => 350000],
            ['code' => 'DELTA', 'name' => 'Plaza Delta Surabaya', 'utama' => 36000, 'pokok' => 350000],
        ];

        $outlets = [];
        foreach ($outletsData as $item) {
            $outlets[$item['code']] = Outlet::create([
                'outlet_code' => $item['code'],
                'outlet_name' => $item['name'],
                'uang_makan_utama' => $item['utama'],
                'gaji_pokok' => $item['pokok'],
                'uang_makan_tambahan' => 0,
                'uang_pulsa' => 10000,
                'is_active' => true,
            ]);
        }

        // Pre-Seeded Outlet Aliases
        $aliasesData = [
            ['code' => 'DELTA', 'alias' => 'delta'],
            ['code' => 'DELTA', 'alias' => 'ELTA'],
            ['code' => 'PTC', 'alias' => 'ptc'],
            ['code' => 'LIPPO', 'alias' => 'lippo'],
            ['code' => 'LIPPO', 'alias' => 'Lippo'],
            ['code' => 'LIPPO', 'alias' => 'lip'],
            ['code' => 'WTC', 'alias' => 'WT'],
            ['code' => 'WTC', 'alias' => 'wtc'],
            ['code' => 'KAZA', 'alias' => 'KA'],
            ['code' => 'KAZA', 'alias' => 'kaza'],
            ['code' => 'SNCT', 'alias' => 'suncity'],
            ['code' => 'SNCT', 'alias' => 'SUNCITY'],
            ['code' => 'GC', 'alias' => 'gc'],
            ['code' => 'GC', 'alias' => 'GC'],
            ['code' => 'GNT', 'alias' => 'giant'],
            ['code' => 'GNT', 'alias' => 'GIANT'],
            ['code' => 'GNT', 'alias' => 'grandcity'],
            ['code' => 'GNT', 'alias' => 'GRANDCITY'],
            ['code' => 'CWS', 'alias' => 'ciputra'],
            ['code' => 'CWS', 'alias' => 'CIPUTRA'],
            ['code' => 'CT-1', 'alias' => 'cito'],
            ['code' => 'CT-1', 'alias' => 'CITO'],
            ['code' => 'RMY', 'alias' => 'marina'],
            ['code' => 'RMY', 'alias' => 'MARINA'],
            ['code' => 'PA-1', 'alias' => 'pa1'],
            ['code' => 'PA-1', 'alias' => 'PA1'],
            ['code' => 'TP-1', 'alias' => 'tp1'],
            ['code' => 'TP-2', 'alias' => 'tp2'],
            ['code' => 'TP-3', 'alias' => 'tp3'],
            ['code' => 'R-1', 'alias' => 'r1'],
            ['code' => 'R-2', 'alias' => 'r2'],
            ['code' => 'R-4', 'alias' => 'r4'],
        ];

        foreach ($aliasesData as $item) {
            if (isset($outlets[$item['code']])) {
                OutletAlias::create([
                    'outlet_id' => $outlets[$item['code']]->id,
                    'alias' => $item['alias'],
                ]);
            }
        }
    }
}
