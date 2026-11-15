<div class="app-content-header">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-6">
                @hasSection('page-title')
                    <h1 class="mb-0 fs-3">@yield('page-title')</h1>
                @endif
            </div>
            <div class="col-sm-6">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
</div>
