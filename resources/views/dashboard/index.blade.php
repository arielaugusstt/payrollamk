@extends('layouts.app', ['title' => 'Executive & Annual Dashboard &bull; Kucing Oren Payroll'])

@section('content')
<div class="space-y-6">

    <!-- Top Banner & Filters -->
    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <span class="p-2.5 bg-orange-100 text-orange-600 rounded-xl">
                    📊
                </span>
                <div>
                    <h1 class="text-xl font-bold text-slate-900">Executive & Annual Financial Dashboard</h1>
                    <p class="text-xs text-slate-500">Analisis Beban Penggajian & Insentif Perusahaan, Multi-Filter SPG/Outlet, dan Matriks Pendapatan Tahunan</p>
                </div>
            </div>
        </div>

        <!-- Export Annual Recap Button -->
        <div class="flex items-center gap-2">
            <a href="{{ route('export.annual', request()->query()) }}" 
               class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold shadow-xs flex items-center gap-1.5 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Export Matriks Tahunan (.xlsx)
            </a>
        </div>
    </div>

    <!-- Filter Bar Card -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs">
        <form method="GET" action="{{ route('dashboard.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4 text-xs">
            
            <!-- 1. Year Selector -->
            <div>
                <label class="block font-semibold text-slate-700 mb-1">Tahun Penggajian</label>
                <select name="year" onchange="this.form.submit()" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-bold focus:outline-none focus:ring-2 focus:ring-orange-500">
                    @foreach ($availableYears as $yr)
                        <option value="{{ $yr }}" {{ (int)$selectedYear === (int)$yr ? 'selected' : '' }}>
                            Tahun {{ $yr }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- 2. Component View Selector -->
            <div>
                <label class="block font-semibold text-slate-700 mb-1">Tampilan Komponen</label>
                <select name="component" onchange="this.form.submit()" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-semibold focus:outline-none focus:ring-2 focus:ring-orange-500">
                    <option value="combined" {{ $componentView === 'combined' ? 'selected' : '' }}>
                        Combined (Take-Home Pay: Salary + Insentif)
                    </option>
                    <option value="salary" {{ $componentView === 'salary' ? 'selected' : '' }}>
                        Salary (Gaji) Saja
                    </option>
                    <option value="insentif" {{ $componentView === 'insentif' ? 'selected' : '' }}>
                        Insentif (Omzet) Saja
                    </option>
                </select>
            </div>

            <!-- 3. Outlet Filter -->
            <div>
                <label class="block font-semibold text-slate-700 mb-1">Filter Berdasarkan Outlet</label>
                <select name="outlet_id" onchange="this.form.submit()" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-orange-500">
                    <option value="">Semua Outlet</option>
                    @foreach ($allOutlets as $out)
                        <option value="{{ $out->id }}" {{ (string)$outletFilter === (string)$out->id ? 'selected' : '' }}>
                            {{ $out->outlet_code }} ({{ $out->outlet_name }})
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- 4. SPG Multi-Select Filter -->
            <div>
                <label class="block font-semibold text-slate-700 mb-1">Pilih SPG Tertentu (Side-by-Side)</label>
                <div class="flex items-center gap-2">
                    <select name="spg_ids[]" multiple class="w-full px-2 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-[11px] focus:outline-none h-9">
                        @foreach ($allSpgs as $s)
                            @php $code = ($s->defaultOutlet ?? $s->assignedOutlet)?->outlet_code ?? '-'; @endphp
                            <option value="{{ $s->id }}" {{ in_array($s->id, (array)$spgFilter) ? 'selected' : '' }}>
                                {{ $s->spg_name }} ({{ $code }})
                            </option>
                        @endforeach
                    </select>
                    <button type="submit" class="px-3 py-2 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-xs font-semibold shrink-0">
                        Filter
                    </button>
                    @if (!empty($spgFilter) || $outletFilter)
                        <a href="{{ route('dashboard.index', ['year' => $selectedYear, 'component' => $componentView]) }}" class="text-slate-400 hover:text-red-500 font-bold px-1" title="Reset filter">
                            &times;
                        </a>
                    @endif
                </div>
            </div>

        </form>
    </div>

    <!-- Company Expense Summary Cards (Annual Accumulation) -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        
        <!-- Total Salary Expense Card -->
        <div class="bg-gradient-to-br from-blue-900 to-indigo-950 text-white p-6 rounded-3xl shadow-lg border border-blue-800/40 relative overflow-hidden">
            <div class="relative z-10">
                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-blue-500/20 text-blue-200 border border-blue-400/30 uppercase tracking-wider">
                    Total Salary Expense (Tahun {{ $selectedYear }})
                </span>
                <p class="text-2xl lg:text-3xl font-extrabold mt-3 font-mono">
                    Rp {{ number_format($cumulativeSalaryTotal, 0, ',', '.') }}
                </p>
                <p class="text-xs text-blue-200/80 mt-1">Akumulasi seluruh gaji pokok, uang makan & pulsa</p>
            </div>
            <div class="absolute -right-6 -bottom-6 w-32 h-32 bg-blue-500/10 rounded-full blur-2xl"></div>
        </div>

        <!-- Total Incentive Expense Card -->
        <div class="bg-gradient-to-br from-emerald-900 to-teal-950 text-white p-6 rounded-3xl shadow-lg border border-emerald-800/40 relative overflow-hidden">
            <div class="relative z-10">
                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-200 border border-emerald-400/30 uppercase tracking-wider">
                    Total Incentive Expense (Tahun {{ $selectedYear }})
                </span>
                <p class="text-2xl lg:text-3xl font-extrabold mt-3 font-mono">
                    Rp {{ number_format($cumulativeIncentiveTotal, 0, ',', '.') }}
                </p>
                <p class="text-xs text-emerald-200/80 mt-1">Akumulasi komisi penjualan omzet bertingkat</p>
            </div>
            <div class="absolute -right-6 -bottom-6 w-32 h-32 bg-emerald-500/10 rounded-full blur-2xl"></div>
        </div>

        <!-- Grand Total Outflow Card -->
        <div class="bg-gradient-to-br from-orange-600 via-amber-600 to-orange-700 text-white p-6 rounded-3xl shadow-lg border border-orange-500/40 relative overflow-hidden">
            <div class="relative z-10">
                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-white/20 text-white border border-white/30 uppercase tracking-wider">
                    Grand Total Payroll Outflow (Tahun {{ $selectedYear }})
                </span>
                <p class="text-2xl lg:text-3xl font-black mt-3 font-mono">
                    Rp {{ number_format($cumulativeGrandTotal, 0, ',', '.') }}
                </p>
                <p class="text-xs text-orange-100 mt-1">Total pengeluaran riil perusahaan (Salary + Insentif)</p>
            </div>
            <div class="absolute -right-6 -bottom-6 w-32 h-32 bg-white/10 rounded-full blur-2xl"></div>
        </div>

    </div>

    <!-- Monthly Trend Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-bold text-slate-900">Trend Pengeluaran Bulanan Tahun {{ $selectedYear }}</h2>
            <span class="text-xs text-slate-400">Data diambil dari Task dengan status FINISHED</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left border-collapse">
                <thead class="bg-slate-900 text-white font-bold">
                    <tr>
                        <th class="px-4 py-2.5">KOMPONEN BIAYA</th>
                        @php $months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']; @endphp
                        @foreach ($months as $m)
                            <th class="px-2.5 py-2.5 text-right font-mono">{{ $m }}</th>
                        @endforeach
                        <th class="px-4 py-2.5 text-right font-mono text-amber-300">TOTAL TAHUNAN</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-mono">
                    <!-- Salary Row -->
                    <tr class="hover:bg-slate-50 transition">
                        <td class="px-4 py-2.5 font-bold font-sans text-blue-900">
                            Total Gaji (Salary)
                        </td>
                        @for ($m = 1; $m <= 12; $m++)
                            <td class="px-2.5 py-2.5 text-right text-slate-700">
                                {{ $monthlySalaryExpense[$m] > 0 ? number_format($monthlySalaryExpense[$m], 0, ',', '.') : '-' }}
                            </td>
                        @endfor
                        <td class="px-4 py-2.5 text-right font-bold text-blue-950 bg-blue-50/50">
                            Rp {{ number_format($cumulativeSalaryTotal, 0, ',', '.') }}
                        </td>
                    </tr>

                    <!-- Incentive Row -->
                    <tr class="hover:bg-slate-50 transition">
                        <td class="px-4 py-2.5 font-bold font-sans text-emerald-900">
                            Total Insentif (Omzet)
                        </td>
                        @for ($m = 1; $m <= 12; $m++)
                            <td class="px-2.5 py-2.5 text-right text-slate-700">
                                {{ $monthlyIncentiveExpense[$m] > 0 ? number_format($monthlyIncentiveExpense[$m], 0, ',', '.') : '-' }}
                            </td>
                        @endfor
                        <td class="px-4 py-2.5 text-right font-bold text-emerald-950 bg-emerald-50/50">
                            Rp {{ number_format($cumulativeIncentiveTotal, 0, ',', '.') }}
                        </td>
                    </tr>

                    <!-- Combined Outflow Row -->
                    <tr class="bg-slate-100/80 font-bold border-t-2 border-slate-300">
                        <td class="px-4 py-3 font-black font-sans text-slate-900">
                            TOTAL OUTFLOW (Gaji + Insentif)
                        </td>
                        @for ($m = 1; $m <= 12; $m++)
                            <td class="px-2.5 py-3 text-right text-slate-900">
                                {{ $monthlyCombinedExpense[$m] > 0 ? number_format($monthlyCombinedExpense[$m], 0, ',', '.') : '-' }}
                            </td>
                        @endfor
                        <td class="px-4 py-3 text-right text-red-600 bg-red-100/50 text-sm">
                            Rp {{ number_format($cumulativeGrandTotal, 0, ',', '.') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Annual SPG Earnings Matrix Grid -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden flex flex-col">
        
        <div class="px-5 py-3.5 bg-slate-900 text-white flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-sm font-bold flex items-center gap-2">
                    <span>Matriks Pendapatan SPG Tahunan (Januari – Desember {{ $selectedYear }})</span>
                    <span class="text-xs font-normal px-2 py-0.5 rounded bg-slate-800 text-slate-300 uppercase">
                        Komponen: {{ $componentView }}
                    </span>
                </h2>
                <p class="text-xs text-slate-400 mt-0.5">Menampilkan trajektori penghasilan lengkap setiap SPG sepanjang tahun</p>
            </div>
            <div class="text-xs text-slate-300 font-mono">
                Total: {{ count($spgMatrixRows) }} SPG Terpilih
            </div>
        </div>

        <div class="overflow-x-auto custom-scrollbar relative max-h-[70vh]">
            <table class="w-full text-xs text-left border-collapse whitespace-nowrap">
                <thead class="sticky top-0 z-30 bg-slate-800 text-white font-bold">
                    <tr>
                        <th class="sticky left-0 z-40 bg-slate-800 px-3 py-3 w-10 text-center border-b border-r border-slate-700">NO</th>
                        <th class="sticky left-10 z-40 bg-slate-800 px-4 py-3 min-w-[150px] border-b border-r border-slate-700">NAMA SPG</th>
                        <th class="sticky left-[190px] z-40 bg-slate-800 px-3 py-3 w-24 text-center border-b border-r border-slate-700 shadow-xs">OUTLET</th>
                        
                        @foreach ($months as $m)
                            <th class="px-3 py-3 text-right border-b border-r border-slate-700 min-w-[80px]">{{ $m }}</th>
                        @endforeach

                        <th class="px-4 py-3 text-right bg-slate-900 text-amber-300 font-extrabold border-b border-slate-700 min-w-[130px]">
                            TOTAL TAHUNAN (RP)
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                    @php $no = 1; @endphp
                    @foreach ($spgMatrixRows as $row)
                        <tr class="hover:bg-slate-50 transition group">
                            <!-- Pinned Left 1: No -->
                            <td class="sticky left-0 z-20 bg-white group-hover:bg-slate-50 px-3 py-2.5 text-center text-slate-400 border-r border-slate-200">
                                {{ $no++ }}
                            </td>
                            <!-- Pinned Left 2: SPG Name -->
                            <td class="sticky left-10 z-20 bg-white group-hover:bg-slate-50 px-4 py-2.5 font-bold text-slate-900 border-r border-slate-200">
                                {{ $row['spg_name'] }}
                            </td>
                            <!-- Pinned Left 3: Outlet Code -->
                            <td class="sticky left-[190px] z-20 bg-white group-hover:bg-slate-50 px-3 py-2.5 text-center font-semibold text-slate-700 border-r border-slate-200 shadow-xs">
                                <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-800 text-[11px] font-mono">
                                    {{ $row['outlet_code'] }}
                                </span>
                            </td>

                            <!-- 12 Months Columns -->
                            @for ($m = 1; $m <= 12; $m++)
                                @php $val = $row['months'][$m] ?? 0; @endphp
                                <td class="px-3 py-2.5 text-right font-mono border-r border-slate-100 {{ $val > 0 ? 'text-slate-800' : 'text-slate-300' }}">
                                    {{ $val > 0 ? number_format($val, 0, ',', '.') : '-' }}
                                </td>
                            @endfor

                            <!-- Annual Total per SPG -->
                            <td class="px-4 py-2.5 text-right font-mono font-extrabold text-slate-900 bg-amber-50/40 text-sm">
                                Rp {{ number_format($row['annual_total'], 0, ',', '.') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>

                <!-- Table Footer Grand Total -->
                <tfoot class="sticky bottom-0 z-30 bg-slate-900 text-white font-bold shadow-md">
                    <tr>
                        <td colspan="3" class="sticky left-0 z-40 bg-slate-900 px-4 py-3 text-right uppercase tracking-wider text-xs border-r border-slate-800">
                            TOTAL SEMUA SPG:
                        </td>
                        @php $annualSum = 0; @endphp
                        @for ($m = 1; $m <= 12; $m++)
                            @php 
                                $colSum = $monthlyTotals[$m] ?? 0; 
                                $annualSum += $colSum;
                            @endphp
                            <td class="px-3 py-3 text-right font-mono border-r border-slate-800 text-xs">
                                {{ $colSum > 0 ? number_format($colSum, 0, ',', '.') : '-' }}
                            </td>
                        @endfor
                        <td class="px-4 py-3 text-right font-mono font-black text-amber-300 text-sm">
                            Rp {{ number_format($annualSum, 0, ',', '.') }}
                        </td>
                    </tr>
                </tfoot>

            </table>
        </div>

    </div>

</div>
@endsection
