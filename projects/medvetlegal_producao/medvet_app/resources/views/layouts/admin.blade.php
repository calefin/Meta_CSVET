<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'MedVetLegal') | Painel Admin</title>

    <!-- PWA -->
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="theme-color" content="#0e2a47">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('assets/favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('assets/favicon-16.png') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('assets/icon-192.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="MedVetLegal">

    <!-- Google Font -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Bootstrap 4 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <!-- AdminLTE -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    
    <style>
        /* Dark mode override */
        body.dark-mode {
            background-color: #1a1a2e;
            color: #e0e0e0;
        }
        body.dark-mode .main-header {
            background-color: #16213e;
            border-bottom-color: #0f3460;
        }
        body.dark-mode .content-wrapper {
            background-color: #1a1a2e;
        }
        body.dark-mode .main-sidebar {
            background-color: #0f3460;
        }
        body.dark-mode .small-box {
            opacity: 0.9;
        }
        body.dark-mode .card {
            background-color: #16213e;
            color: #e0e0e0;
        }
        body.dark-mode .table {
            color: #e0e0e0;
        }
        body.dark-mode .bg-light {
            background-color: #0f3460 !important;
            color: #e0e0e0;
        }
        body.dark-mode .bg-info-light {
            background-color: #16213e !important;
            color: #e0e0e0;
        }
    </style>
    
    @stack('styles')
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="{{ route('admin.dashboard') }}" class="nav-link">Dashboard</a>
            </li>
        </ul>

        <ul class="navbar-nav ml-auto">
            <!-- Dark Mode Toggle -->
            <li class="nav-item">
                <a class="nav-link" href="#" id="dark-mode-toggle" role="button">
                    <i class="fas fa-moon"></i>
                </a>
            </li>
            <!-- User Menu -->
            <li class="nav-item dropdown">
                <a class="nav-link" data-toggle="dropdown" href="#">
                    <i class="fas fa-user"></i> {{ Auth::user()->name }}
                </a>
                <div class="dropdown-menu dropdown-menu-right">
                    <a href="{{ route('admin.profile.edit') }}" class="dropdown-item">
                        <i class="fas fa-user-edit mr-2"></i> Meu Perfil
                    </a>
                    <div class="dropdown-divider"></div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item">
                            <i class="fas fa-sign-out-alt mr-2"></i> Sair
                        </button>
                    </form>
                </div>
            </li>
        </ul>
    </nav>

    <!-- Sidebar -->
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a href="{{ route('admin.dashboard') }}" class="brand-link">
            <i class="fas fa-stethoscope text-warning ml-2"></i>
            <span class="brand-text font-weight-light">MedVetLegal</span>
        </a>

        <div class="sidebar">
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">
                    <li class="nav-item">
                        <a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-tachometer-alt"></i>
                            <p>Dashboard</p>
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a href="{{ route('admin.tickets.index') }}" class="nav-link {{ request()->routeIs('admin.tickets.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-ticket-alt"></i>
                            <p>Tickets</p>
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a href="{{ route('admin.clientes.index') }}" class="nav-link {{ request()->routeIs('admin.clientes.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-users"></i>
                            <p>Clientes</p>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="{{ route('admin.posts.index') }}" class="nav-link {{ request()->routeIs('admin.posts.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-blog"></i>
                            <p>Blog</p>
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-graduation-cap"></i>
                            <p>Cursos</p>
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-search"></i>
                            <p>SEO</p>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </aside>

    <!-- Content -->
    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid">
                @yield('header')
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show">
                        {{ session('success') }}
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show">
                        {{ session('error') }}
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                @endif

                @yield('content')
            </div>
        </section>
    </div>

    <!-- Footer -->
    <footer class="main-footer">
        <strong>&copy; {{ date('Y') }} MedVetLegal - Perícia Veterinária.</strong>
        <div class="float-right d-none d-sm-inline-block">
            <b>Versão:</b> v{{ config('app.version') }} (Build {{ config('app.build') }})
        </div>
    </footer>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

<script>
// Dark Mode Toggle
document.getElementById('dark-mode-toggle').addEventListener('click', function(e) {
    e.preventDefault();
    document.body.classList.toggle('dark-mode');
    const icon = this.querySelector('i');
    
    if (document.body.classList.contains('dark-mode')) {
        icon.classList.replace('fa-moon', 'fa-sun');
        localStorage.setItem('darkMode', 'enabled');
    } else {
        icon.classList.replace('fa-sun', 'fa-moon');
        localStorage.setItem('darkMode', 'disabled');
    }
});

// Load dark mode preference
if (localStorage.getItem('darkMode') === 'enabled') {
    document.body.classList.add('dark-mode');
    document.querySelector('#dark-mode-toggle i').classList.replace('fa-moon', 'fa-sun');
}
</script>

<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
        navigator.serviceWorker.register('/sw.js', { scope: '/admin/' }).catch(function (error) {
            console.error('Falha ao registrar service worker do painel:', error);
        });
    });
}
</script>

@stack('scripts')
</body>
</html>