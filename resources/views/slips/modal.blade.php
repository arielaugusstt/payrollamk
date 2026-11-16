<div class="bg-white p-4 rounded-2xl border border-slate-200 text-xs font-sans max-w-md mx-auto shadow-xs print:shadow-none print:border-none">
    
    <!-- Slip Header -->
    <div class="text-center pb-3 border-b-2 border-slate-900 mb-3">
        <h2 class="text-base font-extrabold text-slate-900 tracking-wider">SLIP RINCIAN GAJI SPG</h2>
        <div class="flex items-center justify-center gap-2 mt-1 font-bold text-orange-600 text-xs">
            <span>PERIODE:</span>
            <span class="px-2 py-0.5 bg-orange-100 rounded text-orange-800 font-mono">{{ $periodHeader }}</span>
        </div>
        <p class="text-[10px] text-slate-400 mt-1">Multi-Outlet Rotation: Tarif Uang Makan Harian Sesuai Lokasi Kerja</p>
    </div>

    <!-- SPG Identity -->
    <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-200 mb-3 flex items-center justify-between text-xs">
        <div>
            <p class="text-slate-400 text-[10px] uppercase font-bold">Nama SPG</p>
            <p class="font-extrabold text-slate-900 text-sm">{{ $record->spg->spg_name }}</p>
        </div>
        <div class="text-right">
            <p class="text-slate-400 text-[10px] uppercase font-bold">Ref. Gaji Pokok (Dominan)</p>
            <p class="font-bold text-slate-800 bg-white px-2 py-0.5 rounded border border-slate-200 inline-block font-mono">
                {{ $record->referenceOutlet ? $record->referenceOutlet->outlet_code : ($record->assignedOutlet ? $record->assignedOutlet->outlet_code : '-') }}
            </p>
        </div>
    </div>

    <!-- Outlets Visited Badges -->
    @if ($record->outlets_summary && is_array($record->outlets_summary))
        <div class="mb-3 px-2.5 py-1.5 bg-slate-50 rounded-lg border border-slate-200 flex items-center justify-between text-[11px]">
            <span class="text-slate-500 font-medium">Lokasi Kerja:</span>
            <div class="flex flex-wrap gap-1">
                @foreach ($record->outlets_summary as $os)
                    <span class="px-1.5 py-0.5 rounded bg-white border border-slate-200 font-mono font-bold text-[10px] text-slate-700">
                        {{ $os['code'] }} ({{ $os['days'] }} hari)
                    </span>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Vertical Date Table (21 down to 20) -->
    <div class="border border-slate-200 rounded-xl overflow-hidden mb-3">
        <table class="w-full text-[11px] text-left">
            <thead class="bg-slate-900 text-white font-bold text-center">
                <tr>
                    <th class="py-1 px-2 border-r border-slate-800 w-10">TGL</th>
                    <th class="py-1 px-2 border-r border-slate-800 w-20">STATUS</th>
                    <th class="py-1 px-2 border-r border-slate-800">OUTLET KERJA</th>
                    <th class="py-1 px-2 text-right">UANG MAKAN</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-mono">
                @foreach ($dailyLogs as $log)
                    @php
                        $ymd = $log->date->format('Y-m-d');
                        $mModel = $multipliersMap->get($ymd);
                        $mVal = $mModel ? $mModel->multiplier : 1;
                        $isBesar = $mVal > 1;
                        $isPresent = $log->is_present;
                        $outletCode = $isPresent ? ($log->outlet_code ?: ($log->chosenOutlet ? $log->chosenOutlet->outlet_code : ($log->raw_cell_value ?: 'OK'))) : '-';
                    @endphp
                    <tr class="{{ $isBesar ? 'bg-orange-50/40 font-semibold' : '' }}">
                        <!-- Date -->
                        <td class="py-1 px-2 text-center text-slate-700 border-r border-slate-100">
                            {{ $log->date->format('d') }}
                        </td>

                        <!-- Status Badge (BESAR on 2x/3x) -->
                        <td class="py-1 px-2 text-center border-r border-slate-100">
                            @if ($isBesar)
                                <span class="px-1.5 py-0.2 rounded text-[9px] font-extrabold bg-orange-600 text-white">
                                    BESAR ({{ $mVal }}x)
                                </span>
                            @elseif ($isPresent)
                                <span class="text-blue-700 text-[10px]">Normal</span>
                            @else
                                <span class="text-slate-300 text-[10px]">OFF</span>
                            @endif
                        </td>

                        <!-- Exact Outlet Worked That Day -->
                        <td class="py-1 px-2 text-center text-slate-800 border-r border-slate-100">
                            @if ($isPresent)
                                <span class="font-bold text-slate-900 bg-slate-100 px-1.5 py-0.5 rounded text-[10px]">
                                    {{ $outletCode }}
                                </span>
                            @else
                                <span class="text-slate-300">-</span>
                            @endif
                        </td>

                        <!-- Daily Meal Allowance according to that outlet's rate -->
                        <td class="py-1 px-2 text-right text-slate-800">
                            {{ $isPresent ? number_format($log->daily_uang_makan_utama, 0, ',', '.') : '-' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Summary Box -->
    <div class="bg-slate-50 p-3 rounded-xl border border-slate-200 space-y-1.5 text-xs font-mono">
        <div class="flex items-center justify-between text-slate-700">
            <span>TOTAL HARI MASUK:</span>
            <span class="font-bold">{{ $record->total_hari_masuk }} Hari ({{ $record->attendance_percentage }}%)</span>
        </div>
        <div class="flex items-center justify-between text-slate-700">
            <span>TOTAL UANG MAKAN:</span>
            <span class="font-bold">Rp {{ number_format($record->uang_makan_utama + $record->uang_makan_tambahan, 0, ',', '.') }}</span>
        </div>
        <div class="flex items-center justify-between text-slate-700">
            <span>GAJI POKOK (REF: {{ $record->referenceOutlet ? $record->referenceOutlet->outlet_code : '-' }}):</span>
            <span class="font-bold">
                Rp {{ number_format($record->base_salary_final, 0, ',', '.') }}
                @if ($record->policy_100_percent_override)
                    <span class="text-[9px] text-amber-700 font-sans font-semibold">(Kebijakan 100%)</span>
                @endif
            </span>
        </div>
        <div class="flex items-center justify-between text-slate-700">
            <span>UANG PULSA:</span>
            <span class="font-bold">Rp {{ number_format($record->uang_pulsa, 0, ',', '.') }}</span>
        </div>
        @if ($record->uang_tambahan > 0)
            <div class="flex items-center justify-between text-slate-700">
                <span>UANG TAMBAHAN:</span>
                <span class="font-bold">Rp {{ number_format($record->uang_tambahan, 0, ',', '.') }}</span>
            </div>
        @endif

        <!-- Bold Red Grand Total -->
        <div class="pt-2 border-t-2 border-slate-900 flex items-center justify-between text-sm font-sans font-extrabold text-red-600">
            <span>GRAND TOTAL:</span>
            <span class="text-base text-red-600">
                Rp {{ number_format($record->total_salary, 0, ',', '.') }}
            </span>
        </div>
    </div>

    <div class="mt-3 pt-2 border-t border-slate-200 text-center text-[10px] text-slate-400">
        Dicetak pada: {{ now()->format('d/m/Y H:i') }} &bull; Sistem Penggajian Kucing Oren
    </div>

</div>
