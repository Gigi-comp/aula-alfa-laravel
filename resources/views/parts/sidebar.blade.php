<aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
    <div class="sidebar-brand">
        <a href="{{ route('home') }}" class="brand-link">
            <span class="brand-text fw-light">Rede Patas</span>
        </a>
    </div>
    <div class="sidebar-wrapper">
        <nav class="mt-2" aria-label="Navegação principal">
            <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" data-accordion="true" id="navigation">
                @if (auth()->user()?->isAdmin())
                    <li class="nav-item">
                        <a href="{{ route('register') }}" class="nav-link">
                            <i class="nav-icon bi bi-person-plus"></i>
                            <p>Novo Usuario</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('admin.users.index') }}" class="nav-link">
                            <i class="nav-icon bi bi-people"></i>
                            <p>Listar Usuario</p>
                        </a>
                    </li>
                @else
                    <li class="nav-item">
                        <a href="{{ route('home') }}" class="nav-link">
                            <i class="nav-icon bi bi-house"></i>
                            <p>Início</p>
                        </a>
                    </li>
                @endif
            </ul>
        </nav>
    </div>
</aside>
