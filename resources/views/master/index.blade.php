@extends('layouts.app', ['title' => 'Master Data & Konstanta &bull; Kucing Oren Payroll'])

@section('content')
<div class="space-y-6" x-data="{
    activeTab: '{{ $tab ?? 'outlets' }}',
    addOutletOpen: false,
    editOutletOpen: false,
    currentOutlet: {},
    addAliasOpen: false,
    addSpgOpen: false,
    editSpgOpen: false,
    currentSpg: {}
}">

    <!-- Top Banner -->
    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <span class="p-2.5 bg-orange-100 text-orange-600 rounded-xl">
                ⚙️
            </span>
            <div>
                <h1 class="text-xl font-bold text-slate-900">Master Data & Konstanta</h1>
                <p class="text-xs text-slate-500">Kelola Outlet & 4 Konstanta Gaji, Kamus Typo Alias, Master SPG, dan Parameter Insentif Global</p>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="flex flex-wrap items-center bg-slate-100 p-1 rounded-xl text-xs font-semibold">
            <button @click="activeTab = 'outlets'" 
                    :class="activeTab === 'outlets' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                    class="px-3.5 py-2 rounded-lg transition">
                🏢 Master Outlet & Gaji
            </button>
            <button @click="activeTab = 'aliases'" 
                    :class="activeTab === 'aliases' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                    class="px-3.5 py-2 rounded-lg transition">
                📖 Kamus Typo Alias
            </button>
            <button @click="activeTab = 'spgs'" 
                    :class="activeTab === 'spgs' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                    class="px-3.5 py-2 rounded-lg transition">
                👩‍💼 Master SPG
            </button>
            <button @click="activeTab = 'incentive'" 
                    :class="activeTab === 'incentive' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                    class="px-3.5 py-2 rounded-lg transition">
                📈 Konstanta Insentif
            </button>
        </div>
    </div>

    <!-- TAB 1: OUTLETS & SALARY CONSTANTS -->
    <div x-show="activeTab === 'outlets'" x-cloak class="space-y-4">
        <div class="flex items-center justify-between">
            <p class="text-xs text-slate-500">Terdapat <b>{{ $outlets->count() }}</b> outlet terdaftar dengan 4 konstanta penggajian masing-masing.</p>
            <button @click="addOutletOpen = true" class="px-3.5 py-2 bg-orange-600 hover:bg-orange-700 text-white rounded-xl text-xs font-semibold shadow-xs flex items-center gap-1.5 transition">
                + Tambah Outlet Baru
            </button>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left border-collapse">
                    <thead class="bg-slate-900 text-white font-bold">
                        <tr>
                            <th class="px-4 py-3">KODE OUTLET</th>
                            <th class="px-4 py-3">NAMA OUTLET</th>
                            <th class="px-4 py-3 text-right">GAJI POKOK (BULANAN)</th>
                            <th class="px-4 py-3 text-right">U. MAKAN UTAMA (HARIAN)</th>
                            <th class="px-4 py-3 text-right">U. MAKAN TAMBAHAN</th>
                            <th class="px-4 py-3 text-right">U. PULSA</th>
                            <th class="px-3 py-3 text-center">STATUS</th>
                            <th class="px-4 py-3 text-center">AKSI</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($outlets as $outlet)
                            <tr class="hover:bg-slate-50 transition">
                                <td class="px-4 py-3 font-mono font-bold text-slate-900">
                                    <span class="px-2 py-0.5 rounded bg-slate-100 border border-slate-200">
                                        {{ $outlet->outlet_code }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 font-semibold text-slate-800">
                                    {{ $outlet->outlet_name }}
                                    <span class="text-[11px] text-slate-400 block">{{ $outlet->spgs_count }} SPG aktif &bull; {{ $outlet->aliases_count }} alias</span>
                                </td>
                                <td class="px-4 py-3 text-right font-mono text-slate-700 font-semibold">
                                    Rp {{ number_format($outlet->gaji_pokok, 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono text-slate-700 font-semibold">
                                    Rp {{ number_format($outlet->uang_makan_utama, 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono text-slate-500">
                                    Rp {{ number_format($outlet->uang_makan_tambahan, 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono text-slate-700">
                                    Rp {{ number_format($outlet->uang_pulsa, 0, ',', '.') }}
                                </td>
                                <td class="px-3 py-3 text-center">
                                    @if ($outlet->is_active)
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">Aktif</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-500">Non-Aktif</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center space-x-1">
                                    <button @click="currentOutlet = {{ json_encode($outlet) }}; editOutletOpen = true" 
                                            class="px-2.5 py-1 text-slate-600 hover:text-orange-600 hover:bg-slate-100 rounded-lg font-medium text-xs">
                                        Edit
                                    </button>
                                    <form action="{{ route('master.outlets.toggle', $outlet->id) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="px-2.5 py-1 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg font-medium text-[11px]">
                                            {{ $outlet->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 2: OUTLET ALIAS & TYPO DICTIONARY -->
    <div x-show="activeTab === 'aliases'" x-cloak class="space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-slate-500">Kamus ini memetakan teks kotor/typo dari Excel legacy (misal: <code>delta</code>, <code>ELTA</code> &rarr; <b>DELTA</b>; <code>WT</code> &rarr; <b>WTC</b>).</p>
            </div>
            <button @click="addAliasOpen = true" class="px-3.5 py-2 bg-orange-600 hover:bg-orange-700 text-white rounded-xl text-xs font-semibold shadow-xs flex items-center gap-1.5 transition">
                + Tambah Alias Baru
            </button>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                @foreach ($aliases as $alias)
                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 flex items-center justify-between text-xs hover:bg-white hover:shadow-xs transition">
                        <div class="flex items-center gap-2">
                            <code class="px-2 py-0.5 bg-amber-100 text-amber-900 rounded font-bold font-mono">{{ $alias->alias }}</code>
                            <span class="text-slate-400">&rarr;</span>
                            <span class="font-bold text-slate-800">{{ $alias->outlet->outlet_code }}</span>
                        </div>
                        <form action="{{ route('master.aliases.delete', $alias->id) }}" method="POST" onsubmit="return confirm('Hapus alias ini?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-slate-400 hover:text-red-600 font-bold px-1" title="Hapus Alias">&times;</button>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- TAB 3: MASTER SPG -->
    <div x-show="activeTab === 'spgs'" x-cloak class="space-y-4">
        <div class="flex items-center justify-between">
            <p class="text-xs text-slate-500"><b>Dynamic Daily Outlets:</b> SPG tidak terikat pada 1 outlet tetap. Penugasan riil ditentukan oleh kode outlet di setiap sel tanggal presensi/omzet.</p>
            <button @click="addSpgOpen = true" class="px-3.5 py-2 bg-orange-600 hover:bg-orange-700 text-white rounded-xl text-xs font-semibold shadow-xs flex items-center gap-1.5 transition">
                + Tambah SPG Baru
            </button>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left border-collapse">
                    <thead class="bg-slate-900 text-white font-bold">
                        <tr>
                            <th class="px-4 py-3">NO</th>
                            <th class="px-4 py-3">NAMA SPG</th>
                            <th class="px-4 py-3">OUTLET ASAL / HOMEBASE</th>
                            <th class="px-4 py-3">ALIAS / NAMA LAIN</th>
                            <th class="px-3 py-3 text-center">STATUS</th>
                            <th class="px-4 py-3 text-center">AKSI</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @php $no = 1; @endphp
                        @foreach ($spgs as $spg)
                            <tr class="hover:bg-slate-50 transition">
                                <td class="px-4 py-3 text-slate-400">{{ $no++ }}</td>
                                <td class="px-4 py-3 font-bold text-slate-900 text-sm">
                                    {{ $spg->spg_name }}
                                </td>
                                <td class="px-4 py-3 font-semibold text-slate-800">
                                    @php $hb = $spg->defaultOutlet ?? $spg->assignedOutlet; @endphp
                                    @if ($hb)
                                        <span class="px-2 py-0.5 rounded bg-slate-100 border border-slate-200 font-mono">
                                            {{ $hb->outlet_code }}
                                        </span>
                                        <span class="text-slate-500 ml-1">({{ $hb->outlet_name }})</span>
                                    @else
                                        <span class="text-slate-400 italic">Rotasi Bebas (Tanpa Homebase)</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-slate-500">
                                    @if ($spg->aliases && is_array($spg->aliases))
                                        <span class="font-mono text-[11px]">{{ implode(', ', $spg->aliases) }}</span>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-center">
                                    @if ($spg->is_active)
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">Aktif</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-500">Non-Aktif</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center space-x-1">
                                    <button @click="currentSpg = {{ json_encode($spg) }}; editSpgOpen = true" 
                                            class="px-2.5 py-1 text-slate-600 hover:text-orange-600 hover:bg-slate-100 rounded-lg font-medium text-xs">
                                        Edit
                                    </button>
                                    <form action="{{ route('master.spgs.toggle', $spg->id) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="px-2.5 py-1 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg font-medium text-[11px]">
                                            {{ $spg->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 4: GLOBAL INCENTIVE CONSTANTS -->
    <div x-show="activeTab === 'incentive'" x-cloak class="space-y-4">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-6 max-w-2xl">
            <h2 class="text-base font-bold text-slate-900 mb-1">Parameter Perhitungan Insentif Global</h2>
            <p class="text-xs text-slate-500 mb-5">Atur ambang batas omzet bulanan (threshold) dan persentase tarif bertingkat untuk seluruh SPG.</p>

            <form action="{{ route('master.incentive.update') }}" method="POST">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Ambang Batas Omzet (Threshold Amount)</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center font-bold text-slate-400 text-xs">Rp</span>
                            <input type="number" name="threshold_amount" value="{{ (int)$incentiveSettings->threshold_amount }}" required class="w-full pl-10 pr-3 py-2 border border-slate-200 rounded-xl text-sm font-bold focus:outline-none focus:ring-2 focus:ring-orange-500">
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1">Default: Rp 5.000.000</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Tarif Tier 1 (First 5 Juta)</label>
                            <div class="relative">
                                <input type="number" step="0.01" name="tier_1_rate_above_threshold" value="{{ $incentiveSettings->tier_1_rate_above_threshold }}" required class="w-full pr-8 pl-3 py-2 border border-slate-200 rounded-xl text-sm font-bold focus:outline-none focus:ring-2 focus:ring-orange-500">
                                <span class="absolute inset-y-0 right-0 pr-3 flex items-center font-bold text-slate-400 text-xs">%</span>
                            </div>
                            <p class="text-[11px] text-slate-400 mt-1">Default: 1.25% (= Rp 62.500 jika omzet &gt; 5 Juta)</p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Tarif Tier 2 (Sisa di Atas 5 Juta)</label>
                            <div class="relative">
                                <input type="number" step="0.01" name="tier_2_rate_remaining" value="{{ $incentiveSettings->tier_2_rate_remaining }}" required class="w-full pr-8 pl-3 py-2 border border-slate-200 rounded-xl text-sm font-bold focus:outline-none focus:ring-2 focus:ring-orange-500">
                                <span class="absolute inset-y-0 right-0 pr-3 flex items-center font-bold text-slate-400 text-xs">%</span>
                            </div>
                            <p class="text-[11px] text-slate-400 mt-1">Default: 2.50%</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Tarif Flat (Jika Total Omzet &le; Threshold 5 Juta)</label>
                        <div class="relative">
                            <input type="number" step="0.01" name="below_threshold_flat_rate" value="{{ $incentiveSettings->below_threshold_flat_rate }}" required class="w-full pr-8 pl-3 py-2 border border-slate-200 rounded-xl text-sm font-bold focus:outline-none focus:ring-2 focus:ring-orange-500">
                            <span class="absolute inset-y-0 right-0 pr-3 flex items-center font-bold text-slate-400 text-xs">%</span>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1">Default: 2.50% flat dari total omzet</p>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-slate-100 flex justify-end">
                    <button type="submit" class="px-5 py-2.5 bg-orange-600 hover:bg-orange-700 text-white rounded-xl text-xs font-bold shadow-md shadow-orange-600/20">
                        Simpan Parameter Insentif
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Outlet Modal -->
    <div x-show="addOutletOpen" x-cloak class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.outside="addOutletOpen = false" class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-100">
            <h3 class="text-base font-bold text-slate-900 mb-4">Tambah Outlet Baru</h3>
            <form action="{{ route('master.outlets.store') }}" method="POST" class="space-y-3 text-xs">
                @csrf
                <div>
                    <label class="block font-semibold mb-1">Kode Outlet (Singkatan Unik)</label>
                    <input type="text" name="outlet_code" placeholder="Contoh: TP-3 atau GALAXY" required class="w-full px-3 py-2 border rounded-xl uppercase">
                </div>
                <div>
                    <label class="block font-semibold mb-1">Nama Panjang Outlet</label>
                    <input type="text" name="outlet_name" placeholder="Contoh: Tunjungan Plaza 3" required class="w-full px-3 py-2 border rounded-xl">
                </div>
                <div>
                    <label class="block font-semibold mb-1">Gaji Pokok (Rp)</label>
                    <input type="number" name="gaji_pokok" value="350000" required class="w-full px-3 py-2 border rounded-xl font-mono">
                </div>
                <div>
                    <label class="block font-semibold mb-1">Uang Makan Utama / AMK Harian (Rp)</label>
                    <input type="number" name="uang_makan_utama" value="30000" required class="w-full px-3 py-2 border rounded-xl font-mono">
                </div>
                <div>
                    <label class="block font-semibold mb-1">Uang Makan Tambahan (Rp)</label>
                    <input type="number" name="uang_makan_tambahan" value="0" class="w-full px-3 py-2 border rounded-xl font-mono">
                </div>
                <div>
                    <label class="block font-semibold mb-1">Uang Pulsa (Rp)</label>
                    <input type="number" name="uang_pulsa" value="10000" class="w-full px-3 py-2 border rounded-xl font-mono">
                </div>
                <div class="flex justify-end gap-2 pt-3 border-t">
                    <button type="button" @click="addOutletOpen = false" class="px-4 py-2 rounded-xl text-slate-600">Batal</button>
                    <button type="submit" class="px-5 py-2 bg-orange-600 text-white font-bold rounded-xl">Simpan Outlet</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Outlet Modal -->
    <div x-show="editOutletOpen" x-cloak class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.outside="editOutletOpen = false" class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-100">
            <h3 class="text-base font-bold text-slate-900 mb-4">Edit Konstanta Outlet</h3>
            <form :action="'/master/outlets/' + currentOutlet.id" method="POST" class="space-y-3 text-xs">
                @csrf
                @method('PUT')
                <div>
                    <label class="block font-semibold mb-1">Kode Outlet</label>
                    <input type="text" name="outlet_code" x-model="currentOutlet.outlet_code" required class="w-full px-3 py-2 border rounded-xl uppercase">
                </div>
                <div>
                    <label class="block font-semibold mb-1">Nama Panjang Outlet</label>
                    <input type="text" name="outlet_name" x-model="currentOutlet.outlet_name" required class="w-full px-3 py-2 border rounded-xl">
                </div>
                <div>
                    <label class="block font-semibold mb-1">Gaji Pokok (Rp)</label>
                    <input type="number" name="gaji_pokok" x-model="currentOutlet.gaji_pokok" required class="w-full px-3 py-2 border rounded-xl font-mono">
                </div>
                <div>
                    <label class="block font-semibold mb-1">Uang Makan Utama (Rp)</label>
                    <input type="number" name="uang_makan_utama" x-model="currentOutlet.uang_makan_utama" required class="w-full px-3 py-2 border rounded-xl font-mono">
                </div>
                <div>
                    <label class="block font-semibold mb-1">Uang Makan Tambahan (Rp)</label>
                    <input type="number" name="uang_makan_tambahan" x-model="currentOutlet.uang_makan_tambahan" class="w-full px-3 py-2 border rounded-xl font-mono">
                </div>
                <div>
                    <label class="block font-semibold mb-1">Uang Pulsa (Rp)</label>
                    <input type="number" name="uang_pulsa" x-model="currentOutlet.uang_pulsa" class="w-full px-3 py-2 border rounded-xl font-mono">
                </div>
                <div class="flex justify-end gap-2 pt-3 border-t">
                    <button type="button" @click="editOutletOpen = false" class="px-4 py-2 rounded-xl text-slate-600">Batal</button>
                    <button type="submit" class="px-5 py-2 bg-orange-600 text-white font-bold rounded-xl">Perbarui Outlet</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Alias Modal -->
    <div x-show="addAliasOpen" x-cloak class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.outside="addAliasOpen = false" class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-100">
            <h3 class="text-base font-bold text-slate-900 mb-4">Tambah Alias / Typo Baru</h3>
            <form action="{{ route('master.aliases.store') }}" method="POST" class="space-y-3 text-xs">
                @csrf
                <div>
                    <label class="block font-semibold mb-1">Pilih Outlet Tujuan</label>
                    <select name="outlet_id" required class="w-full px-3 py-2 border rounded-xl">
                        @foreach ($outlets as $o)
                            <option value="{{ $o->id }}">{{ $o->outlet_code }} ({{ $o->outlet_name }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block font-semibold mb-1">Teks Alias / Typo</label>
                    <input type="text" name="alias" placeholder="Contoh: delta, ELTA, WT" required class="w-full px-3 py-2 border rounded-xl">
                </div>
                <div class="flex justify-end gap-2 pt-3 border-t">
                    <button type="button" @click="addAliasOpen = false" class="px-4 py-2 rounded-xl text-slate-600">Batal</button>
                    <button type="submit" class="px-5 py-2 bg-orange-600 text-white font-bold rounded-xl">Simpan Alias</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add SPG Modal -->
    <div x-show="addSpgOpen" x-cloak class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.outside="addSpgOpen = false" class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-100">
            <h3 class="text-base font-bold text-slate-900 mb-4">Tambah Master SPG Baru</h3>
            <form action="{{ route('master.spgs.store') }}" method="POST" class="space-y-3 text-xs">
                @csrf
                <div>
                    <label class="block font-semibold mb-1">Nama SPG</label>
                    <input type="text" name="spg_name" placeholder="Contoh: Mega" required class="w-full px-3 py-2 border rounded-xl font-bold">
                </div>
                <div>
                    <label class="block font-semibold mb-1">Outlet Penugasan Permanen</label>
                    <select name="assigned_outlet_id" required class="w-full px-3 py-2 border rounded-xl">
                        @foreach ($outlets as $o)
                            <option value="{{ $o->id }}">{{ $o->outlet_code }} ({{ $o->outlet_name }})</option>
                        @endforeach
                    </select>
                    <p class="text-[10px] text-slate-400 mt-1">1 SPG secara permanen terikat ke 1 outlet untuk konstanta gaji.</p>
                </div>
                <div>
                    <label class="block font-semibold mb-1">Alias / Variasi Nama (Pisahkan dengan koma)</label>
                    <input type="text" name="aliases" placeholder="Contoh: mega, Mega R1" class="w-full px-3 py-2 border rounded-xl">
                </div>
                <div class="flex justify-end gap-2 pt-3 border-t">
                    <button type="button" @click="addSpgOpen = false" class="px-4 py-2 rounded-xl text-slate-600">Batal</button>
                    <button type="submit" class="px-5 py-2 bg-orange-600 text-white font-bold rounded-xl">Simpan SPG</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit SPG Modal -->
    <div x-show="editSpgOpen" x-cloak class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.outside="editSpgOpen = false" class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-100">
            <h3 class="text-base font-bold text-slate-900 mb-4">Edit Master SPG</h3>
            <form :action="'/master/spgs/' + currentSpg.id" method="POST" class="space-y-3 text-xs">
                @csrf
                @method('PUT')
                <div>
                    <label class="block font-semibold mb-1">Nama SPG</label>
                    <input type="text" name="spg_name" x-model="currentSpg.spg_name" required class="w-full px-3 py-2 border rounded-xl font-bold">
                </div>
                <div>
                    <label class="block font-semibold mb-1">Outlet Penugasan Permanen</label>
                    <select name="assigned_outlet_id" x-model="currentSpg.assigned_outlet_id" required class="w-full px-3 py-2 border rounded-xl">
                        @foreach ($outlets as $o)
                            <option value="{{ $o->id }}">{{ $o->outlet_code }} ({{ $o->outlet_name }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block font-semibold mb-1">Alias / Variasi Nama (Pisahkan dengan koma)</label>
                    <input type="text" name="aliases" :value="Array.isArray(currentSpg.aliases) ? currentSpg.aliases.join(', ') : ''" class="w-full px-3 py-2 border rounded-xl">
                </div>
                <div class="flex justify-end gap-2 pt-3 border-t">
                    <button type="button" @click="editSpgOpen = false" class="px-4 py-2 rounded-xl text-slate-600">Batal</button>
                    <button type="submit" class="px-5 py-2 bg-orange-600 text-white font-bold rounded-xl">Perbarui SPG</button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
