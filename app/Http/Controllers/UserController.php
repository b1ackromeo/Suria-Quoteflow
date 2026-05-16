<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim($request->string('q')->toString());
        $role = $request->string('role')->toString();
        $role = array_key_exists($role, User::ROLES) ? $role : null;
        $status = $request->string('status')->toString();
        $status = in_array($status, ['active', 'inactive'], true) ? $status : null;

        return view('users.index', [
            'users' => User::query()
                ->when($search, function ($query) use ($search) {
                    $query->where(function ($query) use ($search) {
                        $query
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('role', 'like', "%{$search}%");
                    });
                })
                ->when($role, fn ($query, $value) => $query->where('role', $value))
                ->when($status, fn ($query, $value) => $query->where('is_active', $value === 'active'))
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
            'roles' => User::ROLES,
            'searchTerm' => $search,
            'roleFilter' => $role,
            'statusFilter' => $status,
            'summary' => [
                'total' => User::count(),
                'active' => User::where('is_active', true)->count(),
                'inactive' => User::where('is_active', false)->count(),
                'approvers' => User::whereIn('role', ['admin', 'manager'])->where('is_active', true)->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('users.form', [
            'user' => new User(['role' => 'viewer', 'is_active' => true]),
            'roles' => User::ROLES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['password'] = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ])['password'];

        $user = User::create($data);
        Audit::record('user_created', $user, null, $user->only(['id', 'email', 'role', 'is_active']));

        return redirect()->route('users.index')->with('status', 'User created.');
    }

    public function edit(User $user): View
    {
        return view('users.form', [
            'user' => $user,
            'roles' => User::ROLES,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $before = $user->only(['name', 'email', 'role', 'is_active']);
        $data = $this->validated($request, $user->id);

        if ($request->filled('password')) {
            $data['password'] = $request->validate([
                'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            ])['password'];
        }

        $user->update($data);
        Audit::record('user_updated', $user, $before, $user->only(['name', 'email', 'role', 'is_active']));

        return redirect()->route('users.index')->with('status', 'User updated.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $roles = implode(',', array_keys(User::ROLES));

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.($ignoreId ?? 'NULL').',id'],
            'role' => ['required', 'in:'.$roles],
            'is_active' => ['nullable', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active')];
    }
}
