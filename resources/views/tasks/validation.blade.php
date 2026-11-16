@extends('layouts.app', ['title' => 'Validasi & Resolusi Anomali Excel &bull; ' . $task->title])

@section('content')
<div class="max-w-6xl mx-auto space-y-6">

    <!-- Header Banner -->
    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="p-2 bg-orange-100 text-orange-600 rounded-xl">
                    🔍
                </span>
                <div>
                    <h1 class="text-xl font-bold text-slate-900">Smart Anomaly & Typo Analyzer</h1>
                    <p class="text-xs text-slate-500">Panel Validasi Pra-Kalkulasi Sebelum Data Diterapkan ke Matrix Task</p>
                </div>
            </div>
            <div class="mt-2 flex items-center gap-2 text-xs text-slate-600">
                <span class="px-2.5 py-0.5 rounded-full bg-slate-100 font-semibold">{{ $task->title }}</span>
                <span>&bull;</span>
                <span>Ditemukan Sheet: <b>{{ implode(', ', $analysis['sheet_names'] ?? []) }}</b></span>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('tasks.show', $task->id) }}" class="px-4 py-2 border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-xs font-semibold">
                Batal & Kembali
            </a>
        </div>
    </div>

    <form action="{{ route('tasks.upload.commit', $task->id) }}" method="POST">
        @csrf

        <div class="space-y-6">

            <!-- 1. Outlet Typo & Fuzzy Matching Section -->
            @php $unrecognizedOutlets = $analysis['anomalies']['unrecognized_outlets'] ?? []; @endphp
            <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <span class="w-7 h-7 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center font-bold text-xs">
                            🏢
                        </span>
                        <div>
                            <h2 class="text-sm font-bold text-slate-900">1. Typo & Fuzzy Matching Outlet</h2>
                            <p class="text-xs text-slate-500">Mendeteksi penulisan outlet non-standar (misal: delta, ELTA, WT, ptc)</p>
                        </div>
                    </div>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold {{ empty($unrecognizedOutlets) ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                        {{ count($unrecognizedOutlets) }} Terdeteksi
                    </span>
                </div>

                @if (empty($unrecognizedOutlets))
                    <div class="p-3.5 bg-emerald-50 rounded-xl border border-emerald-100 text-emerald-800 text-xs flex items-center gap-2">
                        <span>✅</span>
                        <span>Semua kode outlet di file terbaca valid dan cocok dengan Master Data Outlet.</span>
                    </div>
                @else
                    <div class="space-y-3">
                        <p class="text-xs text-slate-600">Sistem merekomendasikan pemetaan berikut. Centang opsi simpan ke kamus agar alias ini otomatis dikenali pada upload berikutnya:</p>
                        <div class="divide-y divide-slate-100 border border-slate-200 rounded-xl overflow-hidden">
                            @foreach ($unrecognizedOutlets as $token => $info)
                                <div class="p-3 bg-slate-50/50 flex flex-wrap items-center justify-between gap-3 text-xs">
                                    <div class="flex items-center gap-2">
                                        <code class="px-2 py-1 bg-amber-100 text-amber-900 font-bold rounded border border-amber-200 font-mono">{{ $token }}</code>
                                        <span class="text-slate-400">&rarr;</span>
                                        <span class="text-slate-600">Petakan ke Master Outlet:</span>
                                        <select name="save_aliases[{{ $token }}]" class="px-2.5 py-1 bg-white border border-slate-300 rounded-lg text-xs font-semibold focus:outline-none focus:ring-1 focus:ring-orange-500">
                                            @foreach ($allOutlets as $out)
                                                <option value="{{ $out->id }}" {{ ($info['suggested_outlet_id'] ?? null) == $out->id ? 'selected' : '' }}>
                                                    {{ $out->outlet_code }} ({{ $out->outlet_name }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <label class="flex items-center gap-1.5 text-slate-600 cursor-pointer">
                                        <input type="checkbox" checked class="rounded text-orange-600 focus:ring-orange-500">
                                        <span>Simpan permanen ke Kamus Alias</span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <!-- 2. Multi-Codes in Single Cell Section (Strict Highest Constant Rule) -->
            @php $multiCodes = $analysis['anomalies']['multi_codes'] ?? []; @endphp
            <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <span class="w-7 h-7 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-xs">
                            🏷️
                        </span>
                        <div>
                            <h2 class="text-sm font-bold text-slate-900">2. Multi-Code / Catatan Khusus di Satu Sel</h2>
                            <p class="text-xs text-slate-500">Aturan Bisnis: Evaluasi seluruh kode outlet dan ambil <b>NILAI KONSTANTA TERTINGGI</b> untuk hari tersebut</p>
                        </div>
                    </div>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold {{ empty($multiCodes) ? 'bg-slate-100 text-slate-600' : 'bg-blue-100 text-blue-800' }}">
                        {{ count($multiCodes) }} Kasus
                    </span>
                </div>

                @if (empty($multiCodes))
                    <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 text-slate-600 text-xs flex items-center gap-2">
                        <span>ℹ️</span>
                        <span>Tidak ditemukan sel presensi dengan kode ganda/multi-outlet.</span>
                    </div>
                @else
                    <div class="space-y-2">
                        <div class="divide-y divide-slate-100 border border-slate-200 rounded-xl overflow-hidden text-xs">
                            @foreach ($multiCodes as $mc)
                                <div class="p-3 bg-blue-50/30 flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-2">
                                        <span class="text-slate-500">Teks Sel:</span>
                                        <code class="px-2 py-0.5 bg-blue-100 text-blue-900 font-bold rounded font-mono">{{ $mc['original'] }}</code>
                                        <span class="text-slate-400">&rarr;</span>
                                        <span class="text-slate-700">Kode Terdeteksi: <b>{{ implode(', ', $mc['tokens']) }}</b></span>
                                    </div>
                                    <span class="px-2.5 py-1 bg-blue-100 text-blue-800 rounded-lg text-[11px] font-semibold">
                                        🛡️ Ambil Tarif Tertinggi Otomatis
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <!-- 3. Unrecognized SPG Names Section -->
            @php $unrecognizedSpgs = $analysis['anomalies']['unrecognized_spgs'] ?? []; @endphp
            <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <span class="w-7 h-7 rounded-lg bg-purple-100 text-purple-700 flex items-center justify-center font-bold text-xs">
                            👩‍💼
                        </span>
                        <div>
                            <h2 class="text-sm font-bold text-slate-900">3. Pemetaan & Penambahan Master SPG Baru</h2>
                            <p class="text-xs text-slate-500">Pastikan setiap SPG terdaftar dan terikat ke 1 Outlet permanen</p>
                        </div>
                    </div>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold {{ empty($unrecognizedSpgs) ? 'bg-emerald-100 text-emerald-800' : 'bg-purple-100 text-purple-800' }}">
                        {{ count($unrecognizedSpgs) }} Belum Terpetakan
                    </span>
                </div>

                @if (empty($unrecognizedSpgs))
                    <div class="p-3.5 bg-emerald-50 rounded-xl border border-emerald-100 text-emerald-800 text-xs flex items-center gap-2">
                        <span>✅</span>
                        <span>Semua nama SPG cocok dengan database Master SPG.</span>
                    </div>
                @else
                    <div class="space-y-3">
                        <div class="divide-y divide-slate-100 border border-slate-200 rounded-xl overflow-hidden text-xs">
                            @foreach ($unrecognizedSpgs as $rawName => $info)
                                <div class="p-3.5 bg-slate-50/50 flex flex-wrap items-center justify-between gap-3">
                                    <div class="flex items-center gap-2">
                                        <span class="text-slate-500">Nama di Excel:</span>
                                        <span class="font-bold text-slate-900 text-sm">{{ $rawName }}</span>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-2">
                                        <label class="text-slate-500">Pilih Solusi:</label>
                                        <select name="spg_mappings[{{ $rawName }}]" class="px-2.5 py-1 bg-white border border-slate-300 rounded-lg text-xs font-semibold focus:outline-none focus:ring-1 focus:ring-orange-500">
                                            @if ($info['suggested_spg_id'])
                                                <option value="{{ $info['suggested_spg_id'] }}">
                                                    Cocokkan ke SPG: {{ $info['suggested_name'] }}
                                                </option>
                                            @endif
                                            @foreach ($allSpgs as $s)
                                                @php $code = ($s->defaultOutlet ?? $s->assignedOutlet)?->outlet_code ?? '-'; @endphp
                                                <option value="{{ $s->id }}">{{ $s->spg_name }} ({{ $code }})</option>
                                            @endforeach
                                            <option value="new">+ Buat SPG Baru dengan Nama Ini</option>
                                        </select>

                                        <select name="new_spg_outlets[{{ $rawName }}]" class="px-2.5 py-1 bg-white border border-slate-300 rounded-lg text-xs font-semibold focus:outline-none" title="Jika buat SPG baru, pilih outlet penugasannya">
                                            <option value="">-- Pilih Outlet Permanen --</option>
                                            @foreach ($allOutlets as $o)
                                                <option value="{{ $o->id }}">{{ $o->outlet_code }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <!-- 4. Dirty Numbers in Omzet Sheet Section -->
            @php $dirtyNumbers = $analysis['anomalies']['dirty_numbers'] ?? []; @endphp
            <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <span class="w-7 h-7 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center font-bold text-xs">
                            🔢
                        </span>
                        <div>
                            <h2 class="text-sm font-bold text-slate-900">4. Pembersihan Format Angka Omzet Kotor</h2>
                            <p class="text-xs text-slate-500">Otomatis menormalisasi nol hilang (745.00 &rarr; 745.000), simbol mata uang, dan penjumlahan angka ganda</p>
                        </div>
                    </div>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold {{ empty($dirtyNumbers) ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                        {{ count($dirtyNumbers) }} Dinormalisasi
                    </span>
                </div>

                @if (empty($dirtyNumbers))
                    <div class="p-3.5 bg-emerald-50 rounded-xl border border-emerald-100 text-emerald-800 text-xs flex items-center gap-2">
                        <span>✅</span>
                        <span>Semua angka omzet terbaca rapi dan normal.</span>
                    </div>
                @else
                    <div class="divide-y divide-slate-100 border border-slate-200 rounded-xl overflow-hidden text-xs max-h-56 overflow-y-auto custom-scrollbar">
                        @foreach ($dirtyNumbers as $dn)
                            <div class="p-2.5 bg-amber-50/30 flex items-center justify-between gap-3">
                                <div>
                                    <span class="font-bold text-slate-800">{{ $dn['row'] }}</span> ({{ $dn['date'] }}):
                                    <span class="line-through text-slate-400 font-mono ml-1">{{ $dn['original'] }}</span>
                                    <span class="text-slate-400 mx-1">&rarr;</span>
                                    <span class="font-bold text-emerald-700 font-mono">Rp {{ number_format($dn['resolved'], 0, ',', '.') }}</span>
                                </div>
                                <span class="text-[11px] text-amber-800">{{ $dn['message'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- 5. AMK + AMKopi Auto-Pairing (for INSENTIF tasks) -->
            @if ($task->isIncentive())
                @php $kopiPairs = $analysis['anomalies']['amk_amkopi_pairs'] ?? []; @endphp
                <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2">
                            <span class="w-7 h-7 rounded-lg bg-teal-100 text-teal-700 flex items-center justify-center font-bold text-xs">
                                ☕
                            </span>
                            <div>
                                <h2 class="text-sm font-bold text-slate-900">5. Pemasangan Otomatis Baris Kopi (AMK + AMKOpi)</h2>
                                <p class="text-xs text-slate-500">Khusus modul Insentif: Menggabungkan omzet harian produk utama & kopi pada SPG yang sama</p>
                            </div>
                        </div>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-teal-100 text-teal-800">
                            {{ count($kopiPairs) }} Terpasang
                        </span>
                    </div>

                    @if (empty($kopiPairs))
                        <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 text-slate-600 text-xs">
                            Tidak ditemukan baris khusus AMKopi terpisah di file Excel ini.
                        </div>
                    @else
                        <div class="divide-y divide-slate-100 border border-slate-200 rounded-xl overflow-hidden text-xs">
                            @foreach ($kopiPairs as $kp)
                                <div class="p-3 bg-teal-50/30 flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-2">
                                        <span class="text-slate-500">Baris Kopi:</span>
                                        <b class="text-slate-800">{{ $kp['kopi_name'] }}</b>
                                        <span class="text-slate-400">&harr;</span>
                                        <span class="text-slate-500">Dipasangkan dengan:</span>
                                        <b class="text-emerald-800">{{ $kp['matched_main_name'] ?? 'Sesuai SPG Terkait' }}</b>
                                    </div>
                                    <span class="px-2 py-0.5 bg-teal-100 text-teal-800 rounded font-semibold text-[11px]">
                                        {{ $kp['status'] }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

            <!-- Submit Commit Button -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-slate-900">Semua Validasi Telah Ditinjau</h3>
                    <p class="text-xs text-slate-500">Klik tombol di samping untuk menerapkan data yang telah dinormalisasi ke Matrix Grid.</p>
                </div>

                <div class="flex items-center gap-3">
                    <a href="{{ route('tasks.show', $task->id) }}" class="px-4 py-2 border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-xs font-semibold">
                        Batal
                    </a>
                    <button type="submit" class="px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-sm font-bold shadow-md shadow-emerald-600/20 flex items-center gap-2 transition active:scale-95 cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Terapkan Data ke Matrix Task
                    </button>
                </div>
            </div>

        </div>

    </form>

</div>
@endsection
