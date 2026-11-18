@extends('layouts.app', ['title' => $task->title . ' &bull; Salary Matrix'])

@section('content')
<div class="space-y-4" x-data="salaryMatrixWorkspace({
    taskId: {{ $task->id }},
    isFinished: {{ $task->isFinished() ? 'true' : 'false' }},
    csrfToken: '{{ csrf_token() }}'
})">

    <!-- Datalist of Available Outlets for Quick Autocomplete in Cells -->
    <datalist id="outlet-options">
        <option value="-">- (OFF/Libur)</option>
        @foreach ($allOutlets as $o)
            <option value="{{ $o->outlet_code }}">{{ $o->outlet_code }} - {{ $o->outlet_name }} (AMK: {{ number_format($o->uang_makan_utama, 0, ',', '.') }})</option>
        @endforeach
    </datalist>

    <!-- Top Toolbar & Status Banner -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center space-x-3">
            <a href="{{ route('tasks.index') }}" class="p-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl transition" title="Kembali ke Task Hub">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
            </a>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-lg font-bold text-slate-900">{{ $task->title }}</h1>
                    @if ($task->isFinished())
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-300 flex items-center gap-1">
                            <span class="w-2 h-2 rounded-full bg-emerald-600"></span> TERKUNCI (FINISHED)
                        </span>
                    @else
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-300 flex items-center gap-1">
                            <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span> DRAFT (DYNAMIC DAILY OUTLET)
                        </span>
                    @endif
                </div>
                <p class="text-xs text-slate-500">
                    Periode: <span class="font-semibold text-slate-700">{{ $task->start_date->format('d M Y') }} – {{ $task->end_date->format('d M Y') }}</span>
                    &bull; Dynamic Outlet: 1 Hari = 1 Outlet per SPG &bull; Uang Makan harian mengikuti outlet tanggal tsb &bull; Gaji Pokok mengikuti Outlet Dominan
                </p>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-wrap items-center gap-2">
            <!-- Re-Sync from Bank Data Trigger -->
            @if (!$task->isFinished())
                <form action="{{ route('tasks.resync_bank_data', $task->id) }}" method="POST" class="inline-flex items-center gap-1.5 bg-orange-50 border border-orange-200 px-2.5 py-1.5 rounded-xl">
                    @csrf
                    <label class="inline-flex items-center gap-1 text-[11px] text-orange-800 font-medium cursor-pointer select-none" title="Jangan timpa sel yang sudah diedit manual">
                        <input type="checkbox" name="preserve_manual_overrides" value="1" checked class="rounded border-orange-300 text-orange-600 focus:ring-orange-500 w-3.5 h-3.5">
                        <span>Keep Manual</span>
                    </label>
                    <button type="submit" class="px-2.5 py-1 bg-orange-600 hover:bg-orange-700 text-white rounded-lg text-xs font-bold flex items-center gap-1 transition cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Re-Sync Bank Data
                    </button>
                </form>
            @endif

            <!-- Upload Excel Trigger -->
            <button @click="uploadModalOpen = true" 
                    class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold flex items-center gap-1.5 transition cursor-pointer">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
                Upload Excel
            </button>

            <!-- Delete / Reset Uploaded Task Data Trigger (2x Confirmation) -->
            @if (!$task->isFinished())
                <button type="button"
                        @click="clearDataModal.step = 1; clearDataModal.open = true"
                        class="px-3 py-2 bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 rounded-xl text-xs font-semibold flex items-center gap-1.5 transition cursor-pointer"
                        title="Hapus & kosongkan seluruh data presensi pada task ini">
                    <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    Hapus Data Upload
                </button>
            @endif

            <!-- Download Sample Template -->
            <a href="{{ route('export.template', $task->id) }}" 
               class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold flex items-center gap-1.5 transition">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Template
            </a>

            <!-- Export to Excel (.xlsx) -->
            <a href="{{ route('export.salary', $task->id) }}" 
               class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold shadow-xs flex items-center gap-1.5 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Export Excel Matrix
            </a>

            <!-- Task State Toggle -->
            @if ($task->isFinished())
                <form action="{{ route('tasks.reopen', $task->id) }}" method="POST" onsubmit="return confirm('Buka kembali task ini untuk merevisi data?');">
                    @csrf
                    <button type="submit" class="px-3.5 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-xs font-semibold shadow-xs flex items-center gap-1.5 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Re-open Task (Revisi)
                    </button>
                </form>
            @else
                <form action="{{ route('tasks.finish', $task->id) }}" method="POST" onsubmit="return confirm('Kunci dan selesaikan task ini? Task yang selesai akan mengalirkan datanya ke Executive Dashboard.');">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-md shadow-emerald-600/20 flex items-center gap-1.5 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Finish Task (Selesai & Kunci)
                    </button>
                </form>
            @endif
        </div>
    </div>

    <!-- Pre-Flight Bank Data Readiness Bar -->
    @if(isset($bankReadiness))
        <div class="bg-white px-4 py-3 rounded-2xl border {{ $bankReadiness['is_ready'] ? 'border-emerald-200' : 'border-amber-300 bg-amber-50/40' }} shadow-2xs flex flex-wrap items-center justify-between gap-3 text-xs">
            <div class="flex items-center gap-2.5 flex-wrap">
                <span class="font-bold text-slate-700 flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/></svg>
                    Sumber Bank Data Bulanan:
                </span>
                @foreach($bankReadiness['required_periods'] as $reqP)
                    <a href="{{ route('bank-data.index', ['year' => $reqP['year'], 'month' => $reqP['month'], 'tab' => strtolower($reqP['type'])]) }}"
                       class="px-2.5 py-1 rounded-lg font-semibold border flex items-center gap-1.5 transition {{ $reqP['is_ready'] ? 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100' : 'bg-red-100 text-red-800 border-red-300 hover:bg-red-200 animate-pulse' }}">
                        <span>{{ $reqP['is_ready'] ? '✓ Tersedia:' : '⚠ Belum Upload:' }}</span>
                        <span>{{ $reqP['label'] }}</span>
                    </a>
                @endforeach
            </div>
            <a href="{{ route('bank-data.index') }}" class="text-orange-600 hover:text-orange-700 font-bold hover:underline flex items-center gap-1">
                <span>Kelola Bank Data Bulanan</span>
                <span>&rarr;</span>
            </a>
        </div>
    @endif

    <!-- Step 1 Banner: Multiplier Setup Bar -->
    <div class="bg-slate-900 text-white p-3.5 rounded-2xl shadow-sm flex flex-col lg:flex-row lg:items-center justify-between gap-3 text-xs" x-data="{ expanded: false }">
        <div class="flex items-center space-x-3">
            <span class="w-7 h-7 rounded-lg bg-orange-500/20 text-orange-400 border border-orange-500/30 flex items-center justify-center font-bold text-xs">
                ⚡
            </span>
            <div>
                <span class="font-bold text-white">Setup Multiplier Harian:</span>
                <span class="text-slate-400 ml-1">Hari Normal = 1x &bull; Hari BESAR / Minggu = 2x &bull; Hari Khusus = 3x</span>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <form action="{{ route('tasks.auto_sundays', $task->id) }}" method="POST">
                @csrf
                <button type="submit" 
                        class="px-3 py-1.5 bg-orange-600 hover:bg-orange-700 text-white rounded-lg font-semibold flex items-center gap-1.5 transition cursor-pointer"
                        title="Otomatis menandai semua hari Minggu sebagai 2x BESAR">
                    <span>⚡ Auto-Set Hari Minggu (2x BESAR)</span>
                </button>
            </form>

            <button @click="expanded = !expanded" class="px-2.5 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg text-xs font-medium">
                <span x-text="expanded ? 'Tutup Grid Multiplier' : 'Atur Multiplier Per-Tanggal'"></span>
            </button>
        </div>

        <!-- Collapsible Multiplier Grid -->
        <div x-show="expanded" x-cloak class="w-full pt-3 mt-2 border-t border-slate-800 grid grid-cols-5 sm:grid-cols-7 md:grid-cols-10 lg:grid-cols-15 gap-1.5">
            @foreach ($dates as $d)
                @php
                    $ymd = $d->format('Y-m-d');
                    $mModel = $multipliersMap->get($ymd);
                    $mVal = $mModel ? $mModel->multiplier : 1;
                    $isSun = $d->isSunday();
                @endphp
                <div class="bg-slate-800/80 p-1.5 rounded-lg text-center border {{ $mVal > 1 ? 'border-orange-500/60 bg-orange-950/30' : 'border-slate-700' }}">
                    <div class="text-[10px] {{ $isSun ? 'text-red-400 font-bold' : 'text-slate-400' }}">
                        {{ $d->format('d/m') }} ({{ substr($d->format('D'), 0, 2) }})
                    </div>
                    <select @change="updateMultiplier('{{ $ymd }}', $event.target.value)" 
                            class="w-full mt-1 bg-slate-900 border border-slate-700 text-white text-[10px] rounded p-0.5 text-center focus:outline-none focus:ring-1 focus:ring-orange-500"
                            {{ $task->isFinished() ? 'disabled' : '' }}>
                        <option value="1" {{ $mVal == 1 ? 'selected' : '' }}>1x</option>
                        <option value="2" {{ $mVal == 2 ? 'selected' : '' }}>2x (BESAR)</option>
                        <option value="3" {{ $mVal == 3 ? 'selected' : '' }}>3x</option>
                    </select>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Workspace Tabs Navigation & Filters -->
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 pb-2">
        <div class="flex items-center gap-2">
            <button type="button"
                    @click="activeTab = 'spg_matrix'"
                    :class="activeTab === 'spg_matrix' ? 'bg-orange-600 text-white font-bold shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'"
                    class="px-4 py-2 rounded-xl text-xs flex items-center gap-2 transition cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                <span>Matriks Gaji SPG (Per SPG)</span>
                <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-white/20 text-white font-mono">{{ $task->salaryRecords->count() }}</span>
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

        <div class="flex items-center gap-3">
            <!-- Toggle Hide 0-Attendance SPGs -->
            <label class="inline-flex items-center gap-2 cursor-pointer select-none bg-white px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700 shadow-2xs">
                <input type="checkbox" x-model="hideZeroAttendance" class="rounded border-slate-300 text-orange-600 focus:ring-orange-500">
                <span>Hide 0-Attendance SPGs</span>
            </label>

            <!-- Search SPG / Outlet -->
            <input type="text" x-model="searchFilter" placeholder="Cari SPG / Outlet..."
                   class="w-44 rounded-xl border-slate-200 bg-white text-xs py-1.5 px-3 focus:ring-orange-500 focus:border-orange-500">
        </div>
    </div>

    <!-- Step 4: Interactive Horizontal Pivot Matrix Grid -->
    <div x-show="activeTab === 'spg_matrix'" class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
        
        <!-- Table Legend & Sticky Notice -->
        <div class="px-4 py-2 bg-slate-50 border-b border-slate-200 flex flex-wrap items-center justify-between text-[11px] text-slate-500 gap-2">
            <div class="flex items-center gap-3">
                <span class="flex items-center gap-1">
                    <span class="w-3 h-3 rounded bg-blue-100 border border-blue-300"></span> Outlet Kerja Tanggal Tsb
                </span>
                <span class="flex items-center gap-1">
                    <span class="w-3 h-3 rounded bg-orange-100 border border-orange-400"></span> Tanggal 2x / BESAR
                </span>
                <span class="flex items-center gap-1">
                    <span class="w-3 h-3 rounded bg-red-100 border border-red-300"></span> Absen < 40% (Warning)
                </span>
                <span class="text-slate-400 italic">| Klik kode outlet di sel harian untuk memindahkan lokasi kerja SPG</span>
            </div>
            <div class="text-slate-400">
                Freeze Panes: Kolom SPG & Outlet Terkunci di Kiri saat scroll horizontal
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
                        <!-- Pinned Left 3: Outlets Visited (Days) + Reference Outlet Dropdown -->
                        <th class="sticky left-[190px] z-40 bg-slate-900 px-3 py-3 min-w-[210px] text-left border-b border-r border-slate-700 shadow-[2px_0_5px_rgba(0,0,0,0.15)]">
                            OUTLETS DIKUNJUNGI (REF POKOK)
                        </th>

                        <!-- Horizontal Dates Columns (21 .. 20) -->
                        @foreach ($dates as $d)
                            @php
                                $ymd = $d->format('Y-m-d');
                                $mModel = $multipliersMap->get($ymd);
                                $mVal = $mModel ? $mModel->multiplier : 1;
                                $isSun = $d->isSunday();
                            @endphp
                            <th class="px-2 py-2 text-center min-w-[55px] border-b border-r border-slate-800 {{ $mVal > 1 ? 'bg-orange-950/80 text-orange-200' : '' }}">
                                <div class="text-[10px] {{ $isSun ? 'text-red-400 font-extrabold' : 'text-slate-400' }}">
                                    {{ $d->format('d') }}
                                </div>
                                @if ($mVal > 1)
                                    <span class="inline-block text-[9px] px-1 bg-orange-600 text-white font-extrabold rounded">
                                        {{ $mVal }}x
                                    </span>
                                @else
                                    <span class="text-[9px] text-slate-500 font-normal">1x</span>
                                @endif
                            </th>
                        @endforeach

                        <!-- Calculation Summary Columns -->
                        <th class="px-3 py-3 text-center bg-slate-800 text-slate-200 border-b border-r border-slate-700">HARI</th>
                        <th class="px-3 py-3 text-center bg-slate-800 text-slate-200 border-b border-r border-slate-700">% ABSEN</th>
                        <th class="px-3 py-3 text-right bg-slate-800 text-slate-200 border-b border-r border-slate-700">U. MAKAN UTAMA</th>
                        <th class="px-3 py-3 text-right bg-slate-800 text-slate-200 border-b border-r border-slate-700">U. MAKAN TAMBAHAN</th>
                        <th class="px-3 py-3 text-right bg-slate-800 text-slate-200 border-b border-r border-slate-700">POKOK REK.</th>
                        <th class="px-3 py-3 text-center bg-amber-900/60 text-amber-200 border-b border-r border-slate-700">KEBIJAKAN 100%</th>
                        <th class="px-3 py-3 text-right bg-slate-800 text-slate-200 border-b border-r border-slate-700">POKOK FINAL</th>
                        <th class="px-3 py-3 text-right bg-slate-800 text-slate-200 border-b border-r border-slate-700">PULSA</th>
                        <th class="px-3 py-3 text-right bg-slate-800 text-slate-200 border-b border-r border-slate-700">TAMBAHAN</th>
                        <th class="px-4 py-3 text-right bg-red-950 text-red-300 font-extrabold border-b border-slate-700 shadow-sm min-w-[130px]">
                            TOTAL SALARY (RP)
                        </th>
                    </tr>
                </thead>

                <!-- Table Body -->
                <tbody class="divide-y divide-slate-100">
                    @php $no = 1; @endphp
                    @foreach ($task->salaryRecords as $rec)
                        @php
                            $logsMap = $rec->dailyLogs->keyBy(fn($l) => $l->date->format('Y-m-d'));
                            $isBelowThreshold = $rec->attendance_percentage < 40.0;
                        @endphp
                        <tr x-show="shouldShowRow('{{ strtolower($rec->spg->spg_name) }}', {{ $rec->id }}, {{ (int)$rec->total_hari_masuk }})"
                            class="hover:bg-slate-50/80 transition-colors group" id="row-record-{{ $rec->id }}">
                            
                            <!-- Pinned Left 1: No -->
                            <td class="sticky left-0 z-20 bg-white group-hover:bg-slate-50 px-3 py-2 text-center text-slate-400 font-medium border-r border-slate-200">
                                {{ $no++ }}
                            </td>

                            <!-- Pinned Left 2: SPG Name -->
                            <td class="sticky left-10 z-20 bg-white group-hover:bg-slate-50 px-4 py-2 font-bold text-slate-900 border-r border-slate-200">
                                <button type="button" 
                                        @click="openSlipModal({{ $rec->id }})" 
                                        class="hover:text-orange-600 hover:underline text-left cursor-pointer flex items-center gap-1.5"
                                        title="Klik untuk membuka Slip Gaji Rincian Harian">
                                    <span>{{ $rec->spg->spg_name }}</span>
                                    <svg class="w-3 h-3 text-slate-400 group-hover:text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </button>
                            </td>

                            <!-- Pinned Left 3: Outlets Visited (Badges) + Reference Outlet Dropdown -->
                            <td class="sticky left-[190px] z-20 bg-white group-hover:bg-slate-50 px-3 py-2 border-r border-slate-200 shadow-[2px_0_5px_rgba(0,0,0,0.04)]">
                                <div class="flex flex-col gap-1">
                                    <!-- Badges of Outlets visited -->
                                    <div class="flex flex-wrap gap-1" id="outlets-summary-{{ $rec->id }}">
                                        @if ($rec->outlets_summary && is_array($rec->outlets_summary))
                                            @foreach ($rec->outlets_summary as $os)
                                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-mono font-bold bg-slate-100 text-slate-800 border border-slate-200">
                                                    {{ $os['code'] }} <span class="text-orange-600 font-extrabold">{{ $os['days'] }}d</span>
                                                </span>
                                            @endforeach
                                        @else
                                            <span class="text-slate-400 text-[10px]">-</span>
                                        @endif
                                    </div>

                                    <!-- Inline Reference Outlet Selector -->
                                    <div class="flex items-center gap-1 mt-0.5">
                                        <span class="text-[9px] text-slate-400 uppercase font-semibold">Ref:</span>
                                        <select @change="updateReferenceOutlet({{ $rec->id }}, $event.target.value)"
                                                class="text-[10px] font-semibold bg-slate-50 border border-slate-200 rounded px-1 py-0.5 focus:outline-none focus:ring-1 focus:ring-orange-500"
                                                {{ $task->isFinished() ? 'disabled' : '' }}>
                                            <option value="">-- Dominan --</option>
                                            @foreach ($allOutlets as $o)
                                                <option value="{{ $o->id }}" {{ $rec->reference_outlet_id == $o->id ? 'selected' : '' }}>
                                                    {{ $o->outlet_code }} ({{ number_format($o->gaji_pokok, 0, ',', '.') }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </td>

                            <!-- Date Cells (Dynamic Daily Outlet Code & Allowance + Full Inline Edit Modal) -->
                            @foreach ($dates as $d)
                                @php
                                    $ymd = $d->format('Y-m-d');
                                    $log = $logsMap->get($ymd);
                                    $isPresent = ($log && $log->is_present);
                                    $displayCode = $isPresent ? ($log->outlet_code ?: ($log->chosenOutlet ? $log->chosenOutlet->outlet_code : ($log->raw_cell_value ?: 'OK'))) : '-';
                                    $mModel = $multipliersMap->get($ymd);
                                    $mVal = $mModel ? $mModel->multiplier : 1;
                                    $dailyUangMakan = $log ? $log->nominal_uang_makan : 0;
                                    $customNom = $log ? $log->custom_nominal_override : null;
                                @endphp
                                <td class="px-1 py-1 text-center border-r border-slate-100 {{ $mVal > 1 ? 'bg-orange-50/40' : '' }}">
                                    <div id="cell-box-{{ $rec->id }}-{{ $ymd }}"
                                         @if(!$task->isFinished())
                                             @dblclick="openCellModal({{ $rec->id }}, '{{ $rec->spg->spg_name }}', '{{ $ymd }}', '{{ $d->format('d M Y') }}')"
                                         @endif
                                         class="flex flex-col items-center justify-center p-0.5 rounded relative group/cell {{ $isPresent ? 'bg-blue-50/80 border border-blue-100' : '' }}">
                                        <div class="flex items-center gap-0.5">
                                            @if ($task->isFinished())
                                                <span class="inline-block px-1 py-0.5 rounded text-[10px] font-mono {{ $isPresent ? 'text-blue-900 font-extrabold' : 'text-slate-300' }}">
                                                    {{ $displayCode }}
                                                </span>
                                            @else
                                                <input type="text" 
                                                       id="cell-input-{{ $rec->id }}-{{ $ymd }}"
                                                       list="outlet-options"
                                                       value="{{ $displayCode }}" 
                                                       data-custom-nominal="{{ $customNom !== null ? (int)$customNom : '' }}"
                                                       @change="updateCell({{ $rec->id }}, '{{ $ymd }}', $event.target.value, null, false)"
                                                       class="w-13 text-center py-0.5 px-0.5 text-[10px] font-mono font-bold rounded border border-transparent hover:border-slate-300 focus:border-orange-500 focus:bg-white focus:outline-none transition {{ $isPresent ? 'text-blue-900' : 'bg-transparent text-slate-300' }}">
                                                <button type="button"
                                                        @click="openCellModal({{ $rec->id }}, '{{ $rec->spg->spg_name }}', '{{ $ymd }}', '{{ $d->format('d M Y') }}')"
                                                        class="opacity-0 group-hover/cell:opacity-100 text-[9px] text-slate-400 hover:text-orange-600 px-0.5 transition cursor-pointer"
                                                        title="Edit Detail Sel & Custom Nominal">
                                                    ✎
                                                </button>
                                            @endif
                                            @if ($isPresent && $mVal > 1)
                                                <span class="text-[8.5px] font-extrabold px-1 rounded bg-orange-600 text-white" title="Pengali Hari Besar: {{ $mVal }}x">
                                                    {{ $mVal }}x
                                                </span>
                                            @endif
                                        </div>
                                        <span id="cell-nom-{{ $rec->id }}-{{ $ymd }}" class="text-[9px] font-mono {{ $customNom !== null ? 'text-purple-700 font-bold underline' : 'text-slate-600 font-semibold' }} leading-tight">
                                            {{ ($isPresent && $dailyUangMakan > 0) ? number_format($dailyUangMakan, 0, ',', '.') : '' }}
                                        </span>
                                    </div>
                                </td>
                            @endforeach

                            <!-- Calculation Summary Columns -->
                            <td class="px-3 py-2 text-center font-bold text-slate-800 bg-slate-50/50 border-r border-slate-200" id="val-hari-{{ $rec->id }}">
                                {{ $rec->total_hari_masuk }}
                            </td>

                            <td class="px-3 py-2 text-center font-semibold border-r border-slate-200" id="val-absen-{{ $rec->id }}">
                                @if ($isBelowThreshold)
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-red-100 text-red-700 border border-red-200" title="Absensi < 40% (Kurang dari 10,4 hari)">
                                        {{ $rec->attendance_percentage }}% ⚠️
                                    </span>
                                @else
                                    <span class="text-emerald-700 font-medium">
                                        {{ $rec->attendance_percentage }}%
                                    </span>
                                @endif
                            </td>

                            <td class="px-3 py-2 text-right text-slate-700 font-mono border-r border-slate-200" id="val-makan-utama-{{ $rec->id }}">
                                {{ number_format($rec->uang_makan_utama, 0, ',', '.') }}
                            </td>

                            <td class="px-3 py-2 text-right text-slate-700 font-mono border-r border-slate-200" id="val-makan-tambahan-{{ $rec->id }}">
                                {{ number_format($rec->uang_makan_tambahan, 0, ',', '.') }}
                            </td>

                            <td class="px-3 py-2 text-right text-slate-700 font-mono border-r border-slate-200" id="val-pokok-rek-{{ $rec->id }}">
                                {{ number_format($rec->base_salary_recommended, 0, ',', '.') }}
                            </td>

                            <!-- Kebijakan 100% Override Toggle -->
                            <td class="px-3 py-2 text-center bg-amber-50/40 border-r border-slate-200">
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="checkbox" 
                                           @change="togglePolicy({{ $rec->id }}, $event.target.checked)"
                                           {{ $rec->policy_100_percent_override ? 'checked' : '' }}
                                           {{ $task->isFinished() ? 'disabled' : '' }}
                                           class="w-4 h-4 rounded text-orange-600 focus:ring-orange-500 border-slate-300">
                                    <span class="ml-1.5 text-[10px] font-bold text-amber-900">100%</span>
                                </label>
                            </td>

                            <td class="px-3 py-2 text-right text-slate-800 font-mono font-bold border-r border-slate-200" id="val-pokok-final-{{ $rec->id }}">
                                {{ number_format($rec->base_salary_final, 0, ',', '.') }}
                            </td>

                            <td class="px-3 py-2 text-right text-slate-700 font-mono border-r border-slate-200" id="val-pulsa-{{ $rec->id }}">
                                {{ number_format($rec->uang_pulsa, 0, ',', '.') }}
                            </td>

                            <!-- Manual Tambahan Input -->
                            <td class="px-2 py-1 text-right border-r border-slate-200">
                                @if ($task->isFinished())
                                    <span class="font-mono text-slate-700">{{ number_format($rec->uang_tambahan, 0, ',', '.') }}</span>
                                @else
                                    <input type="number" 
                                           value="{{ (int)$rec->uang_tambahan }}" 
                                           @change="updateManualAddition({{ $rec->id }}, $event.target.value)"
                                           class="w-20 text-right px-1.5 py-0.5 font-mono text-xs rounded border border-slate-200 focus:border-orange-500 focus:outline-none">
                                @endif
                            </td>

                            <!-- Grand Total Salary SPG -->
                            <td class="px-4 py-2 text-right font-mono font-extrabold text-red-600 bg-red-50/50 text-sm shadow-xs" id="val-total-salary-{{ $rec->id }}">
                                Rp {{ number_format($rec->total_salary, 0, ',', '.') }}
                            </td>

                        </tr>
                    @endforeach
                </tbody>

                <!-- Table Footer Grand Totals -->
                <tfoot class="bg-slate-900 text-white font-bold sticky bottom-0 z-30 shadow-md">
                    <tr>
                        <td colspan="3" class="sticky left-0 z-40 bg-slate-900 px-4 py-3 text-right uppercase tracking-wider text-xs border-r border-slate-800">
                            GRAND TOTAL PERIODE:
                        </td>
                        <td colspan="{{ count($dates) }}" class="px-2 py-3 text-center text-slate-400 text-xs">
                            — Akumulasi Seluruh SPG & Seluruh Outlet —
                        </td>
                        <td class="px-3 py-3 text-center font-bold text-amber-400 border-r border-slate-800">
                            {{ $grandTotals['total_hari_masuk'] }} Hari
                        </td>
                        <td class="px-3 py-3 text-center text-slate-400 border-r border-slate-800">-</td>
                        <td class="px-3 py-3 text-right font-mono border-r border-slate-800">
                            {{ number_format($grandTotals['uang_makan_utama'], 0, ',', '.') }}
                        </td>
                        <td class="px-3 py-3 text-right font-mono border-r border-slate-800">
                            {{ number_format($grandTotals['uang_makan_tambahan'], 0, ',', '.') }}
                        </td>
                        <td class="px-3 py-3 text-right font-mono border-r border-slate-800">-</td>
                        <td class="px-3 py-3 text-center text-slate-400 border-r border-slate-800">-</td>
                        <td class="px-3 py-3 text-right font-mono border-r border-slate-800">
                            {{ number_format($grandTotals['base_salary_final'], 0, ',', '.') }}
                        </td>
                        <td class="px-3 py-3 text-right font-mono border-r border-slate-800">
                            {{ number_format($grandTotals['uang_pulsa'], 0, ',', '.') }}
                        </td>
                        <td class="px-3 py-3 text-right font-mono border-r border-slate-800">
                            {{ number_format($grandTotals['uang_tambahan'], 0, ',', '.') }}
                        </td>
                        <td class="px-4 py-3 text-right font-mono font-extrabold text-emerald-400 text-sm">
                            Rp {{ number_format($grandTotals['total_salary'], 0, ',', '.') }}
                        </td>
                    </tr>
                </tfoot>

            </table>
        </div>

    </div>

    <!-- TAB 2: Verifikasi Outlet (Per Tanggal Per Outlet) -->
    <div x-show="activeTab === 'outlet_verification'" class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
        <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-200 flex flex-wrap items-center justify-between text-[11px] text-slate-600 gap-2">
            <div>
                Menampilkan daftar SPG yang bertugas di masing-masing outlet pada setiap tanggal (21 s/d 20).
            </div>
            <div class="text-orange-700 font-semibold">
                Verifikasi rotasi outlet harian &bull; Deteksi outlet tanpa SPG jaga
            </div>
        </div>

        <div class="overflow-x-auto custom-scrollbar relative max-h-[72vh]">
            <table class="w-full text-xs text-left border-collapse whitespace-nowrap">
                <thead class="sticky top-0 z-30 bg-slate-900 text-white font-bold shadow-xs">
                    <tr>
                        <th class="sticky left-0 z-40 bg-slate-900 px-3 py-3 w-10 text-center border-b border-r border-slate-800">NO</th>
                        <th class="sticky left-10 z-40 bg-slate-900 px-3 py-3 min-w-[100px] border-b border-r border-slate-800">KODE OUTLET</th>
                        <th class="sticky left-[140px] z-40 bg-slate-900 px-3 py-3 min-w-[180px] border-b border-r border-slate-700 shadow-[2px_0_5px_rgba(0,0,0,0.15)]">NAMA OUTLET</th>
                        @foreach ($dates as $d)
                            <th class="px-2 py-2 text-center min-w-[110px] border-b border-r border-slate-800 {{ $d->isSunday() ? 'bg-orange-950 text-orange-200' : 'bg-slate-900' }}">
                                <div class="text-[11px] font-bold">{{ $d->format('d') }}</div>
                                <div class="text-[9px] font-normal text-slate-400">{{ substr($d->format('D'), 0, 2) }}</div>
                            </th>
                        @endforeach
                        <th class="px-4 py-3 text-right bg-orange-950 text-orange-300 font-extrabold border-b border-slate-700 min-w-[140px]">
                            TOTAL HARI JAGA
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @php $sNo = 1; @endphp
                    @foreach ($verificationGrid as $vRow)
                        @php
                            $outletTotalDays = 0;
                            foreach ($vRow['days'] as $dVal) {
                                if (($dVal['spg_count'] ?? 0) > 0) $outletTotalDays++;
                            }
                        @endphp
                        <tr class="hover:bg-slate-50 transition-colors group">
                            <td class="sticky left-0 z-20 bg-white group-hover:bg-slate-50 px-3 py-2 text-center text-slate-400 font-medium border-r border-slate-200">
                                {{ $sNo++ }}
                            </td>
                            <td class="sticky left-10 z-20 bg-white group-hover:bg-slate-50 px-3 py-2 font-mono font-bold text-slate-900 border-r border-slate-200">
                                <span class="px-2 py-0.5 rounded bg-slate-100 border border-slate-200 text-slate-800">
                                    {{ $vRow['outlet_code'] }}
                                </span>
                            </td>
                            <td class="sticky left-[140px] z-20 bg-white group-hover:bg-slate-50 px-3 py-2 text-slate-700 font-medium border-r border-slate-200 shadow-[2px_0_5px_rgba(0,0,0,0.04)]">
                                {{ $vRow['outlet_name'] }}
                            </td>
                            @foreach ($dates as $d)
                                @php
                                    $ymd = $d->format('Y-m-d');
                                    $dayData = $vRow['days'][$ymd] ?? null;
                                    $spgs = $dayData['spgs'] ?? [];
                                    $spgCount = count($spgs);
                                    $dailyVal = (float)($dayData['daily_value'] ?? 0);
                                @endphp
                                <td class="px-1.5 py-1.5 border-r border-slate-100 text-center">
                                    @if ($spgCount > 0)
                                        <div class="p-1 rounded bg-blue-50/90 border border-blue-200 text-left">
                                            <div class="flex flex-wrap gap-0.5">
                                                @foreach ($spgs as $spgN)
                                                    <span class="text-[9.5px] px-1 rounded bg-blue-200/90 text-blue-950 font-bold">
                                                        {{ $spgN }}
                                                    </span>
                                                @endforeach
                                            </div>
                                            @if ($dailyVal > 0)
                                                <div class="font-mono text-[9px] text-slate-500 mt-0.5">
                                                    U.Makan: Rp {{ number_format($dailyVal, 0, ',', '.') }}
                                                </div>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-slate-300 font-mono text-[11px]">-</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="px-4 py-2 text-right font-mono font-bold text-slate-900 bg-slate-50/50">
                                {{ $outletTotalDays }} Hari
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
                    <span class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center font-bold text-sm">
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
                        Pilih file Excel (.xlsx / .xls). Sistem akan <strong>secara otomatis</strong> memetakan nama SPG, mendeteksi kode outlet harian, dan menghitung nominal tanpa perlu pemetaan manual.
                    </p>

                    <div class="border-2 border-dashed border-slate-200 hover:border-orange-500 rounded-2xl p-6 text-center transition cursor-pointer bg-slate-50/50">
                        <input type="file" name="excel_file" accept=".xlsx,.xls" required class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-orange-50 file:text-orange-700 hover:file:bg-orange-100">
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                    <button type="button" @click="uploadModalOpen = false" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-xl">Batal</button>
                    <button type="submit" class="px-5 py-2.5 bg-orange-600 hover:bg-orange-700 text-white rounded-xl text-xs font-bold shadow-md shadow-orange-600/20">
                        Upload & Impor Otomatis
                    </button>
                </div>
            </form>

        </div>
    </div>

    <!-- Vertical Slip Card Modal -->
    <div x-show="slipModalOpen" 
         x-cloak 
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        
        <div @click.outside="slipModalOpen = false" 
             class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-100 relative max-h-[92vh] flex flex-col">
            
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-3">
                <h3 class="text-sm font-bold text-slate-900">Rincian Slip Gaji SPG (Vertical Breakdown)</h3>
                <button @click="slipModalOpen = false" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
            </div>

            <div class="overflow-y-auto custom-scrollbar flex-1 pr-1" id="slip-modal-content">
                <div class="p-8 text-center text-slate-400 text-xs">
                    Memuat rincian slip gaji SPG...
                </div>
            </div>

            <div class="pt-3 border-t border-slate-100 flex items-center justify-between mt-3">
                <button type="button" onclick="window.print()" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold flex items-center gap-1.5">
                    🖨️ Cetak Slip
                </button>
                <button type="button" @click="slipModalOpen = false" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-xs font-semibold">Tutup</button>
            </div>

        </div>
    </div>

    <!-- Inline Cell Edit Popover Modal (Outlet + Custom Daily Nominal Override + Update Bank Data) -->
    <div x-show="cellModal.open"
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.outside="cellModal.open = false"
             class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-100 relative">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                <div>
                    <h3 class="text-sm font-bold text-slate-900">Edit Sel Harian Salary (Gaji)</h3>
                    <p class="text-xs text-slate-500">SPG: <strong x-text="cellModal.spgName"></strong> &bull; Tanggal: <strong x-text="cellModal.dateFormatted"></strong></p>
                </div>
                <button @click="cellModal.open = false" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Kode Outlet Kerja (atau OFF `-`):</label>
                    <select x-model="cellModal.outletCode" class="w-full rounded-xl border-slate-300 text-xs font-semibold py-2 px-3 focus:ring-orange-500 focus:border-orange-500">
                        <option value="-">-- OFF / LIBUR (-) --</option>
                        @foreach ($allOutlets as $o)
                            <option value="{{ $o->outlet_code }}">{{ $o->outlet_code }} — {{ $o->outlet_name }} (U.Makan: Rp {{ number_format($o->uang_makan_utama, 0, ',', '.') }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Custom Nominal Uang Makan Harian (Opsional - Rp):</label>
                    <input type="number" step="100" min="0" x-model="cellModal.customNominal" placeholder="Kosongkan untuk mengikuti tarif baku outlet x multiplier"
                           class="w-full rounded-xl border-slate-300 text-xs py-2 px-3 focus:ring-orange-500 focus:border-orange-500">
                    <p class="text-[10px] text-slate-400 mt-1">Jika diisi angka, maka nominal uang makan tanggal ini akan menggunakan nilai custom ini.</p>
                </div>

                <label class="flex items-center gap-2 p-3 rounded-xl bg-orange-50/70 border border-orange-200 cursor-pointer select-none">
                    <input type="checkbox" x-model="cellModal.updateBankData" class="rounded border-orange-300 text-orange-600 focus:ring-orange-500">
                    <div class="text-xs">
                        <span class="font-bold text-orange-900">Update Bank Data Bulanan juga</span>
                        <span class="block text-[10px] text-orange-700">Simpan perubahan kode outlet tanggal ini ke kalender utama Bank Data Bulanan</span>
                    </div>
                </label>
            </div>

            <div class="flex justify-end gap-2 pt-4 mt-4 border-t border-slate-100">
                <button type="button" @click="cellModal.open = false" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-xl">Batal</button>
                <button type="button" @click="saveCellModal()" class="px-5 py-2 bg-orange-600 hover:bg-orange-700 text-white rounded-xl text-xs font-bold shadow-md shadow-orange-600/20">
                    Simpan Perubahan Sel
                </button>
            </div>
        </div>
    </div>

    <!-- 2-Step Confirmation Modal for Clearing Task Data -->
    <div x-show="clearDataModal.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4">
        <div @click.outside="clearDataModal.open = false" class="bg-white rounded-3xl shadow-2xl border border-slate-200 w-full max-w-md p-6">
            <div x-show="clearDataModal.step === 1">
                <div class="flex items-center gap-3 pb-3 border-b border-slate-100">
                    <div class="w-11 h-11 rounded-2xl bg-amber-100 text-amber-600 flex items-center justify-center text-xl shrink-0">⚠️</div>
                    <div>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-amber-100 text-amber-800 border border-amber-300">PERINGATAN KONFIRMASI 1 DARI 2</span>
                        <h3 class="text-base font-bold text-slate-900 mt-1">Kosongkan Data Presensi Task Ini?</h3>
                    </div>
                </div>
                <div class="mt-4 p-3.5 rounded-2xl bg-amber-50 border border-amber-200 text-xs text-amber-900 space-y-1.5">
                    <p class="font-semibold">Anda akan menghapus seluruh data kehadiran harian yang sudah diupload pada task <strong>{{ $task->title }}</strong>.</p>
                    <p class="text-amber-700">Apakah Anda yakin ingin melanjutkan ke konfirmasi tahap kedua?</p>
                </div>
                <div class="mt-6 flex items-center justify-end gap-2">
                    <button type="button" @click="clearDataModal.open = false" class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-50 cursor-pointer">Batal</button>
                    <button type="button" @click="clearDataModal.step = 2" class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold shadow-sm cursor-pointer">Ya, Lanjut Konfirmasi Kedua (1/2) &rarr;</button>
                </div>
            </div>

            <div x-show="clearDataModal.step === 2" x-cloak>
                <div class="flex items-center gap-3 pb-3 border-b border-red-100">
                    <div class="w-11 h-11 rounded-2xl bg-red-100 text-red-600 flex items-center justify-center text-xl shrink-0 animate-bounce">🚨</div>
                    <div>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-red-100 text-red-800 border border-red-300">PERINGATAN TERAKHIR 2 DARI 2</span>
                        <h3 class="text-base font-bold text-red-700 mt-1">Konfirmasi Penghapusan Data Task</h3>
                    </div>
                </div>
                <div class="mt-4 p-3.5 rounded-2xl bg-red-50 border-2 border-red-300 text-xs text-red-900 space-y-1.5">
                    <p class="font-bold text-red-800">PERHATIAN: Seluruh sel tanggal pada task ini akan di-reset ke OFF (-) dan perhitungan kembali ke Rp 0!</p>
                </div>
                <form method="POST" action="{{ route('tasks.clear_data', $task->id) }}" class="mt-6 flex items-center justify-between gap-2">
                    @csrf
                    @method('DELETE')
                    <button type="button" @click="clearDataModal.open = false; clearDataModal.step = 1" class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-50 cursor-pointer">Batalkan</button>
                    <button type="submit" class="px-4 py-2.5 rounded-xl bg-red-600 hover:bg-red-700 text-white text-xs font-extrabold shadow-md shadow-red-600/25 cursor-pointer">🗑️ YA, HAPUS DATA SEKARANG (2/2)</button>
                </form>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
function salaryMatrixWorkspace(config) {
    return {
        activeTab: 'spg_matrix',
        uploadModalOpen: false,
        slipModalOpen: false,
        hideZeroAttendance: true,
        searchFilter: '',
        rowHariMap: {},
        clearDataModal: {
            open: false,
            step: 1
        },
        cellModal: {
            open: false,
            recordId: null,
            spgName: '',
            dateStr: '',
            dateFormatted: '',
            outletCode: '-',
            customNominal: '',
            updateBankData: false
        },

        shouldShowRow(spgNameLower, recordId, initialHari) {
            const currentHari = (this.rowHariMap[recordId] !== undefined) ? this.rowHariMap[recordId] : initialHari;
            if (this.hideZeroAttendance && currentHari <= 0) {
                return false;
            }
            if (this.searchFilter.trim() !== '' && !spgNameLower.includes(this.searchFilter.trim().toLowerCase())) {
                return false;
            }
            return true;
        },

        openCellModal(recordId, spgName, dateStr, dateFormatted) {
            const inputEl = document.getElementById(`cell-input-${recordId}-${dateStr}`);
            const currentCode = inputEl ? inputEl.value : '-';
            const currentCustom = inputEl ? (inputEl.dataset.customNominal || '') : '';
            this.cellModal = {
                open: true,
                recordId,
                spgName,
                dateStr,
                dateFormatted,
                outletCode: (currentCode && currentCode !== '') ? currentCode : '-',
                customNominal: currentCustom,
                updateBankData: false
            };
        },

        async saveCellModal() {
            await this.updateCell(
                this.cellModal.recordId,
                this.cellModal.dateStr,
                this.cellModal.outletCode,
                this.cellModal.customNominal,
                this.cellModal.updateBankData
            );
            this.cellModal.open = false;
        },

        async updateCell(recordId, dateStr, val, customNominal = null, updateBankData = false) {
            try {
                const payload = {
                    record_id: recordId,
                    date: dateStr,
                    raw_value: val,
                    update_bank_data: updateBankData
                };
                if (customNominal !== null) {
                    payload.custom_nominal_override = customNominal;
                }
                const res = await fetch(`/tasks/${config.taskId}/cell`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': config.csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.success) {
                    this.rowHariMap[recordId] = data.record.total_hari_masuk;
                    this.updateCellDOM(recordId, dateStr, data.log);
                    this.updateRowDOM(data.record);
                    this.updateOutletsSummaryDOM(recordId, data.outlets_summary);
                } else if (data.error) {
                    alert(data.error);
                }
            } catch (err) {
                console.error(err);
            }
        },

        updateCellDOM(recordId, dateStr, log) {
            const formatRp = (n) => new Intl.NumberFormat('id-ID').format(Math.round(n));
            const inputEl = document.getElementById(`cell-input-${recordId}-${dateStr}`);
            const nomEl = document.getElementById(`cell-nom-${recordId}-${dateStr}`);
            const boxEl = document.getElementById(`cell-box-${recordId}-${dateStr}`);

            const isPresent = Boolean(log.is_present);
            const code = isPresent ? (log.outlet_code || log.raw_cell_value || 'OK') : '-';
            const nom = (Number(log.daily_uang_makan_utama) || 0) + (Number(log.daily_uang_makan_tambahan) || 0);

            if (inputEl) {
                inputEl.value = code;
                inputEl.dataset.customNominal = (log.custom_nominal_override !== null && log.custom_nominal_override !== undefined) ? log.custom_nominal_override : '';
                inputEl.classList.toggle('text-blue-900', isPresent);
                inputEl.classList.toggle('text-slate-300', !isPresent);
            }
            if (nomEl) {
                nomEl.innerText = (isPresent && nom > 0) ? formatRp(nom) : '';
            }
            if (boxEl) {
                boxEl.classList.toggle('bg-blue-50/80', isPresent);
                boxEl.classList.toggle('border', isPresent);
                boxEl.classList.toggle('border-blue-100', isPresent);
            }
        },

        async updateReferenceOutlet(recordId, outletId) {
            try {
                const res = await fetch(`/tasks/${config.taskId}/reference-outlet/${recordId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': config.csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        outlet_id: outletId
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

        async updateMultiplier(dateStr, multVal) {
            try {
                const res = await fetch(`/tasks/${config.taskId}/multiplier`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': config.csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        date: dateStr,
                        multiplier: multVal,
                        note: multVal == 2 ? 'BESAR' : null
                    })
                });
                const data = await res.json();
                if (data.success) {
                    window.location.reload();
                }
            } catch (err) {
                console.error(err);
            }
        },

        async togglePolicy(recordId, isChecked) {
            try {
                const res = await fetch(`/tasks/${config.taskId}/policy/${recordId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': config.csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        policy_override: isChecked
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

        updateOutletsSummaryDOM(recordId, summary) {
            const container = document.getElementById(`outlets-summary-${recordId}`);
            if (!container) return;
            if (!summary || !summary.length) {
                container.innerHTML = '<span class="text-slate-400 text-[10px]">-</span>';
                return;
            }
            container.innerHTML = summary.map(os => 
                `<span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-mono font-bold bg-slate-100 text-slate-800 border border-slate-200">` +
                `${os.code} <span class="text-orange-600 font-extrabold">${os.days}d</span></span>`
            ).join('');
        },

        updateRowDOM(rec) {
            const formatRp = (n) => new Intl.NumberFormat('id-ID').format(n);

            const elHari = document.getElementById(`val-hari-${rec.id}`);
            if (elHari) elHari.innerText = rec.total_hari_masuk;

            const elAbsen = document.getElementById(`val-absen-${rec.id}`);
            if (elAbsen) {
                if (rec.attendance_percentage < 40) {
                    elAbsen.innerHTML = `<span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-red-100 text-red-700 border border-red-200">` + rec.attendance_percentage + `% ⚠️</span>`;
                } else {
                    elAbsen.innerHTML = `<span class="text-emerald-700 font-medium">` + rec.attendance_percentage + `%</span>`;
                }
            }

            const elUtama = document.getElementById(`val-makan-utama-${rec.id}`);
            if (elUtama) elUtama.innerText = formatRp(rec.uang_makan_utama);

            const elTambahan = document.getElementById(`val-makan-tambahan-${rec.id}`);
            if (elTambahan) elTambahan.innerText = formatRp(rec.uang_makan_tambahan);

            const elPokokRek = document.getElementById(`val-pokok-rek-${rec.id}`);
            if (elPokokRek) elPokokRek.innerText = formatRp(rec.base_salary_recommended);

            const elPokokFinal = document.getElementById(`val-pokok-final-${rec.id}`);
            if (elPokokFinal) elPokokFinal.innerText = formatRp(rec.base_salary_final);

            const elPulsa = document.getElementById(`val-pulsa-${rec.id}`);
            if (elPulsa) elPulsa.innerText = formatRp(rec.uang_pulsa);

            const elTotalSalary = document.getElementById(`val-total-salary-${rec.id}`);
            if (elTotalSalary) elTotalSalary.innerText = 'Rp ' + formatRp(rec.total_salary);
        },

        async openSlipModal(recordId) {
            this.slipModalOpen = true;
            const container = document.getElementById('slip-modal-content');
            container.innerHTML = '<div class="p-8 text-center text-slate-400 text-xs">Memuat data slip...</div>';

            try {
                const res = await fetch(`/slips/${recordId}`);
                const html = await res.text();
                container.innerHTML = html;
            } catch (e) {
                container.innerHTML = '<div class="p-4 text-center text-red-500 text-xs">Gagal memuat slip.</div>';
            }
        }
    };
}
</script>
@endpush
