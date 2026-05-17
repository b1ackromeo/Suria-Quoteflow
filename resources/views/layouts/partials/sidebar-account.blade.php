<div class="sidebar-account-panel" aria-label="Current user">
    <div class="sidebar-account-user">
        <span class="sidebar-account-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
        <span class="min-w-0">
            <span class="block truncate text-sm font-bold text-slate-950">{{ auth()->user()->name }}</span>
            <span class="block text-xs font-semibold text-slate-500">{{ \App\Models\User::ROLES[auth()->user()->role] ?? auth()->user()->role }}</span>
        </span>
    </div>

    <form method="post" action="{{ route('logout') }}">
        <input type="hidden" name="_token" value="{{ csrf_token() }}">
        <button type="submit" class="sidebar-account-logout">Logout</button>
    </form>
</div>
