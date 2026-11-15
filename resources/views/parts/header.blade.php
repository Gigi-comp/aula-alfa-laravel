<nav class="app-header navbar navbar-expand bg-body">
    <div class="container-fluid">
        {{-- Links à esquerda --}}
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button" aria-label="Alternar menu lateral">
                    <i class="bi bi-list"></i>
                </a>
            </li>
            <li class="nav-item d-none d-md-block">
                <a href="{{ route('home') }}" class="nav-link">Início</a>
            </li>
        </ul>

        {{-- Links à direita --}}
        <ul class="navbar-nav ms-auto">
            <li class="nav-item">
                <a class="nav-link" href="#" data-lte-toggle="fullscreen" aria-label="Alternar tela cheia">
                    <i data-lte-icon="maximize" class="bi bi-arrows-fullscreen"></i>
                    <i data-lte-icon="minimize" class="bi bi-fullscreen-exit d-none"></i>
                </a>
            </li>

            {{-- Menu do usuário: nome + botão Sair --}}
            <li class="nav-item user-menu d-flex align-items-center">
                <span class="nav-link">
                    <i class="bi bi-person-circle me-1" aria-hidden="true"></i>
                    <span class="d-none d-md-inline">{{ auth()->user()?->name }}</span>
                </span>
                {{-- Logout é POST com CSRF; nunca um link GET. --}}
                <form action="{{ route('logout') }}" method="POST" class="ms-2">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-danger">Sair</button>
                </form>
            </li>
        </ul>
    </div>
</nav>
