@extends('layouts.app')

@section('content')
<div class="space-y-6" x-data="incentiveMatrixWorkspace({
    taskId: {{ $task->id }},
    csrfToken: '{{ csrf_token() }}',
    twinMode: '{{ $task->twin_outlet_mode ?: 'split' }}'
})">

    <!-- Top Navigation Bar & Action Triggers -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
        <div>
            <div class="flex items-center gap-3">
                <a href="{{ route('tasks.index') }}" class="p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-xl transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                </a>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded-full text-[11px] font-extrabold bg-purple-100 text-purple-700 border border-purple-200">
                            INSENTIF (OMZET)
                        </span>
                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold {{ $task->isFinished() ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                            {{ $task->status }}
                        </span>
                    </div>
                    <h1 class="text-xl font-black text-slate-900 mt-1">{{ $task->title }}</h1>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Periode: <span class="font-semibold text-slate-700">{{ $task->start_date->format('d/m/Y') }} s/d {{ $task->end_date->format('d/m/Y') }}</span>
                        ({{ count($dates) }} Hari Kerja &bull; 3D Matrix: Per Tanggal &rarr; Per Outlet &rarr; Per SPG)
                    </p>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <!-- Upload Excel Trigger -->
            <button @click="uploadModalOpen = true" 
                    class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold flex items-center gap-1.5 transition cursor-pointer">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
                Upload Excel (Presensi & Omzet)
            </button>

            <!-- Export to Excel (.xlsx) -->
            <a href="{{ route('export.incentive', $task->id) }}" 
               class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold shadow-xs flex items-center gap-1.5 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Export Excel (Matrix + Verifikasi)
            </a>

            <!-- Task State Toggle -->
            @if ($task->isFinished())
                <form action="{{ route('tasks.reopen', $task->id) }}" method="POST" onsubmit="return confirm('Buka kembali task ini untuk merevisi data?');">
                    @csrf
                    <button type="submit" class="px-3.5 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-xs font-semibold shadow-xs flex items-center gap-1.5 transition cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Re-open Task (Revisi)
                    </button>
                </form>
            @else
                <form action="{{ route('tasks.finish', $task->id) }}" method="POST" onsubmit="return confirm('Kunci dan selesaikan task ini? Task yang selesai akan mengalirkan datanya ke Executive Dashboard.');">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-md shadow-emerald-600/20 flex items-center gap-1.5 transition cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Finish Task (Selesai & Kunci)
                    </button>
                </form>
            @endif
        </div>
    </div>

    <!-- Mode Omzet Outlet Kembar (1-Click Toggle) -->
    <div class="bg-slate-900 text-white p-3.5 rounded-2xl shadow-sm flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
        <div class="flex items-center gap-3">
            <span class="w-8 h-8 rounded-xl bg-purple-500/20 text-purple-400 border border-purple-500/30 flex items-center justify-center font-bold text-sm">
                ⚙️
            </span>
            <div>
                <span class="font-bold text-white text-sm">Mode Omzet Outlet Kembar:</span>
                <span class="text-slate-400 block text-[11px]">Ketika terdapat 2 atau lebih SPG bertugas di outlet yang sama pada tanggal yang sama</span>
            </div>
        </div>
        <div class="flex items-center bg-slate-800 p-1 rounded-xl border border-slate-700">
            <button type="button" 
                    @click="setTwinMode('split')"
                    :class="currentTwinMode === 'split' ? 'bg-emerald-600 text-white font-bold shadow-xs' : 'text-slate-400 hover:text-white'"
                    class="px-3 py-1.5 rounded-lg text-xs transition cursor-pointer flex items-center gap-1.5">
                <span>➗ Bagi Rata SPG Jaga (Split)</span>
            </button>
            <button type="button" 
                    @click="setTwinMode('full')"
                    :class="currentTwinMode === 'full' ? 'bg-purple-600 text-white font-bold shadow-xs' : 'text-slate-400 hover:text-white'"
                    class="px-3 py-1.5 rounded-lg text-xs transition cursor-pointer flex items-center gap-1.5">
                <span>⭐ Full per SPG (100% Masing-masing)</span>
            </button>
        </div>
    </div>

    <!-- Incentive Formula Guide Banner -->
    <div class="bg-gradient-to-r from-emerald-900 to-teal-900 text-white p-3.5 rounded-2xl shadow-sm flex flex-col md:flex-row items-center justify-between gap-3 text-xs">
        <div class="flex items-center space-x-3">
            <span class="w-7 h-7 rounded-lg bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 flex items-center justify-center font-bold text-xs">
                💡
            </span>
            <div>
                <span class="font-bold text-white">Logika Bisnis Perhitungan Insentif:</span>
                <span class="text-emerald-200 ml-1">
                    Jika Total Omzet &gt; Rp 5.000.000 &rarr; <b>Tier 1:</b> 5 Juta &times; 1,25% (Rp 62.500) + <b>Tier 2:</b> Sisa Omzet &times; 2,5%.
                    Jika &le; Rp 5.000.000 &rarr; Flat 2,5%.
                </span>
            </div>
        </div>
        <div class="px-3 py-1 bg-emerald-950/60 rounded-xl border border-emerald-500/30 text-emerald-300 font-mono text-[11px] shrink-0">
            Auto-Sum: AMK + AMKOpi Aktif
        </div>
    </div>

    <!-- Workspace Tabs Navigation -->
    <div class="flex items-center gap-2 border-b border-slate-200 pb-2">
        <button type="button"
                @click="activeTab = 'spg_matrix'"
                :class="activeTab === 'spg_matrix' ? 'bg-emerald-600 text-white font-bold shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'"
                class="px-4 py-2 rounded-xl text-xs flex items-center gap-2 transition cursor-pointer">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            <span>Matriks Insentif SPG (Per SPG)</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-white/20 text-white font-mono">{{ $task->incentiveRecords->count() }}</span>
        </button>

        <button type="button"
                @click="activeTab = 'outlet_verification'"
                :class="activeTab === 'outlet_verification' ? 'bg-indigo-600 text-white font-bold shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'"
                class="px-4 py-2 rounded-xl text-xs flex items-center gap-2 transition cursor-pointer">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            <span>Verifikasi Outlet (Per Tanggal Per Outlet)</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-white/20 text-white font-mono">{{ count($verificationGrid) }}</span>
        </button>
    </div>

    <!-- TAB 1: Matriks Insentif SPG (Per SPG) -->
    <div x-show="activeTab === 'spg_matrix'" class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
        
        <!-- Table Legend -->
        <div class="px-4 py-2 bg-slate-50 border-b border-slate-200 flex flex-wrap items-center justify-between text-[11px] text-slate-500 gap-2">
            <div class="flex items-center gap-3">
                <span class="flex items-center gap-1">
                    <span class="w-2.5 h-2.5 rounded bg-emerald-100 border border-emerald-300"></span> Sel Menampilkan: <b>Kode Outlet</b> &amp; <b>Total Omzet Harian (AMK + AMKopi)</b>
                </span>
            </div>
            <div class="text-emerald-700 font-semibold">
                Freeze Panes: Kolom SPG &amp; Outlet Terkunci di Kiri &bull; Arahkan kursor pada sel untuk rincian AMK vs Kopi
            </div>
        </div>

        <!-- Horizontal Scrollable Container -->
        <div class="overflow-x-auto custom-scrollbar relative max-h-[72vh]">
            <table class="w-full text-xs text-left border-collapse whitespace-nowrap">
                
                <!-- Table Header (Sticky Top) -->
                <thead class="sticky top-0 z-30 bg-slate-900 text-white font-bold shadow-xs">
                    <tr>
                        <!-- Pinned Left 1: No -->
                        <th class="sticky left-0 z-40 bg-slate-900 px-3 py-3 w-10 text-center border-b border-r border-slate-800">
                            NO
                        </th>
                        <!-- Pinned Left 2: Nama SPG -->
                        <th class="sticky left-10 z-40 bg-slate-900 px-4 py-3 min-w-[150px] border-b border-r border-slate-800">
                            NAMA SPG
                        </th>
                        <!-- Pinned Left 3: Outlet Tetap -->
                        <th class="sticky left-[190px] z-40 bg-slate-900 px-3 py-3 min-w-[140px] text-center border-b border-r border-slate-700 shadow-[2px_0_5px_rgba(0,0,0,0.15)]">
                            OUTLETS DIKUNJUNGI
                        </th>

                        <!-- Horizontal Dates Columns -->
                        @foreach ($dates as $d)
                            <th class="px-2 py-2.5 text-center min-w-[70px] border-b border-r border-slate-800 {{ $d->isSunday() ? 'bg-orange-950 text-orange-200' : 'bg-slate-900' }}">
                                <div class="text-[11px] font-bold">{{ $d->format('d') }}</div>
                                <div class="text-[9px] font-normal text-slate-400">{{ substr($d->format('D'), 0, 2) }}</div>
                            </th>
                        @endforeach

                        <!-- Summary Header Columns -->
                        <th class="px-3 py-3 text-right bg-slate-800 text-slate-200 border-b border-r border-slate-700 min-w-[100px]">TOTAL AMK</th>
                        <th class="px-3 py-3 text-right bg-slate-800 text-slate-200 border-b border-r border-slate-700 min-w-[100px]">TOTAL KOPI</th>
                        <th class="px-4 py-3 text-right bg-amber-950 text-amber-300 font-extrabold border-b border-r border-slate-700 min-w-[120px]">TOTAL OMZET</th>
                        <th class="px-3 py-3 text-right bg-slate-800 text-slate-200 border-b border-r border-slate-700">TIER 1 (1.25%)</th>
                        <th class="px-3 py-3 text-right bg-slate-800 text-slate-200 border-b border-r border-slate-700">TIER 2 (2.5%)</th>
                        <th class="px-3 py-3 text-right bg-slate-800 text-slate-200 border-b border-r border-slate-700">INSENTIF MURNI</th>
                        <th class="px-3 py-3 text-right bg-slate-800 text-slate-200 border-b border-r border-slate-700">TAMBAHAN</th>
                        <th class="px-4 py-3 text-right bg-emerald-950 text-emerald-300 font-extrabold border-b border-slate-700 shadow-sm min-w-[130px]">
                            TOTAL INSENTIF (RP)
                        </th>
                    </tr>
                </thead>

                <!-- Table Body -->
                <tbody class="divide-y divide-slate-100">
                    @php $no = 1; @endphp
                    @foreach ($task->incentiveRecords as $rec)
                        @php
                            $logsMap = $rec->dailyLogs->keyBy(fn($l) => $l->date->format('Y-m-d'));
                        @endphp
                        <tr class="hover:bg-slate-50/80 transition-colors group" id="row-record-{{ $rec->id }}">
                            
                            <!-- Pinned Left 1: No -->
                            <td class="sticky left-0 z-20 bg-white group-hover:bg-slate-50 px-3 py-2 text-center text-slate-400 font-medium border-r border-slate-200">
                                {{ $no++ }}
                            </td>

                            <!-- Pinned Left 2: SPG Name -->
                            <td class="sticky left-10 z-20 bg-white group-hover:bg-slate-50 px-4 py-2 font-bold text-slate-900 border-r border-slate-200">
                                {{ $rec->spg->spg_name }}
                            </td>

                            <!-- Pinned Left 3: Outlet Code -->
                            <td class="sticky left-[190px] z-20 bg-white group-hover:bg-slate-50 px-3 py-2 text-center font-semibold text-slate-700 border-r border-slate-200 shadow-[2px_0_5px_rgba(0,0,0,0.04)]">
                                @if ($rec->outlets_summary && is_array($rec->outlets_summary) && count($rec->outlets_summary) > 0)
                                    <div class="flex flex-wrap gap-1 items-center justify-center">
                                        @foreach ($rec->outlets_summary as $os)
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-mono font-medium bg-slate-100 text-slate-800 border border-slate-200">
                                                {{ $os['code'] ?? '-' }} <span class="text-emerald-700 font-bold ml-0.5">{{ $os['days'] ?? 0 }}d</span>
                                            </span>
                                        @endforeach
                                    </div>
                                @else
                                    @php
                                        $outletCode = ($rec->referenceOutlet ?? $rec->assignedOutlet ?? $rec->spg?->defaultOutlet ?? $rec->spg?->assignedOutlet)?->outlet_code ?? '-';
                                    @endphp
                                    <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-800 text-[11px] font-mono">
                                        {{ $outletCode }}
                                    </span>
                                @endif
                            </td>

                            <!-- Date Cells (Shows: Outlet Code + Total Daily Omzet) -->
                            @foreach ($dates as $d)
                                @php
                                    $ymd = $d->format('Y-m-d');
                                    $log = $logsMap->get($ymd);
                                    $val = $log ? (float)$log->total_daily_omzet : 0;
                                    $outCode = $log ? ($log->outlet_code ?: '-') : '-';
                                    $amk = $log ? (float)$log->omzet_amk : 0;
                                    $kopi = $log ? (float)$log->omzet_amkopi : 0;
                                @endphp
                                <td class="px-1 py-1 text-center border-r border-slate-100">
                                    <div class="flex flex-col items-center justify-center p-1 rounded {{ $val > 0 ? 'bg-emerald-50/70 border border-emerald-100' : '' }}"
                                         title="Tanggal {{ $d->format('d/m') }} | Outlet: {{ $outCode }} | AMK: Rp {{ number_format($amk, 0, ',', '.') }} | Kopi: Rp {{ number_format($kopi, 0, ',', '.') }}">
                                        @if ($outCode !== '-')
                                            <span class="text-[9px] font-mono font-extrabold text-slate-700 bg-white/90 px-1 rounded shadow-2xs border border-slate-200">
                                                {{ $outCode }}
                                            </span>
                                        @endif
                                        <span class="font-mono text-[10px] {{ $val > 0 ? 'text-emerald-800 font-bold' : 'text-slate-300' }}">
                                            {{ $val > 0 ? number_format($val, 0, ',', '.') : '-' }}
                                        </span>
                                    </div>
                                </td>
                            @endforeach

                            <!-- Calculation Summary Columns -->
                            <td class="px-3 py-2 text-right text-slate-700 font-mono border-r border-slate-200" id="val-omzet-amk-{{ $rec->id }}">
                                {{ number_format($rec->total_omzet_amk, 0, ',', '.') }}
                            </td>

                            <td class="px-3 py-2 text-right text-slate-700 font-mono border-r border-slate-200" id="val-omzet-kopi-{{ $rec->id }}">
                                {{ number_format($rec->total_omzet_amkopi, 0, ',', '.') }}
                            </td>

                            <td class="px-3 py-2 text-right font-bold text-amber-700 font-mono bg-amber-50/30 border-r border-slate-200" id="val-total-omzet-{{ $rec->id }}">
                                {{ number_format($rec->total_omzet, 0, ',', '.') }}
                            </td>

                            <td class="px-3 py-2 text-right text-emerald-700 font-mono border-r border-slate-200" id="val-tier1-{{ $rec->id }}">
                                {{ number_format($rec->insentif_tier_1, 0, ',', '.') }}
                            </td>

                            <td class="px-3 py-2 text-right text-emerald-700 font-mono border-r border-slate-200" id="val-tier2-{{ $rec->id }}">
                                {{ number_format($rec->insentif_tier_2, 0, ',', '.') }}
                            </td>

                            <td class="px-3 py-2 text-right font-semibold text-slate-800 font-mono border-r border-slate-200" id="val-murni-{{ $rec->id }}">
                                {{ number_format($rec->insentif_murni, 0, ',', '.') }}
                            </td>

                            <!-- Manual Tambahan Input -->
                            <td class="px-2 py-1 text-right border-r border-slate-200">
                                @if ($task->isFinished())
                                    <span class="font-mono text-slate-700">{{ number_format($rec->uang_tambahan, 0, ',', '.') }}</span>
                                @else
                                    <input type="number" 
                                           value="{{ (int)$rec->uang_tambahan }}" 
                                           @change="updateManualAddition({{ $rec->id }}, $event.target.value)"
                                           class="w-18 text-right py-0.5 px-1 font-mono text-xs rounded border border-transparent hover:border-slate-300 focus:border-emerald-500 focus:bg-white focus:outline-none transition">
                                @endif
                            </td>

                            <!-- Total Insentif Column -->
                            <td class="px-4 py-2 text-right font-bold text-emerald-700 font-mono text-xs bg-emerald-50/30 shadow-xs" id="val-total-insentif-{{ $rec->id }}">
                                Rp {{ number_format($rec->total_insentif, 0, ',', '.') }}
                            </td>

                        </tr>
                    @endforeach
                </tbody>

                <!-- Table Footer (Grand Totals Sticky Bottom) -->
                <tfoot class="sticky bottom-0 z-30 bg-slate-900 text-white font-bold border-t-2 border-slate-700 shadow-lg">
                    <tr>
                        <td colspan="3" class="sticky left-0 z-40 bg-slate-900 px-4 py-3 text-right uppercase tracking-wider text-xs border-r border-slate-800 shadow-[2px_0_5px_rgba(0,0,0,0.15)]">
                            GRAND TOTAL
                        </td>
                        <td colspan="{{ count($dates) }}" class="bg-slate-900 border-r border-slate-800 text-center text-slate-400 font-mono text-[11px]">
                            &larr; {{ count($dates) }} Hari Kerja &bull; Perhitungan Otomatis Matriks &rarr;
                        </td>
                        <td class="px-3 py-3 text-right font-mono border-r border-slate-800">
                            {{ number_format($grandTotals['total_omzet_amk'], 0, ',', '.') }}
                        </td>
                        <td class="px-3 py-3 text-right font-mono border-r border-slate-800">
                            {{ number_format($grandTotals['total_omzet_amkopi'], 0, ',', '.') }}
                        </td>
                        <td class="px-3 py-3 text-right font-mono font-extrabold text-amber-400 border-r border-slate-800 bg-amber-950/40">
                            {{ number_format($grandTotals['total_omzet'], 0, ',', '.') }}
                        </td>
                        <td class="px-3 py-3 text-right font-mono border-r border-slate-800">
                            {{ number_format($grandTotals['insentif_tier_1'], 0, ',', '.') }}
                        </td>
                        <td class="px-3 py-3 text-right font-mono border-r border-slate-800">
                            {{ number_format($grandTotals['insentif_tier_2'], 0, ',', '.') }}
                        </td>
                        <td class="px-3 py-3 text-right font-mono border-r border-slate-800">
                            {{ number_format($grandTotals['insentif_murni'], 0, ',', '.') }}
                        </td>
                        <td class="px-3 py-3 text-right font-mono border-r border-slate-800">
                            {{ number_format($grandTotals['uang_tambahan'], 0, ',', '.') }}
                        </td>
                        <td class="px-4 py-3 text-right font-mono font-extrabold text-emerald-400 text-sm">
                            Rp {{ number_format($grandTotals['total_insentif'], 0, ',', '.') }}
                        </td>
                    </tr>
                </tfoot>

            </table>
        </div>

    </div>

    <!-- TAB 2: Verifikasi Outlet (Per Tanggal Per Outlet) -->
    <div x-show="activeTab === 'outlet_verification'" class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
        
        <!-- Table Legend -->
        <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-200 flex flex-wrap items-center justify-between text-[11px] gap-2">
            <div class="flex items-center gap-4 text-slate-600">
                <span class="flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded bg-emerald-100 border border-emerald-300"></span>
                    <b>Normal:</b> Omzet ada &amp; SPG bertugas
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded bg-amber-100 border border-amber-300"></span>
                    <b class="text-amber-700">⚠️ Anomali:</b> Omzet ada tapi tidak ada SPG di Presensi
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded bg-blue-100 border border-blue-300"></span>
                    <b class="text-blue-700">Info:</b> SPG jaga tapi omzet 0
                </span>
            </div>
            <div class="text-slate-500 font-medium">
                Menampilkan seluruh outlet aktif &bull; Membantu rekonsiliasi data penjualan harian
            </div>
        </div>

        <!-- Verification Horizontal Scrollable Grid -->
        <div class="overflow-x-auto custom-scrollbar relative max-h-[72vh]">
            <table class="w-full text-xs text-left border-collapse whitespace-nowrap">
                
                <!-- Table Header -->
                <thead class="sticky top-0 z-30 bg-slate-900 text-white font-bold shadow-xs">
                    <tr>
                        <th class="sticky left-0 z-40 bg-slate-900 px-3 py-3 w-10 text-center border-b border-r border-slate-800">
                            NO
                        </th>
                        <th class="sticky left-10 z-40 bg-slate-900 px-3 py-3 min-w-[100px] border-b border-r border-slate-800">
                            KODE OUTLET
                        </th>
                        <th class="sticky left-[140px] z-40 bg-slate-900 px-3 py-3 min-w-[180px] border-b border-r border-slate-700 shadow-[2px_0_5px_rgba(0,0,0,0.15)]">
                            NAMA OUTLET
                        </th>

                        <!-- Dates Columns -->
                        @foreach ($dates as $d)
                            <th class="px-2 py-2 text-center min-w-[110px] border-b border-r border-slate-800 {{ $d->isSunday() ? 'bg-orange-950 text-orange-200' : 'bg-slate-900' }}">
                                <div class="text-[11px] font-bold">{{ $d->format('d') }}</div>
                                <div class="text-[9px] font-normal text-slate-400">{{ substr($d->format('D'), 0, 2) }}</div>
                            </th>
                        @endforeach

                        <th class="px-4 py-3 text-right bg-emerald-950 text-emerald-300 font-extrabold border-b border-slate-700 min-w-[140px]">
                            TOTAL OMZET BULANAN
                        </th>
                    </tr>
                </thead>

                <!-- Table Body -->
                <tbody class="divide-y divide-slate-100">
                    @php $vNo = 1; @endphp
                    @foreach ($verificationGrid as $vRow)
                        @php
                            $outletTotalOmzet = 0;
                            foreach ($vRow['days'] as $dVal) {
                                $outletTotalOmzet += (float)($dVal['total_omzet'] ?? 0);
                            }
                        @endphp
                        <tr class="hover:bg-slate-50 transition-colors group">
                            
                            <!-- Pinned Left 1: No -->
                            <td class="sticky left-0 z-20 bg-white group-hover:bg-slate-50 px-3 py-2 text-center text-slate-400 font-medium border-r border-slate-200">
                                {{ $vNo++ }}
                            </td>

                            <!-- Pinned Left 2: Kode Outlet -->
                            <td class="sticky left-10 z-20 bg-white group-hover:bg-slate-50 px-3 py-2 font-mono font-bold text-slate-900 border-r border-slate-200">
                                <span class="px-2 py-0.5 rounded bg-slate-100 border border-slate-200 text-slate-800">
                                    {{ $vRow['outlet_code'] }}
                                </span>
                            </td>

                            <!-- Pinned Left 3: Nama Outlet -->
                            <td class="sticky left-[140px] z-20 bg-white group-hover:bg-slate-50 px-3 py-2 text-slate-700 font-medium border-r border-slate-200 shadow-[2px_0_5px_rgba(0,0,0,0.04)]">
                                {{ $vRow['outlet_name'] }}
                            </td>

                            <!-- Date Cells -->
                            @foreach ($dates as $d)
                                @php
                                    $ymd = $d->format('Y-m-d');
                                    $dayData = $vRow['days'][$ymd] ?? null;
                                    $status = $dayData['status'] ?? 'idle';
                                    $totalOmz = (float)($dayData['total_omzet'] ?? 0);
                                    $spgs = $dayData['spgs'] ?? [];
                                    $spgCount = count($spgs);
                                @endphp
                                <td class="px-1.5 py-1.5 border-r border-slate-100 text-center">
                                    @if ($status === 'ok')
                                        <div class="p-1 rounded bg-emerald-50/80 border border-emerald-200 text-left">
                                            <div class="font-mono font-bold text-emerald-900 text-[10px]">
                                                Rp {{ number_format($totalOmz, 0, ',', '.') }}
                                            </div>
                                            <div class="flex flex-wrap gap-0.5 mt-0.5">
                                                @foreach ($spgs as $spgN)
                                                    <span class="text-[9px] px-1 rounded bg-emerald-200/80 text-emerald-900 font-semibold">
                                                        {{ $spgN }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        </div>
                                    @elseif ($status === 'no_spg')
                                        <div class="p-1 rounded bg-amber-50 border border-amber-300 text-left" title="Peringatan: Outlet mencatat omzet, namun di sheet Presensi tidak ada SPG yang terjadwal bertugas!">
                                            <div class="font-mono font-bold text-amber-900 text-[10px]">
                                                Rp {{ number_format($totalOmz, 0, ',', '.') }}
                                            </div>
                                            <span class="inline-block mt-0.5 text-[8.5px] px-1 rounded bg-amber-200 text-amber-900 font-bold">
                                                ⚠️ TANPA SPG
                                            </span>
                                        </div>
                                    @elseif ($status === 'no_omzet')
                                        <div class="p-1 rounded bg-blue-50/60 border border-blue-200 text-left">
                                            <div class="font-mono text-slate-400 text-[10px]">
                                                Rp 0
                                            </div>
                                            <div class="flex flex-wrap gap-0.5 mt-0.5">
                                                @foreach ($spgs as $spgN)
                                                    <span class="text-[9px] px-1 rounded bg-blue-100 text-blue-900 font-medium">
                                                        {{ $spgN }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-slate-300 font-mono text-[11px]">-</span>
                                    @endif
                                </td>
                            @endforeach

                            <!-- Total Omzet Bulanan Outlet -->
                            <td class="px-4 py-2 text-right font-mono font-bold text-slate-900 bg-slate-50/50">
                                Rp {{ number_format($outletTotalOmzet, 0, ',', '.') }}
                            </td>

                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

    </div>

    <!-- Upload Excel Modal -->
    <div x-show="uploadModalOpen" 
         x-cloak 
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        
        <div @click.outside="uploadModalOpen = false" 
             class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-100 relative">
            
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                <div class="flex items-center gap-2">
                    <span class="w-8 h-8 rounded-xl bg-purple-100 text-purple-600 flex items-center justify-center font-bold text-sm">
                        📊
                    </span>
                    <h3 class="text-sm font-bold text-slate-900">Upload File Excel (Presensi & Omzet)</h3>
                </div>
                <button @click="uploadModalOpen = false" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
            </div>

            <form action="{{ route('tasks.upload', $task->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="mb-4">
                    <p class="text-xs text-slate-500 mb-3">
                        Pilih file Excel (.xlsx / .xls / .csv / .txt). Sistem secara <strong>100% otomatis</strong> mem-parsing sheet Presensi &amp; Omzet sesuai format baku, menghubungkan outlet harian ke masing-masing SPG, serta mengkalkulasi insentif berjenjang tanpa perlu pemetaan manual.
                    </p>

                    <div class="border-2 border-dashed border-slate-200 hover:border-purple-500 rounded-2xl p-6 text-center transition cursor-pointer bg-slate-50/50">
                        <input type="file" name="excel_file" accept=".xlsx,.xls,.csv,.txt" required class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100">
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                    <button type="button" @click="uploadModalOpen = false" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-xl">Batal</button>
                    <button type="submit" class="px-5 py-2.5 bg-purple-600 hover:bg-purple-700 text-white rounded-xl text-xs font-bold shadow-md shadow-purple-600/20">
                        Upload &amp; Impor Otomatis
                    </button>
                </div>
            </form>

        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
function incentiveMatrixWorkspace(config) {
    return {
        activeTab: 'spg_matrix',
        uploadModalOpen: false,
        currentTwinMode: config.twinMode,
        updatingTwinMode: false,

        async setTwinMode(mode) {
            if (this.currentTwinMode === mode || this.updatingTwinMode) return;
            this.updatingTwinMode = true;
            try {
                const res = await fetch(`/tasks/${config.taskId}/twin-mode`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': config.csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ twin_outlet_mode: mode })
                });
                const data = await res.json();
                if (data.success) {
                    this.currentTwinMode = data.twin_outlet_mode;
                    window.location.reload();
                }
            } catch (err) {
                console.error(err);
            } finally {
                this.updatingTwinMode = false;
            }
        },

        async updateManualAddition(recordId, val) {
            try {
                const res = await fetch(`/tasks/${config.taskId}/manual/${recordId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': config.csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        uang_tambahan: val
                    })
                });
                const data = await res.json();
                if (data.success) {
                    this.updateRowDOM(data.record);
                }
            } catch (err) {
                console.error(err);
            }
        },

        updateRowDOM(rec) {
            const formatRp = (n) => new Intl.NumberFormat('id-ID').format(n);

            const elAmk = document.getElementById(`val-omzet-amk-${rec.id}`);
            if (elAmk) elAmk.innerText = formatRp(rec.total_omzet_amk);

            const elKopi = document.getElementById(`val-omzet-kopi-${rec.id}`);
            if (elKopi) elKopi.innerText = formatRp(rec.total_omzet_amkopi);

            const elTotalOmzet = document.getElementById(`val-total-omzet-${rec.id}`);
            if (elTotalOmzet) elTotalOmzet.innerText = formatRp(rec.total_omzet);

            const elTier1 = document.getElementById(`val-tier1-${rec.id}`);
            if (elTier1) elTier1.innerText = formatRp(rec.insentif_tier_1);

            const elTier2 = document.getElementById(`val-tier2-${rec.id}`);
            if (elTier2) elTier2.innerText = formatRp(rec.insentif_tier_2);

            const elMurni = document.getElementById(`val-murni-${rec.id}`);
            if (elMurni) elMurni.innerText = formatRp(rec.insentif_murni);

            const elTotalInsentif = document.getElementById(`val-total-insentif-${rec.id}`);
            if (elTotalInsentif) elTotalInsentif.innerText = 'Rp ' + formatRp(rec.total_insentif);
        }
    };
}
</script>
@endpush
