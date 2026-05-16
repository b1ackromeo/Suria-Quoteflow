<nav class="sidebar-nav {{ ($mobile ?? false) ? 'sidebar-nav-mobile' : 'sidebar-nav-desktop' }}" aria-label="{{ ($mobile ?? false) ? 'Mobile navigation' : 'Main navigation' }}">
    <div class="nav-section">
        @foreach($primaryNav as $item)
            <a class="nav-link {{ $item['active'] ? 'nav-link-active' : '' }}" href="{{ $item['route'] }}">
                <x-icon :name="$item['icon']" class="nav-icon" />
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach
    </div>

    <div class="nav-section">
        <p class="nav-heading">Outgoing revenue</p>
        @foreach($salesLinks as $link)
            <a class="nav-link {{ $isModule($link['module']) ? 'nav-link-active' : '' }}" href="{{ route('documents.index', $link['module']) }}">
                <x-icon :name="$link['icon']" class="nav-icon" />
                <span>{{ $link['label'] }}</span>
            </a>
        @endforeach
    </div>

    <div class="nav-section">
        <p class="nav-heading">Incoming procurement</p>
        @foreach($procurementLinks as $link)
            <a class="nav-link {{ $isModule($link['module']) ? 'nav-link-active' : '' }}" href="{{ route('documents.index', $link['module']) }}">
                <x-icon :name="$link['icon']" class="nav-icon" />
                <span>{{ $link['label'] }}</span>
            </a>
        @endforeach
    </div>

    <div class="nav-section">
        <p class="nav-heading">Directory</p>
        @if(auth()->user()->hasRole('admin', 'manager', 'sales'))
            <a class="nav-link {{ request()->routeIs('customers.*') ? 'nav-link-active' : '' }}" href="{{ route('customers.index') }}"><x-icon name="customers" class="nav-icon" /><span>Customers</span></a>
        @endif
        @if(auth()->user()->hasRole('admin', 'manager', 'procurement'))
            <a class="nav-link {{ request()->routeIs('suppliers.*') ? 'nav-link-active' : '' }}" href="{{ route('suppliers.index') }}"><x-icon name="customers" class="nav-icon" /><span>Suppliers</span></a>
        @endif
        @if(auth()->user()->hasRole('admin', 'manager', 'sales', 'procurement'))
            <a class="nav-link {{ request()->routeIs('products.*') ? 'nav-link-active' : '' }}" href="{{ route('products.index') }}"><x-icon name="products" class="nav-icon" /><span>Products & Services</span></a>
        @endif
    </div>

    @if(auth()->user()->hasRole('admin', 'manager'))
        <div class="nav-section">
            <p class="nav-heading">Control</p>
            @if(auth()->user()->hasRole('admin'))
                <a class="nav-link {{ request()->routeIs('users.*') ? 'nav-link-active' : '' }}" href="{{ route('users.index') }}"><x-icon name="admin" class="nav-icon" /><span>Users</span></a>
                <a class="nav-link {{ request()->routeIs('company-profiles.*') ? 'nav-link-active' : '' }}" href="{{ route('company-profiles.index') }}"><x-icon name="admin" class="nav-icon" /><span>Company Identity</span></a>
            @endif
            <a class="nav-link {{ request()->routeIs('audit.*') ? 'nav-link-active' : '' }}" href="{{ route('audit.index') }}"><x-icon name="admin" class="nav-icon" /><span>Audit Trail</span></a>
        </div>
    @endif
</nav>
