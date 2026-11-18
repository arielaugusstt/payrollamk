@extends('layouts.app')

@section('content')
<div class="max-w-full px-4 sm:px-6 lg:px-8 py-6"
     x-data="bankDataManager({
         year: {{ $year }},
         month: {{ $month }},
         activeTab: '{{ request('tab', 'presensi') }}',
         hideZeroAttendance: true,
         presensiCellUrl: '{{ route('bank-data.presensi.cell') }}',
         omzetCellUrl: '{{ route('bank-data.omzet.cell') }}',
         csrfToken: '{{ csrf_token() }}'
     })">

    <!-- Page Header & Year/Month Selector -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 mb-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-orange-500 to-amber-600 text-white flex items-center justify-center shadow-lg shadow-orange-500/20 shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/>
                    </svg>
                </div>
                <div>
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <h1 class="text-xl font-bold text-slate-900">Bank Data Bulanan (Presensi & Omzet)</h1>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-orange-100 text-orange-700 border border-orange-200">
                            Kalender Penuh Tgl 1–{{ $daysInMonth }}
                        </span>
                    </div>
                    <p class="text-sm text-slate-500 mt-1">
                        Pusat penyimpanan kalender bulanan terpisah untuk <strong>Presensi SPG</strong> dan <strong>Omzet Outlet</strong>. Task Gaji (21–20) & Insentif (1–31) menarik data otomatis dari sini.
                    </p>
                </div>
            </div>

            <!-- Year & Month Selector Form -->
            <form method="GET" action="{{ route('bank-data.index') }}" class="flex items-center gap-2 bg-slate-50 p-2 rounded-xl border border-slate-200">
                <input type="hidden" name="tab" :value="activeTab">
                <div class="flex items-center gap-1.5">
                    <label class="text-xs font-semibold text-slate-500 pl-2">Bulan:</label>
                    <select name="month" onchange="this.form.submit()" class="rounded-lg border-slate-300 text-sm font-semibold text-slate-800 py-1.5 pl-3 pr-8 focus:ring-orange-500 focus:border-orange-500">
                        @foreach($monthNames as $mNum => $mName)
                            <option value="{{ $mNum }}" {{ $month === $mNum ? 'selected' : '' }}>{{ $mName }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-center gap-1.5">
                    <label class="text-xs font-semibold text-slate-500">Tahun:</label>
                    <select name="year" onchange="this.form.submit()" class="rounded-lg border-slate-300 text-sm font-semibold text-slate-800 py-1.5 pl-3 pr-8 focus:ring-orange-500 focus:border-orange-500">
                        @for($y = 2025; $y <= 2028; $y++)
                            <option value="{{ $y }}" {{ $year === $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>
                </div>
            </form>
        </div>

        <!-- 12-Month Quick Calendar Strip -->
        <div class="mt-5 pt-4 border-t border-slate-100">
            <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2.5">Status Kalender Bank Data Tahun {{ $year }}</div>
            <div class="grid grid-cols-3 sm:grid-cols-6 lg:grid-cols-12 gap-2">
                @foreach($monthNames as $mNum => $mName)
                    @php
                        $pItem = $allPeriodsInYear->get($mNum);
                        $hasP = $pItem && $pItem->has_presensi;
                        $hasO = $pItem && $pItem->has_omzet;
                        $isCurrent = ($mNum === $month);
                    @endphp
                    <a href="{{ route('bank-data.index', ['year' => $year, 'month' => $mNum]) }}"
                       class="p-2 rounded-xl border transition-all flex flex-col justify-between {{ $isCurrent ? 'bg-orange-50/90 border-orange-500 ring-2 ring-orange-500/20' : 'bg-slate-50/60 border-slate-200 hover:bg-slate-100' }}">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold {{ $isCurrent ? 'text-orange-700' : 'text-slate-700' }}">{{ substr($mName, 0, 3) }}</span>
                            @if($hasP && $hasO)
                                <span class="w-2 h-2 rounded-full bg-emerald-500" title="Lengkap (Presensi & Omzet)"></span>
                            @elseif($hasP || $hasO)
                                <span class="w-2 h-2 rounded-full bg-amber-500" title="Sebagian Terisi"></span>
                            @else
                                <span class="w-2 h-2 rounded-full bg-slate-300" title="Kosong"></span>
                            @endif
                        </div>
                        <div class="mt-1.5 flex items-center gap-1">
                            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold {{ $hasP ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-500' }}">P</span>
                            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold {{ $hasO ? 'bg-blue-100 text-blue-700' : 'bg-slate-200 text-slate-500' }}">O</span>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    <!-- 2 Independent Upload Slots for Selected Month & Year -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- SLOT 1: UPLOAD PRESENSI BULANAN -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 relative overflow-hidden">
            <div class="flex items-start justify-between gap-4 mb-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold">
                        1
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Slot 1: Upload Presensi Bulanan — {{ $monthNames[$month] }} {{ $year }}</h2>
                        <p class="text-xs text-slate-500">Format <code>presensirev.xlsx</code> (Baris 1 = Nama SPG, Kolom A = Tanggal 1–31, Sel = Kode Outlet)</p>
                    </div>
                </div>
                @if($period->has_presensi)
                    <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-700 border border-emerald-200 flex items-center gap-1 shrink-0">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        Tersimpan
                    </span>
                @else
                    <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-700 border border-amber-200 shrink-0">
                        Belum Diunggah
                    </span>
                @endif
            </div>

            @if($period->has_presensi)
                <div class="mb-3 px-3 py-2 rounded-lg bg-emerald-50/70 border border-emerald-200/70 text-xs text-emerald-800 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <span>File Terakhir: <strong>{{ $period->presensi_filename ?? 'presensirev.xlsx' }}</strong></span>
                        <span class="mx-1.5 text-emerald-300">|</span>
                        <span>Diperbarui: {{ $period->presensi_uploaded_at ? $period->presensi_uploaded_at->format('d M Y H:i') : '-' }}</span>
                    </div>
                    <button type="button"
                            @click="openDeleteModal('PRESENSI', '{{ $monthNames[$month] }} {{ $year }}', '{{ route('bank-data.delete.presensi') }}')"
                            class="px-2.5 py-1 rounded-lg bg-red-600 hover:bg-red-700 text-white text-[11px] font-bold flex items-center gap-1 shadow-2xs transition cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        Hapus Data Presensi
                    </button>
                </div>
            @endif

            <form method="POST" action="{{ route('bank-data.upload.presensi') }}" enctype="multipart/form-data" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                @csrf
                <input type="hidden" name="year" value="{{ $year }}">
                <input type="hidden" name="month" value="{{ $month }}">
                <input type="file" name="presensi_file" accept=".xlsx,.xls,.csv" required
                       class="block w-full text-xs text-slate-600 file:mr-3 file:py-2 file:px-3.5 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 border border-slate-200 rounded-xl p-1">
                <button type="submit" class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-sm transition shrink-0 flex items-center justify-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    Upload Presensi {{ substr($monthNames[$month], 0, 3) }}
                </button>
            </form>
        </div>

        <!-- SLOT 2: UPLOAD OMZET BULANAN -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 relative overflow-hidden">
            <div class="flex items-start justify-between gap-4 mb-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-blue-100 text-blue-700 flex items-center justify-center font-bold">
                        2
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Slot 2: Upload Omzet Bulanan — {{ $monthNames[$month] }} {{ $year }}</h2>
                        <p class="text-xs text-slate-500">Format <code>omsetrev.xlsx</code> (Baris 1 = Tanggal 1–31, Kolom A = Outlet & Baris AMKopi)</p>
                    </div>
                </div>
                @if($period->has_omzet)
                    <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-700 border border-blue-200 flex items-center gap-1 shrink-0">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        Tersimpan
                    </span>
                @else
                    <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-700 border border-amber-200 shrink-0">
                        Belum Diunggah
                    </span>
                @endif
            </div>

            @if($period->has_omzet)
                <div class="mb-3 px-3 py-2 rounded-lg bg-blue-50/70 border border-blue-200/70 text-xs text-blue-800 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <span>File Terakhir: <strong>{{ $period->omzet_filename ?? 'omsetrev.xlsx' }}</strong></span>
                        <span class="mx-1.5 text-blue-300">|</span>
                        <span>Diperbarui: {{ $period->omzet_uploaded_at ? $period->omzet_uploaded_at->format('d M Y H:i') : '-' }}</span>
                    </div>
                    <button type="button"
                            @click="openDeleteModal('OMZET', '{{ $monthNames[$month] }} {{ $year }}', '{{ route('bank-data.delete.omzet') }}')"
                            class="px-2.5 py-1 rounded-lg bg-red-600 hover:bg-red-700 text-white text-[11px] font-bold flex items-center gap-1 shadow-2xs transition cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        Hapus Data Omzet
                    </button>
                </div>
            @endif

            <form method="POST" action="{{ route('bank-data.upload.omzet') }}" enctype="multipart/form-data" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                @csrf
                <input type="hidden" name="year" value="{{ $year }}">
                <input type="hidden" name="month" value="{{ $month }}">
                <input type="file" name="omzet_file" accept=".xlsx,.xls,.csv" required
                       class="block w-full text-xs text-slate-600 file:mr-3 file:py-2 file:px-3.5 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 border border-slate-200 rounded-xl p-1">
                <button type="submit" class="px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold shadow-sm transition shrink-0 flex items-center justify-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    Upload Omzet {{ substr($monthNames[$month], 0, 3) }}
                </button>
            </form>
        </div>
    </div>

    <!-- Interactive Preview & Inline Edit Bank Data Matrices -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <!-- Matrix Toolbar & Tabs -->
        <div class="px-5 py-4 border-b border-slate-200 flex flex-wrap items-center justify-between gap-4 bg-slate-50/60">
            <div class="flex items-center gap-2">
                <button type="button" @click="activeTab = 'presensi'"
                        :class="activeTab === 'presensi' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-100'"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2">
                    <span>Matriks Presensi Bulanan ({{ count($spgs) }} Master SPG)</span>
                </button>
                <button type="button" @click="activeTab = 'omzet'"
                        :class="activeTab === 'omzet' ? 'bg-blue-600 text-white shadow-sm' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-100'"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2">
                    <span>Matriks Omzet Bulanan ({{ count($outlets) }} Master Outlet)</span>
                </button>
            </div>

            <div class="flex items-center gap-4">
                <!-- Toggle Hide 0-Attendance SPGs -->
                <label x-show="activeTab === 'presensi'" class="inline-flex items-center gap-2 cursor-pointer select-none bg-white px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-medium text-slate-700">
                    <input type="checkbox" x-model="hideZeroAttendance" class="rounded border-slate-300 text-orange-600 focus:ring-orange-500">
                    <span>Hide 0-Attendance SPGs</span>
                </label>

                <div class="relative">
                    <input type="text" x-model="searchQuery" placeholder="Cari SPG / Outlet..."
                           class="w-48 rounded-xl border-slate-300 text-xs py-1.5 pl-8 pr-3 focus:ring-orange-500 focus:border-orange-500">
                    <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- TAB 1: PRESENSI MATRIX (SPG x Days 1..daysInMonth) -->
        <div x-show="activeTab === 'presensi'" class="overflow-x-auto max-h-[70vh]">
            <table class="min-w-full border-collapse text-xs">
                <thead class="bg-slate-900 text-white sticky top-0 z-20">
                    <tr>
                        <th class="sticky left-0 z-30 bg-slate-900 px-3 py-3 text-left font-bold border-r border-slate-700 w-10">#</th>
                        <th class="sticky left-10 z-30 bg-slate-900 px-4 py-3 text-left font-bold border-r border-slate-700 min-w-[140px]">Nama SPG</th>
                        <th class="sticky left-[180px] z-30 bg-slate-900 px-3 py-3 text-center font-bold border-r border-slate-700 min-w-[70px]">Total Hari</th>
                        @for($d = 1; $d <= $daysInMonth; $d++)
                            @php
                                $dtObj = \Carbon\Carbon::createFromDate($year, $month, $d);
                                $isSun = $dtObj->isSunday();
                            @endphp
                            <th class="px-2 py-2 text-center font-bold border-r border-slate-800 min-w-[68px] {{ $isSun ? 'bg-red-900/70 text-red-200' : '' }}">
                                <div>Tgl {{ $d }}</div>
                                <div class="text-[10px] font-normal opacity-75">{{ $dtObj->translatedFormat('D') }}</div>
                            </th>
                        @endfor
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @foreach($spgs as $idx => $spg)
                        @php
                            $totalDays = $spgAttendanceTotals[$spg->id] ?? 0;
                        @endphp
                        <tr x-show="shouldShowSpgRow('{{ strtolower($spg->spg_name) }}', getSpgDays({{ $spg->id }}, {{ $totalDays }}))"
                            class="hover:bg-orange-50/40 transition">
                            <td class="sticky left-0 z-10 bg-white px-3 py-2 font-medium text-slate-400 border-r border-slate-200">{{ $idx + 1 }}</td>
                            <td class="sticky left-10 z-10 bg-white px-4 py-2 font-bold text-slate-900 border-r border-slate-200">{{ $spg->spg_name }}</td>
                            <td class="sticky left-[180px] z-10 bg-white px-3 py-2 text-center font-bold border-r border-slate-200">
                                <span class="px-2 py-0.5 rounded-full text-xs"
                                      :class="getSpgDays({{ $spg->id }}, {{ $totalDays }}) > 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-400'"
                                      x-text="getSpgDays({{ $spg->id }}, {{ $totalDays }})">
                                    {{ $totalDays }}
                                </span>
                            </td>
                            @for($d = 1; $d <= $daysInMonth; $d++)
                                @php
                                    $cell = $presensiGrid[$spg->id][$d] ?? ['outlet_id' => null, 'outlet_code' => null];
                                    $cCode = $cell['outlet_code'];
                                    $cOutletId = $cell['outlet_id'];
                                @endphp
                                <td @click="openPresensiModal({{ $spg->id }}, '{{ $spg->spg_name }}', {{ $d }}, getPresensiOutletId({{ $spg->id }}, {{ $d }}, {{ $cOutletId ?? 'null' }}))"
                                    class="px-1.5 py-2 text-center border-r border-slate-100 cursor-pointer hover:bg-amber-100/60 transition select-none">
                                    <template x-if="getPresensiCode({{ $spg->id }}, {{ $d }}, '{{ $cCode ?? '' }}')">
                                        <span class="inline-block px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200"
                                              x-text="getPresensiCode({{ $spg->id }}, {{ $d }}, '{{ $cCode ?? '' }}')"></span>
                                    </template>
                                    <template x-if="!getPresensiCode({{ $spg->id }}, {{ $d }}, '{{ $cCode ?? '' }}')">
                                        <span class="text-slate-300">-</span>
                                    </template>
                                </td>
                            @endfor
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- TAB 2: OMZET MATRIX (33 Outlets x Days 1..daysInMonth) -->
        <div x-show="activeTab === 'omzet'" x-cloak class="overflow-x-auto max-h-[70vh]">
            <table class="min-w-full border-collapse text-xs">
                <thead class="bg-slate-900 text-white sticky top-0 z-20">
                    <tr>
                        <th class="sticky left-0 z-30 bg-slate-900 px-3 py-3 text-left font-bold border-r border-slate-700 w-10">#</th>
                        <th class="sticky left-10 z-30 bg-slate-900 px-4 py-3 text-left font-bold border-r border-slate-700 min-w-[140px]">Kode Outlet</th>
                        <th class="sticky left-[180px] z-30 bg-slate-900 px-3 py-3 text-right font-bold border-r border-slate-700 min-w-[130px]">Total Bulanan</th>
                        @for($d = 1; $d <= $daysInMonth; $d++)
                            @php
                                $dtObj = \Carbon\Carbon::createFromDate($year, $month, $d);
                                $isSun = $dtObj->isSunday();
                            @endphp
                            <th class="px-2 py-2 text-center font-bold border-r border-slate-800 min-w-[95px] {{ $isSun ? 'bg-red-900/70 text-red-200' : '' }}">
                                <div>Tgl {{ $d }}</div>
                                <div class="text-[10px] font-normal opacity-75">{{ $dtObj->translatedFormat('D') }}</div>
                            </th>
                        @endfor
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @foreach($outlets as $idx => $outlet)
                        @php
                            $mTot = $outletMonthlyTotals[$outlet->id] ?? ['amk' => 0, 'kopi' => 0, 'total' => 0];
                        @endphp
                        <tr x-show="shouldShowOutletRow('{{ strtolower($outlet->outlet_code . ' ' . $outlet->outlet_name) }}')"
                            class="hover:bg-blue-50/40 transition">
                            <td class="sticky left-0 z-10 bg-white px-3 py-2 font-medium text-slate-400 border-r border-slate-200">{{ $idx + 1 }}</td>
                            <td class="sticky left-10 z-10 bg-white px-4 py-2 border-r border-slate-200">
                                <div class="font-bold text-slate-900">{{ $outlet->outlet_code }}</div>
                                <div class="text-[10px] text-slate-400 truncate max-w-[120px]">{{ $outlet->outlet_name }}</div>
                            </td>
                            <td class="sticky left-[180px] z-10 bg-white px-3 py-2 text-right font-bold text-slate-900 border-r border-slate-200">
                                <div x-text="formatRupiah(getOutletMonthlyTotal({{ $outlet->id }}, {{ $mTot['total'] }}))"></div>
                                @if($mTot['kopi'] > 0)
                                    <div class="text-[10px] text-amber-700 font-normal">inc. AMKopi</div>
                                @endif
                            </td>
                            @for($d = 1; $d <= $daysInMonth; $d++)
                                @php
                                    $oCell = $omzetGrid[$outlet->id][$d] ?? ['amk' => 0, 'kopi' => 0, 'total' => 0];
                                @endphp
                                <td @click="openOmzetModal({{ $outlet->id }}, '{{ $outlet->outlet_code }}', {{ $d }}, getOmzetAmk({{ $outlet->id }}, {{ $d }}, {{ $oCell['amk'] }}), getOmzetKopi({{ $outlet->id }}, {{ $d }}, {{ $oCell['kopi'] }}))"
                                    class="px-2 py-1.5 text-right border-r border-slate-100 cursor-pointer hover:bg-blue-100/60 transition select-none">
                                    <template x-if="getOmzetTotal({{ $outlet->id }}, {{ $d }}, {{ $oCell['total'] }}) > 0">
                                        <div>
                                            <div class="font-bold text-slate-800 text-[11px]"
                                                 x-text="formatCompact(getOmzetTotal({{ $outlet->id }}, {{ $d }}, {{ $oCell['total'] }}))"></div>
                                            <template x-if="getOmzetKopi({{ $outlet->id }}, {{ $d }}, {{ $oCell['kopi'] }}) > 0">
                                                <div class="text-[9px] text-amber-700 font-semibold"
                                                     x-text="'+Kopi ' + formatCompact(getOmzetKopi({{ $outlet->id }}, {{ $d }}, {{ $oCell['kopi'] }}))"></div>
                                            </template>
                                        </div>
                                    </template>
                                    <template x-if="getOmzetTotal({{ $outlet->id }}, {{ $d }}, {{ $oCell['total'] }}) <= 0">
                                        <span class="text-slate-300">-</span>
                                    </template>
                                </td>
                            @endfor
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal 1: Inline Edit Presensi Cell -->
    <div x-show="presensiModal.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
        <div @click.outside="presensiModal.open = false" class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-md p-6">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div>
                    <h3 class="text-base font-bold text-slate-900">Edit Presensi Bank Data</h3>
                    <p class="text-xs text-slate-500">SPG: <strong x-text="presensiModal.spgName"></strong> • Tanggal <strong x-text="presensiModal.day + ' {{ $monthNames[$month] }} {{ $year }}'"></strong></p>
                </div>
                <button @click="presensiModal.open = false" class="text-slate-400 hover:text-slate-600">&times;</button>
            </div>
            <div class="mt-4">
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Pilih Outlet Jaga (atau OFF / Libur):</label>
                <select x-model="presensiModal.outletId" class="w-full rounded-xl border-slate-300 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                    <option value="">-- OFF / LIBUR (-) --</option>
                    @foreach($outlets as $out)
                        <option value="{{ $out->id }}">{{ $out->outlet_code }} — {{ $out->outlet_name }} (AMK: Rp {{ number_format($out->uang_makan_utama, 0, ',', '.') }})</option>
                    @endforeach
                </select>
            </div>
            <div class="mt-6 flex items-center justify-end gap-2">
                <button type="button" @click="presensiModal.open = false" class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-50">Batal</button>
                <button type="button" @click="savePresensiCell()" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-sm">Simpan ke Bank Data</button>
            </div>
        </div>
    </div>

    <!-- Modal 2: Inline Edit Omzet Cell -->
    <div x-show="omzetModal.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
        <div @click.outside="omzetModal.open = false" class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-md p-6">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div>
                    <h3 class="text-base font-bold text-slate-900">Edit Omzet Bank Data</h3>
                    <p class="text-xs text-slate-500">Outlet: <strong x-text="omzetModal.outletCode"></strong> • Tanggal <strong x-text="omzetModal.day + ' {{ $monthNames[$month] }} {{ $year }}'"></strong></p>
                </div>
                <button @click="omzetModal.open = false" class="text-slate-400 hover:text-slate-600">&times;</button>
            </div>
            <div class="mt-4 space-y-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Omzet AMK Utama (Rp):</label>
                    <input type="number" step="100" min="0" x-model="omzetModal.amk" class="w-full rounded-xl border-slate-300 text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Omzet AMKopi (Rp):</label>
                    <input type="number" step="100" min="0" x-model="omzetModal.kopi" class="w-full rounded-xl border-slate-300 text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="p-3 rounded-xl bg-blue-50 border border-blue-100 flex items-center justify-between text-xs">
                    <span class="font-semibold text-blue-800">Total Omzet Harian:</span>
                    <strong class="text-sm text-blue-900" x-text="formatRupiah((Number(omzetModal.amk)||0) + (Number(omzetModal.kopi)||0))"></strong>
                </div>
            </div>
            <div class="mt-6 flex items-center justify-end gap-2">
                <button type="button" @click="omzetModal.open = false" class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-50">Batal</button>
                <button type="button" @click="saveOmzetCell()" class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold shadow-sm">Simpan ke Bank Data</button>
            </div>
        </div>
    </div>

    <!-- Modal 3: 2-Step Delete Confirmation Modal (2x Peringatan Konfirmasi) -->
    <div x-show="deleteModal.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4">
        <div @click.outside="deleteModal.open = false" class="bg-white rounded-3xl shadow-2xl border border-slate-200 w-full max-w-md p-6 overflow-hidden">
            
            <!-- STEP 1/2: Peringatan Pertama -->
            <div x-show="deleteModal.step === 1">
                <div class="flex items-center gap-3 pb-3 border-b border-slate-100">
                    <div class="w-11 h-11 rounded-2xl bg-amber-100 text-amber-600 flex items-center justify-center text-xl shrink-0">
                        ⚠️
                    </div>
                    <div>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-amber-100 text-amber-800 border border-amber-300">
                            PERINGATAN KONFIRMASI 1 DARI 2
                        </span>
                        <h3 class="text-base font-bold text-slate-900 mt-1">Hapus Data <span x-text="deleteModal.dataType"></span>?</h3>
                    </div>
                </div>

                <div class="mt-4 p-3.5 rounded-2xl bg-amber-50 border border-amber-200 text-xs text-amber-900 space-y-1.5">
                    <p class="font-semibold">
                        Anda akan menghapus seluruh data <strong x-text="deleteModal.dataType"></strong> untuk periode <strong x-text="deleteModal.periodLabel"></strong>.
                    </p>
                    <p class="text-amber-700">
                        Seluruh rekaman harian (Tgl 1–31) yang sudah diupload pada slot ini akan dikosongkan. Apakah Anda yakin ingin melanjutkan?
                    </p>
                </div>

                <div class="mt-6 flex items-center justify-end gap-2">
                    <button type="button" @click="deleteModal.open = false" class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-50 cursor-pointer">
                        Batal
                    </button>
                    <button type="button" @click="deleteModal.step = 2" class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold shadow-sm cursor-pointer flex items-center gap-1.5">
                        <span>Ya, Lanjut Konfirmasi Kedua (1/2)</span>
                        <span>&rarr;</span>
                    </button>
                </div>
            </div>

            <!-- STEP 2/2: Peringatan Terakhir (Final Confirmation) -->
            <div x-show="deleteModal.step === 2" x-cloak>
                <div class="flex items-center gap-3 pb-3 border-b border-red-100">
                    <div class="w-11 h-11 rounded-2xl bg-red-100 text-red-600 flex items-center justify-center text-xl shrink-0 animate-bounce">
                        🚨
                    </div>
                    <div>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-red-100 text-red-800 border border-red-300">
                            PERINGATAN TERAKHIR 2 DARI 2
                        </span>
                        <h3 class="text-base font-bold text-red-700 mt-1">Konfirmasi Penghapusan Permanen</h3>
                    </div>
                </div>

                <div class="mt-4 p-3.5 rounded-2xl bg-red-50 border-2 border-red-300 text-xs text-red-900 space-y-1.5">
                    <p class="font-bold text-red-800">
                        PERHATIAN: Tindakan ini TIDAK DAPAT DIBATALKAN!
                    </p>
                    <p>
                        Data <strong x-text="deleteModal.dataType"></strong> bulan <strong x-text="deleteModal.periodLabel"></strong> akan dihapus secara permanen dari Bank Data Bulanan dan Task DRAFT terkait akan ikut di-reset.
                    </p>
                </div>

                <form method="POST" :action="deleteModal.actionUrl" class="mt-6 flex items-center justify-between gap-2">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="year" value="{{ $year }}">
                    <input type="hidden" name="month" value="{{ $month }}">

                    <button type="button" @click="deleteModal.open = false; deleteModal.step = 1" class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-50 cursor-pointer">
                        Batalkan
                    </button>
                    <button type="submit" class="px-4 py-2.5 rounded-xl bg-red-600 hover:bg-red-700 text-white text-xs font-extrabold shadow-md shadow-red-600/25 cursor-pointer flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        <span>YA, HAPUS DATA SEKARANG (2/2)</span>
                    </button>
                </form>
            </div>

        </div>
    </div>

</div>

<script>
function bankDataManager(config) {
    return {
        year: config.year,
        month: config.month,
        activeTab: config.activeTab,
        hideZeroAttendance: config.hideZeroAttendance,
        searchQuery: '',
        presensiUpdates: {},
        spgDayTotals: {},
        omzetUpdates: {},
        outletMonthlyTotals: {},
        presensiModal: {
            open: false,
            spgId: null,
            spgName: '',
            day: null,
            outletId: ''
        },
        omzetModal: {
            open: false,
            outletId: null,
            outletCode: '',
            day: null,
            amk: 0,
            kopi: 0
        },
        deleteModal: {
            open: false,
            step: 1,
            dataType: '',
            periodLabel: '',
            actionUrl: ''
        },
        openDeleteModal(dataType, periodLabel, actionUrl) {
            this.deleteModal.dataType = dataType;
            this.deleteModal.periodLabel = periodLabel;
            this.deleteModal.actionUrl = actionUrl;
            this.deleteModal.step = 1;
            this.deleteModal.open = true;
        },
        shouldShowSpgRow(spgNameLower, totalDays) {
            if (this.hideZeroAttendance && totalDays <= 0) {
                return false;
            }
            if (this.searchQuery.trim() !== '' && !spgNameLower.includes(this.searchQuery.trim().toLowerCase())) {
                return false;
            }
            return true;
        },
        shouldShowOutletRow(outletTextLower) {
            if (this.searchQuery.trim() !== '' && !outletTextLower.includes(this.searchQuery.trim().toLowerCase())) {
                return false;
            }
            return true;
        },
        getSpgDays(spgId, fallback) {
            return (this.spgDayTotals[spgId] !== undefined) ? this.spgDayTotals[spgId] : fallback;
        },
        getPresensiCode(spgId, day, fallback) {
            const key = `${spgId}_${day}`;
            return (this.presensiUpdates[key] !== undefined) ? this.presensiUpdates[key].code : fallback;
        },
        getPresensiOutletId(spgId, day, fallback) {
            const key = `${spgId}_${day}`;
            return (this.presensiUpdates[key] !== undefined) ? this.presensiUpdates[key].outletId : fallback;
        },
        openPresensiModal(spgId, spgName, day, currentOutletId) {
            this.presensiModal.spgId = spgId;
            this.presensiModal.spgName = spgName;
            this.presensiModal.day = day;
            this.presensiModal.outletId = currentOutletId ? String(currentOutletId) : '';
            this.presensiModal.open = true;
        },
        async savePresensiCell() {
            const res = await fetch(config.presensiCellUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': config.csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    year: this.year,
                    month: this.month,
                    day: this.presensiModal.day,
                    spg_id: this.presensiModal.spgId,
                    outlet_id: this.presensiModal.outletId || null
                })
            });
            const data = await res.json();
            if (data.success) {
                const key = `${this.presensiModal.spgId}_${this.presensiModal.day}`;
                this.presensiUpdates[key] = {
                    outletId: data.outlet_id,
                    code: data.outlet_code || ''
                };
                this.spgDayTotals[this.presensiModal.spgId] = data.total_days;
                this.presensiModal.open = false;
            }
        },
        getOmzetAmk(outletId, day, fallback) {
            const key = `${outletId}_${day}`;
            return (this.omzetUpdates[key] !== undefined) ? this.omzetUpdates[key].amk : fallback;
        },
        getOmzetKopi(outletId, day, fallback) {
            const key = `${outletId}_${day}`;
            return (this.omzetUpdates[key] !== undefined) ? this.omzetUpdates[key].kopi : fallback;
        },
        getOmzetTotal(outletId, day, fallback) {
            const key = `${outletId}_${day}`;
            return (this.omzetUpdates[key] !== undefined) ? this.omzetUpdates[key].total : fallback;
        },
        getOutletMonthlyTotal(outletId, fallback) {
            return (this.outletMonthlyTotals[outletId] !== undefined) ? this.outletMonthlyTotals[outletId] : fallback;
        },
        openOmzetModal(outletId, outletCode, day, amk, kopi) {
            this.omzetModal.outletId = outletId;
            this.omzetModal.outletCode = outletCode;
            this.omzetModal.day = day;
            this.omzetModal.amk = amk;
            this.omzetModal.kopi = kopi;
            this.omzetModal.open = true;
        },
        async saveOmzetCell() {
            const res = await fetch(config.omzetCellUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': config.csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    year: this.year,
                    month: this.month,
                    day: this.omzetModal.day,
                    outlet_id: this.omzetModal.outletId,
                    omzet_amk: Number(this.omzetModal.amk) || 0,
                    omzet_amkopi: Number(this.omzetModal.kopi) || 0
                })
            });
            const data = await res.json();
            if (data.success) {
                const key = `${this.omzetModal.outletId}_${this.omzetModal.day}`;
                this.omzetUpdates[key] = {
                    amk: data.amk,
                    kopi: data.kopi,
                    total: data.total
                };
                this.outletMonthlyTotals[this.omzetModal.outletId] = data.outlet_monthly_total;
                this.omzetModal.open = false;
            }
        },
        formatRupiah(val) {
            return 'Rp ' + Math.round(Number(val) || 0).toLocaleString('id-ID');
        },
        formatCompact(val) {
            return Math.round(Number(val) || 0).toLocaleString('id-ID');
        }
    };
}
</script>
@endsection
