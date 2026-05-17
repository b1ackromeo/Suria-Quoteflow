@extends('layouts.app', ['title' => 'Edit Company Identity'])

@section('content')
<form method="post" action="{{ $company->exists ? route('company-profiles.update', $company) : route('company-profiles.store') }}" enctype="multipart/form-data" class="space-y-6">
    @csrf
    @if($company->exists)
        @method('put')
    @endif

    <section class="panel space-y-5">
        <div>
            <p class="text-xs font-bold uppercase tracking-wide text-[#0a4f93]">Company identity setup</p>
            <h1 class="mt-2 text-xl font-bold tracking-tight text-slate-950">Edit Company Identity</h1>
            <p class="panel-subtitle">This company name, logo, and contact details appear in the app context, document previews, and generated PDF documents.</p>
        </div>

        <div class="grid gap-4 lg:grid-cols-[1fr_18rem]">
            <div class="grid gap-4 md:grid-cols-2">
                <label class="form-label md:col-span-2">Company name
                    <input class="form-input" name="name" value="{{ old('name', $company->name) }}" required placeholder="Enter company or subsidiary name">
                </label>
                <label class="form-label">Registration number
                    <input class="form-input" name="registration_number" value="{{ old('registration_number', $company->registration_number) }}" placeholder="Company registration number">
                </label>
                <label class="form-label">Email
                    <input class="form-input" type="email" name="email" value="{{ old('email', $company->email) }}" placeholder="company@example.com">
                </label>
                <label class="form-label">Phone
                    <input class="form-input" name="phone" value="{{ old('phone', $company->phone) }}" placeholder="+60 ...">
                </label>
                <label class="form-label">Tagline
                    <input class="form-input" name="tagline" value="{{ old('tagline', $company->tagline) }}" placeholder="Company tagline shown on documents">
                </label>
                <label class="form-label md:col-span-2">Address
                    <textarea class="form-input min-h-24" name="address" placeholder="Registered or business address">{{ old('address', $company->address) }}</textarea>
                </label>
                <label class="form-label">Primary document color
                    <input class="form-input h-12" type="color" name="primary_color" value="{{ old('primary_color', $company->primary_color ?? '#0a345f') }}">
                </label>
                <label class="form-label">Action color
                    <input class="form-input h-12" type="color" name="accent_color" value="{{ old('accent_color', $company->accent_color ?? '#0a4f93') }}">
                </label>
            </div>

            <aside class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Logo</p>
                @if($company->exists || $company->logo_path)
                    <img class="mt-3 aspect-square w-28 rounded-2xl object-cover shadow-sm" src="{{ $company->logoUrl() }}" alt="{{ $company->displayName() }}">
                @else
                    <div class="mt-3 grid aspect-square w-28 place-items-center rounded-2xl border border-dashed border-slate-300 bg-white text-center text-xs font-bold uppercase tracking-wide text-slate-400 shadow-sm">
                        Logo preview
                    </div>
                @endif
                <label class="form-label mt-4">Upload logo
                    <input class="form-input" type="file" name="logo" accept="image/*">
                </label>
                <p class="mt-3 text-xs font-semibold leading-5 text-slate-500">Use a square PNG/JPG logo. Existing documents will immediately use this company identity.</p>
            </aside>
        </div>
    </section>

    <div class="flex flex-wrap gap-3">
        <button class="btn btn-primary" type="submit">Save company identity</button>
        <a class="btn btn-secondary" href="{{ route('company-profiles.index') }}">Cancel</a>
    </div>
</form>
@endsection
