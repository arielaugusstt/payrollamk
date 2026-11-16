<?php

namespace App\Http\Controllers;

use App\Models\IncentiveSetting;
use App\Models\Outlet;
use App\Models\OutletAlias;
use App\Models\Spg;
use Illuminate\Http\Request;

class MasterDataController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->query('tab', 'outlets');

        $outlets = Outlet::withCount('spgs', 'aliases')->orderBy('outlet_code')->get();
        $aliases = OutletAlias::with('outlet')->orderBy('alias')->get();
        $spgs = Spg::with('assignedOutlet')->orderBy('spg_name')->get();
        $incentiveSettings = IncentiveSetting::current();

        return view('master.index', compact('tab', 'outlets', 'aliases', 'spgs', 'incentiveSettings'));
    }

    // --- OUTLETS CRUD ---
    public function storeOutlet(Request $request)
    {
        $request->validate([
            'outlet_code' => ['required', 'string', 'max:20', 'unique:outlets,outlet_code'],
            'outlet_name' => ['required', 'string', 'max:100'],
            'gaji_pokok' => ['required', 'numeric', 'min:0'],
            'uang_makan_utama' => ['required', 'numeric', 'min:0'],
            'uang_makan_tambahan' => ['nullable', 'numeric', 'min:0'],
            'uang_pulsa' => ['nullable', 'numeric', 'min:0'],
        ]);

        Outlet::create([
            'outlet_code' => strtoupper(trim($request->outlet_code)),
            'outlet_name' => trim($request->outlet_name),
            'gaji_pokok' => (float)$request->gaji_pokok,
            'uang_makan_utama' => (float)$request->uang_makan_utama,
            'uang_makan_tambahan' => (float)($request->uang_makan_tambahan ?? 0),
            'uang_pulsa' => (float)($request->uang_pulsa ?? 0),
            'is_active' => true,
        ]);

        return redirect()->route('master.index', ['tab' => 'outlets'])->with('success', 'Outlet berhasil ditambahkan!');
    }

    public function updateOutlet(Request $request, Outlet $outlet)
    {
        $request->validate([
            'outlet_code' => ['required', 'string', 'max:20', 'unique:outlets,outlet_code,' . $outlet->id],
            'outlet_name' => ['required', 'string', 'max:100'],
            'gaji_pokok' => ['required', 'numeric', 'min:0'],
            'uang_makan_utama' => ['required', 'numeric', 'min:0'],
            'uang_makan_tambahan' => ['nullable', 'numeric', 'min:0'],
            'uang_pulsa' => ['nullable', 'numeric', 'min:0'],
        ]);

        $outlet->update([
            'outlet_code' => strtoupper(trim($request->outlet_code)),
            'outlet_name' => trim($request->outlet_name),
            'gaji_pokok' => (float)$request->gaji_pokok,
            'uang_makan_utama' => (float)$request->uang_makan_utama,
            'uang_makan_tambahan' => (float)($request->uang_makan_tambahan ?? 0),
            'uang_pulsa' => (float)($request->uang_pulsa ?? 0),
        ]);

        return redirect()->route('master.index', ['tab' => 'outlets'])->with('success', 'Outlet berhasil diperbarui!');
    }

    public function toggleOutlet(Outlet $outlet)
    {
        $outlet->update(['is_active' => !$outlet->is_active]);
        return back()->with('success', 'Status outlet berhasil diubah.');
    }

    // --- ALIASES CRUD ---
    public function storeAlias(Request $request)
    {
        $request->validate([
            'outlet_id' => ['required', 'exists:outlets,id'],
            'alias' => ['required', 'string', 'max:50'],
        ]);

        OutletAlias::firstOrCreate([
            'outlet_id' => $request->outlet_id,
            'alias' => trim($request->alias),
        ]);

        return redirect()->route('master.index', ['tab' => 'aliases'])->with('success', 'Alias outlet berhasil ditambahkan!');
    }

    public function deleteAlias(OutletAlias $alias)
    {
        $alias->delete();
        return redirect()->route('master.index', ['tab' => 'aliases'])->with('success', 'Alias outlet berhasil dihapus.');
    }

    // --- SPG CRUD ---
    public function storeSpg(Request $request)
    {
        $request->validate([
            'spg_name' => ['required', 'string', 'max:100'],
            'default_outlet_id' => ['nullable', 'exists:outlets,id'],
            'assigned_outlet_id' => ['nullable', 'exists:outlets,id'],
            'aliases' => ['nullable', 'string'],
        ]);

        $outletId = $request->default_outlet_id ?: $request->assigned_outlet_id;
        $aliases = $request->aliases ? array_map('trim', explode(',', $request->aliases)) : [];

        Spg::create([
            'spg_name' => trim($request->spg_name),
            'default_outlet_id' => $outletId,
            'assigned_outlet_id' => $outletId,
            'aliases' => $aliases,
            'is_active' => true,
        ]);

        return redirect()->route('master.index', ['tab' => 'spgs'])->with('success', 'Master SPG berhasil ditambahkan!');
    }

    public function updateSpg(Request $request, Spg $spg)
    {
        $request->validate([
            'spg_name' => ['required', 'string', 'max:100'],
            'default_outlet_id' => ['nullable', 'exists:outlets,id'],
            'assigned_outlet_id' => ['nullable', 'exists:outlets,id'],
            'aliases' => ['nullable', 'string'],
        ]);

        $outletId = $request->default_outlet_id ?: $request->assigned_outlet_id;
        $aliases = $request->aliases ? array_map('trim', explode(',', $request->aliases)) : [];

        $spg->update([
            'spg_name' => trim($request->spg_name),
            'default_outlet_id' => $outletId,
            'assigned_outlet_id' => $outletId,
            'aliases' => $aliases,
        ]);

        return redirect()->route('master.index', ['tab' => 'spgs'])->with('success', 'Data SPG berhasil diperbarui!');
    }

    public function toggleSpg(Spg $spg)
    {
        $spg->update(['is_active' => !$spg->is_active]);
        return back()->with('success', 'Status SPG berhasil diubah.');
    }

    // --- INCENTIVE SETTINGS ---
    public function updateIncentiveSettings(Request $request)
    {
        $request->validate([
            'threshold_amount' => ['required', 'numeric', 'min:0'],
            'tier_1_rate_above_threshold' => ['required', 'numeric', 'min:0', 'max:100'],
            'tier_2_rate_remaining' => ['required', 'numeric', 'min:0', 'max:100'],
            'below_threshold_flat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $settings = IncentiveSetting::current();
        $settings->update([
            'threshold_amount' => (float)$request->threshold_amount,
            'tier_1_rate_above_threshold' => (float)$request->tier_1_rate_above_threshold,
            'tier_2_rate_remaining' => (float)$request->tier_2_rate_remaining,
            'below_threshold_flat_rate' => (float)$request->below_threshold_flat_rate,
        ]);

        return redirect()->route('master.index', ['tab' => 'incentive'])->with('success', 'Konstanta Insentif global berhasil diperbarui!');
    }
}
