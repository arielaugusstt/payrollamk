@extends('layouts.app', ['title' => 'Task Hub Workspace &bull; Kucing Oren Payroll'])

@section('content')
<div class="space-y-6" x-data="{ createModalOpen: false, taskType: 'SALARY', selectedMonth: 12, selectedYear: 2026,
    get periodPreview() {
        const monthNames = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        const m = parseInt(this.selectedMonth);
        const y = parseInt(this.selectedYear);

        if (this.taskType === 'SALARY') {
            // Month - 2 and Month - 1
            let startM = m - 2;
            let startY = y;
            if (startM < 1) { startM += 12; startY -= 1; }

            let endM = m - 1;
            let endY = y;
            if (endM < 1) { endM += 12; endY -= 1; }

            return `21 ${monthNames[startM]} ${startY} – 20 ${monthNames[endM]} ${endY}`;
        } else {
            // Month - 1 full month
            let targetM = m - 1;
            let targetY = y;
            if (targetM < 1) { targetM += 12; targetY -= 1; }

            const lastDay = new Date(targetY, targetM, 0).getDate();
            return `1 ${monthNames[targetM]} ${targetY} – ${lastDay} ${monthNames[targetM]} ${targetY}`;
        }
    }
}">

    <!-- Top Banner & Action Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-200 shadow-xs">
        <div>
            <div class="flex items-center gap-2">
                <span class="p-2 bg-orange-100 text-orange-600 rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                </span>
                <div>
                    <h1 class="text-xl font-bold text-slate-900">Task Hub Workspace</h1>
                    <p class="text-xs text-slate-500">Ruang kerja terstruktur payroll SPG dengan persistensi draft & auto period mapping</p>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('bank-data.index') }}"
               class="px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-sm font-semibold shadow-sm flex items-center gap-2 transition">
                <svg class="w-4 h-4 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/>
                </svg>
                Bank Data Bulanan (Presensi & Omzet)
            </a>

            <button @click="createModalOpen = true" 
                    class="px-4 py-2.5 bg-orange-600 hover:bg-orange-700 text-white rounded-xl text-sm font-semibold shadow-md shadow-orange-600/20 flex items-center gap-2 transition active:scale-95 cursor-pointer">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Buat Task Payroll Baru
            </button>
        </div>
    </div>

    <!-- Summary Stats Metric Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-slate-500">Task In Progress (Draft)</p>
                <p class="text-2xl font-bold text-amber-600 mt-1">{{ $totalDraft }}</p>
                <p class="text-[11px] text-slate-400 mt-0.5">Dapat diedit & disimpan bertahap</p>
            </div>
            <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold">
                ⏳
            </div>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-slate-500">Task Selesai (Completed)</p>
                <p class="text-2xl font-bold text-emerald-600 mt-1">{{ $totalFinished }}</p>
                <p class="text-[11px] text-slate-400 mt-0.5">Terkunci & masuk ke Dashboard</p>
            </div>
            <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">
                ✅
            </div>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-slate-500">Task Salary (Gaji)</p>
                <p class="text-2xl font-bold text-blue-600 mt-1">{{ $totalSalaryTasks }}</p>
                <p class="text-[11px] text-slate-400 mt-0.5">Periode tgl 21 – 20</p>
            </div>
            <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold">
                💼
            </div>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-slate-500">Task Insentif (Omzet)</p>
                <p class="text-2xl font-bold text-purple-600 mt-1">{{ $totalIncentiveTasks }}</p>
                <p class="text-[11px] text-slate-400 mt-0.5">Periode tgl 1 – akhir bulan</p>
            </div>
            <div class="w-12 h-12 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center font-bold">
                📈
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="bg-white p-3 rounded-2xl border border-slate-200 shadow-xs flex flex-wrap items-center justify-between gap-3 text-xs">
        <div class="flex flex-wrap items-center gap-2">
            <span class="font-semibold text-slate-700 px-2">Filter:</span>

            <!-- Filter Type -->
            <a href="{{ route('tasks.index', array_merge(request()->query(), ['type' => ''])) }}" 
               class="px-3 py-1.5 rounded-lg font-medium transition {{ empty($typeFilter) ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                Semua Tipe
            </a>
            <a href="{{ route('tasks.index', array_merge(request()->query(), ['type' => 'SALARY'])) }}" 
               class="px-3 py-1.5 rounded-lg font-medium transition {{ $typeFilter === 'SALARY' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                Salary Saja
            </a>
            <a href="{{ route('tasks.index', array_merge(request()->query(), ['type' => 'INSENTIF'])) }}" 
               class="px-3 py-1.5 rounded-lg font-medium transition {{ $typeFilter === 'INSENTIF' ? 'bg-purple-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                Insentif Saja
            </a>

            <div class="h-4 w-px bg-slate-200 mx-1"></div>

            <!-- Filter Status -->
            <a href="{{ route('tasks.index', array_merge(request()->query(), ['status' => ''])) }}" 
               class="px-3 py-1.5 rounded-lg font-medium transition {{ empty($statusFilter) ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                Semua Status
            </a>
            <a href="{{ route('tasks.index', array_merge(request()->query(), ['status' => 'DRAFT'])) }}" 
               class="px-3 py-1.5 rounded-lg font-medium transition {{ $statusFilter === 'DRAFT' ? 'bg-amber-500 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                Draft / In Progress
            </a>
            <a href="{{ route('tasks.index', array_merge(request()->query(), ['status' => 'FINISHED'])) }}" 
               class="px-3 py-1.5 rounded-lg font-medium transition {{ $statusFilter === 'FINISHED' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                Finished
            </a>
        </div>

        <!-- Filter Year -->
        <div class="flex items-center gap-2">
            <span class="text-slate-500">Tahun:</span>
            <select onchange="location = this.value;" class="px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs font-semibold focus:outline-none">
                <option value="{{ route('tasks.index', array_merge(request()->query(), ['year' => ''])) }}">Semua Tahun</option>
                @foreach ($availableYears as $yr)
                    <option value="{{ route('tasks.index', array_merge(request()->query(), ['year' => $yr])) }}" {{ (string)$yearFilter === (string)$yr ? 'selected' : '' }}>
                        Tahun {{ $yr }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- Task Cards Grid -->
    @if ($tasks->isEmpty())
        <div class="bg-white rounded-3xl p-12 text-center border border-dashed border-slate-300">
            <div class="w-16 h-16 rounded-2xl bg-orange-50 text-orange-500 mx-auto flex items-center justify-center text-3xl mb-3">
                📂
            </div>
            <h3 class="text-base font-bold text-slate-800">Belum Ada Task Payroll</h3>
            <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">Klik tombol di atas untuk membuat task baru, lalu upload file excel atau masukkan data presensi & omzet.</p>
            <button @click="createModalOpen = true" class="mt-4 px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white rounded-xl text-xs font-semibold shadow-xs">
                + Buat Task Pertama
            </button>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach ($tasks as $task)
                @php
                    $isFinished = $task->isFinished();
                    $isSalary = $task->isSalary();
                    $cardBorder = $isFinished ? 'border-emerald-300 hover:border-emerald-400 bg-white' : 'border-amber-200 hover:border-amber-300 bg-gradient-to-b from-white to-amber-50/30';
                @endphp

                <div class="rounded-2xl border {{ $cardBorder }} p-5 shadow-xs hover:shadow-md transition-all flex flex-col justify-between relative group">
                    
                    <!-- Card Top Header -->
                    <div>
                        <div class="flex items-start justify-between gap-2 mb-3">
                            <span class="px-2.5 py-1 rounded-lg text-xs font-bold tracking-wide {{ $isSalary ? 'bg-blue-100 text-blue-800 border border-blue-200' : 'bg-purple-100 text-purple-800 border border-purple-200' }}">
                                {{ $task->task_type }}
                            </span>

                            @if ($isFinished)
                                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300 flex items-center gap-1">
                                    <span class="w-2 h-2 rounded-full bg-emerald-600"></span> FINISHED
                                </span>
                            @else
                                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-100 text-amber-800 border border-amber-300 flex items-center gap-1">
                                    <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span> IN PROGRESS
                                </span>
                            @endif
                        </div>

                        <h3 class="text-base font-bold text-slate-900 group-hover:text-orange-600 transition-colors">
                            {{ $task->title }}
                        </h3>

                        <!-- Period Details Badge -->
                        <div class="mt-2.5 p-2.5 bg-slate-50 rounded-xl border border-slate-100 space-y-1 text-xs">
                            <div class="flex items-center justify-between text-slate-600">
                                <span class="text-slate-400 font-medium">Bulan Penggajian:</span>
                                <span class="font-bold text-slate-800">{{ $task->period_label }}</span>
                            </div>
                            <div class="flex items-center justify-between text-slate-600">
                                <span class="text-slate-400 font-medium">Rentang Tanggal:</span>
                                <span class="font-semibold text-slate-700">{{ $task->start_date->format('d M Y') }} – {{ $task->end_date->format('d M Y') }}</span>
                            </div>
                            <div class="flex items-center justify-between text-slate-600">
                                <span class="text-slate-400 font-medium">Jumlah SPG:</span>
                                <span class="font-bold text-slate-800">
                                    {{ $isSalary ? $task->salary_records_count : $task->incentive_records_count }} Orang
                                </span>
                            </div>
                        </div>

                        <!-- Bank Data Readiness Badges -->
                        @php
                            $readiness = $taskReadinessMap[$task->id] ?? ['is_ready' => true, 'required_periods' => [], 'missing' => []];
                        @endphp
                        <div class="mt-2.5 flex flex-wrap items-center gap-1.5">
                            @foreach($readiness['required_periods'] as $reqItem)
                                <a href="{{ route('bank-data.index', ['year' => $reqItem['year'], 'month' => $reqItem['month'], 'tab' => strtolower($reqItem['type'])]) }}"
                                   class="px-2 py-0.5 rounded-md text-[10px] font-semibold border transition flex items-center gap-1 {{ $reqItem['is_ready'] ? 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100' : 'bg-red-50 text-red-700 border-red-200 hover:bg-red-100' }}">
                                    <span>{{ $reqItem['is_ready'] ? '✓' : '⚠ Upload:' }}</span>
                                    <span>{{ $reqItem['label'] }}</span>
                                </a>
                            @endforeach
                        </div>

                        @if ($task->notes)
                            <p class="text-xs text-slate-500 mt-2 line-clamp-1 italic">"{{ $task->notes }}"</p>
                        @endif
                    </div>

                    <!-- Card Actions Footer -->
                    <div class="mt-5 pt-4 border-t border-slate-100 flex items-center justify-between gap-2">
                        <div class="text-[11px] text-slate-400">
                            @if ($isFinished)
                                Selesai: {{ $task->finished_at ? $task->finished_at->format('d/m/Y') : '-' }}
                            @else
                                Update: {{ $task->updated_at->diffForHumans() }}
                            @endif
                        </div>

                        <div class="flex items-center gap-1.5">
                            <!-- Delete Task Form -->
                            <form method="POST" action="{{ route('tasks.destroy', $task->id) }}" onsubmit="return confirm('Apakah Anda yakin ingin menghapus task ini? Data matrix akan terhapus.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="p-2 text-slate-400 hover:text-red-600 rounded-lg hover:bg-red-50 transition" title="Hapus Task">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </form>

                            <!-- Open Matrix Workspace Button -->
                            <a href="{{ route('tasks.show', $task->id) }}" 
                               class="px-3 py-1.5 rounded-xl text-xs font-semibold flex items-center gap-1.5 transition {{ $isFinished ? 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs' : 'bg-amber-500 hover:bg-amber-600 text-white shadow-xs' }}">
                                <span>Buka Matrix</span>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                </svg>
                            </a>
                        </div>
                    </div>

                </div>
            @endforeach
        </div>
    @endif

    <!-- Create Task Modal -->
    <div x-show="createModalOpen" 
         x-cloak 
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        
        <div @click.outside="createModalOpen = false" 
             class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-100 relative">
            
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-5">
                <div class="flex items-center gap-2">
                    <span class="w-8 h-8 rounded-xl bg-orange-100 text-orange-600 flex items-center justify-center font-bold text-sm">
                        ➕
                    </span>
                    <h3 class="text-base font-bold text-slate-900">Buat Task Payroll Baru</h3>
                </div>
                <button @click="createModalOpen = false" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
            </div>

            <form action="{{ route('tasks.store') }}" method="POST">
                @csrf

                <!-- Task Type Selection -->
                <div class="mb-4">
                    <label class="block text-xs font-semibold text-slate-700 mb-2">Tipe Task Perhitungan</label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="border-2 rounded-xl p-3 cursor-pointer transition flex items-center gap-3"
                               :class="taskType === 'SALARY' ? 'border-blue-600 bg-blue-50/50' : 'border-slate-200 hover:bg-slate-50'">
                            <input type="radio" name="task_type" value="SALARY" x-model="taskType" class="text-blue-600 focus:ring-blue-500">
                            <div>
                                <p class="text-xs font-bold text-slate-900">SALARY (Gaji)</p>
                                <p class="text-[11px] text-slate-500">Tgl 21 – 20 (Multipliers 1x/2x/3x)</p>
                            </div>
                        </label>

                        <label class="border-2 rounded-xl p-3 cursor-pointer transition flex items-center gap-3"
                               :class="taskType === 'INSENTIF' ? 'border-purple-600 bg-purple-50/50' : 'border-slate-200 hover:bg-slate-50'">
                            <input type="radio" name="task_type" value="INSENTIF" x-model="taskType" class="text-purple-600 focus:ring-purple-500">
                            <div>
                                <p class="text-xs font-bold text-slate-900">INSENTIF (Omzet)</p>
                                <p class="text-[11px] text-slate-500">Tgl 1 – akhir bulan (Tiered)</p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Target Month & Year -->
                <div class="grid grid-cols-2 gap-3 mb-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Target Bulan Penggajian</label>
                        <select name="period_month" x-model="selectedMonth" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-orange-500">
                            <option value="1">Januari</option>
                            <option value="2">Februari</option>
                            <option value="3">Maret</option>
                            <option value="4">April</option>
                            <option value="5">Mei</option>
                            <option value="6">Juni</option>
                            <option value="7">Juli</option>
                            <option value="8">Agustus</option>
                            <option value="9">September</option>
                            <option value="10">Oktober</option>
                            <option value="11">November</option>
                            <option value="12">Desember</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Tahun Penggajian</label>
                        <select name="period_year" x-model="selectedYear" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-orange-500">
                            <option value="2025">2025</option>
                            <option value="2026">2026</option>
                            <option value="2027">2027</option>
                            <option value="2028">2028</option>
                        </select>
                    </div>
                </div>

                <!-- Automatic Working Period Calculation Info Box -->
                <div class="mb-5 p-3.5 bg-orange-50/70 border border-orange-200 rounded-2xl">
                    <div class="flex items-center gap-2 text-xs font-bold text-orange-900 mb-1">
                        <span>🗓️</span>
                        <span>Pemetaan Rentang Tanggal Otomatis:</span>
                    </div>
                    <p class="text-sm font-extrabold text-orange-800" x-text="periodPreview"></p>
                    <p class="text-[11px] text-orange-700/80 mt-1">
                        <span x-show="taskType === 'SALARY'">Sesuai standar bisnis: tgl 21 dua bulan sebelumnya s/d tgl 20 bulan sebelumnya.</span>
                        <span x-show="taskType === 'INSENTIF'">Sesuai standar bisnis: omzet tgl 1 s/d akhir bulan satu bulan sebelumnya.</span>
                    </p>
                </div>

                <!-- Notes Input -->
                <div class="mb-5">
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Catatan Tambahan (Opsional)</label>
                    <input type="text" name="notes" placeholder="Contoh: Periode libur tahun baru / Idul Fitri" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-orange-500">
                </div>

                <!-- Footer Actions -->
                <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                    <button type="button" @click="createModalOpen = false" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-xl">Batal</button>
                    <button type="submit" class="px-5 py-2.5 bg-orange-600 hover:bg-orange-700 text-white rounded-xl text-xs font-bold shadow-md shadow-orange-600/20">Buat & Buka Task Matrix</button>
                </div>
            </form>

        </div>
    </div>

</div>
@endsection
