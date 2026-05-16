<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CompanyProfileController extends Controller
{
    public function index(): View
    {
        return view('company_profiles.index', [
            'company' => $this->companyIdentity(),
        ]);
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('company-profiles.edit', $this->companyIdentity());
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->update($request, $this->companyIdentity());
    }

    public function edit(CompanyProfile $companyProfile): View
    {
        return view('company_profiles.form', [
            'company' => $companyProfile,
        ]);
    }

    public function update(Request $request, CompanyProfile $companyProfile): RedirectResponse
    {
        $before = $companyProfile->toArray();
        $data = $this->validated($request);
        unset($data['logo']);

        DB::transaction(function () use ($request, $companyProfile, $before, &$data) {
            if ($path = $this->storeLogo($request)) {
                $data['logo_path'] = $path;
            }

            $data['is_active'] = true;
            CompanyProfile::whereKeyNot($companyProfile->id)->update(['is_active' => false]);

            $companyProfile->update($data);

            Audit::record('company_profile_updated', $companyProfile, $before, $companyProfile->fresh()->toArray());
        });

        return redirect()->route('company-profiles.index')->with('status', 'Company identity updated.');
    }

    public function activate(CompanyProfile $companyProfile): RedirectResponse
    {
        return redirect()->route('company-profiles.edit', $companyProfile);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo' => ['nullable', 'image', 'max:1024'],
        ]);
    }

    private function companyIdentity(): CompanyProfile
    {
        return CompanyProfile::query()->where('is_active', true)->first()
            ?? CompanyProfile::query()->orderBy('id')->first()
            ?? CompanyProfile::create(CompanyProfile::defaults());
    }

    private function storeLogo(Request $request): ?string
    {
        if (! $request->hasFile('logo')) {
            return null;
        }

        return $request->file('logo')->store('company-logos', 'public');
    }
}
