<?php
// ====================================
// index.php - Master Sky - Sistema de Gestão COMPLETO
// ====================================

// Iniciar output buffering para evitar problemas com headers
ob_start();

// Iniciar sessão
session_start();

// Incluir configurações
require_once 'config/database.php';

// Autoload das classes
spl_autoload_register(function ($className) {
    $file = __DIR__ . '/classes/' . $className . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Verificar se é uma requisição AJAX
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Se for AJAX, redirecionar para a API
if ($isAjax && strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
    ob_end_clean();
    require_once 'api/endpoints.php';
    exit;
}

// Processar logout se solicitado
if (isset($_GET['logout'])) {
    ob_end_clean();
    session_destroy();
    header('Location: index.php');
    exit;
}

// Processar login se enviado
$loginError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $usuario = $_POST['usuario'] ?? '';
    $senha = $_POST['senha'] ?? '';
    
    try {
        $auth = new Auth();
        $result = $auth->login($usuario, $senha);
        
        if ($result['success']) {
            ob_end_clean();
            header('Location: index.php');
            exit;
        } else {
            $loginError = $result['message'];
        }
    } catch (Exception $e) {
        $loginError = 'Erro interno do sistema: ' . $e->getMessage();
    }
}

// Instanciar classes necessárias
try {
    $auth = new Auth();
    $isLoggedIn = $auth->isLoggedIn();
} catch (Exception $e) {
    $isLoggedIn = false;
    $loginError = 'Erro de conexão com o banco de dados: ' . $e->getMessage();
}

// Dados do usuário se logado
$userData = null;
if ($isLoggedIn) {
    $userData = [
        'id' => $_SESSION['user_id'] ?? null,
        'nome' => $_SESSION['user_name'] ?? 'Usuário',
        'nivel' => $_SESSION['user_level'] ?? 'funcionario',
        'cargo' => $_SESSION['user_cargo'] ?? 'Funcionário'
    ];
}

// Finalizar buffering
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Sky - Sistema de Gestão</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.0/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    
    <style>
        :root {
            --primary-color: #8B0000;      /* Vinho escuro */
            --primary-light: #A52A2A;      /* Marrom avermelhado */
            --secondary-color: #DC143C;    /* Crimson */
            --secondary-light: #FF6B6B;    /* Vermelho claro */
            --accent-color: #B22222;       /* Firebrick */
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --light-bg: #f8f9fa;
            --dark-bg: #8B0000;           /* Vinho escuro para sidebar */
            --text-color: #2c3e50;
            --white: #ffffff;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--light-bg);
            color: var(--text-color);
        }

        .login-container {
            min-height: 100vh;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            position: relative;
            overflow: hidden;
        }

        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.03)" stroke-width="1"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
            animation: backgroundMove 20s ease-in-out infinite;
        }

        @keyframes backgroundMove {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }

        .login-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
            position: relative;
            z-index: 1;
            overflow: hidden;
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color), var(--primary-color));
            background-size: 200% 100%;
            animation: gradientShift 3s ease-in-out infinite;
        }

        @keyframes gradientShift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        .system-logo {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
            text-align: center;
        }

        .navbar-custom {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            box-shadow: 0 2px 15px rgba(139, 0, 0, 0.3);
            border-bottom: 3px solid rgba(255, 255, 255, 0.1);
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.4rem;
            letter-spacing: -0.025em;
        }

        .sidebar {
            background: var(--dark-bg);
            min-height: calc(100vh - 56px);
            transition: all 0.3s;
            box-shadow: 3px 0 15px rgba(139, 0, 0, 0.2);
            position: relative;
        }

        .sidebar::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 2px;
            height: 100%;
            background: linear-gradient(to bottom, transparent, rgba(255, 255, 255, 0.1), transparent);
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.85);
            border-radius: 10px;
            margin: 3px 8px;
            padding: 12px 16px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            font-weight: 500;
        }

        .sidebar .nav-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transition: all 0.3s;
        }

        .sidebar .nav-link:hover::before {
            left: 100%;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: linear-gradient(135deg, var(--secondary-color), var(--secondary-light));
            color: white;
            transform: translateX(8px);
            box-shadow: 0 6px 20px rgba(220, 20, 60, 0.4);
        }

        .sidebar .nav-link.active {
            box-shadow: 0 6px 20px rgba(220, 20, 60, 0.5);
        }

        .sidebar .nav-link i {
            width: 20px;
            text-align: center;
            margin-right: 12px;
        }

        .main-content {
            background: white;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(139, 0, 0, 0.1);
            margin: 20px;
            padding: 30px;
            position: relative;
            overflow: hidden;
        }

        .main-content::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }

        .card-custom {
            border: none;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(139, 0, 0, 0.08);
            transition: all 0.3s;
            overflow: hidden;
            position: relative;
        }

        .card-custom::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            opacity: 0;
            transition: all 0.3s;
        }

        .card-custom:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(139, 0, 0, 0.15);
        }

        .card-custom:hover::before {
            opacity: 1;
        }

        .btn-custom {
            border-radius: 10px;
            padding: 12px 24px;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            position: relative;
            overflow: hidden;
        }

        .btn-custom::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            transition: all 0.3s;
            transform: translate(-50%, -50%);
        }

        .btn-custom:hover::before {
            width: 300px;
            height: 300px;
        }

        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            box-shadow: 0 6px 20px rgba(220, 20, 60, 0.3);
        }

        .btn-primary-custom:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(220, 20, 60, 0.4);
            color: white;
        }

        .stats-card {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            color: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
            transition: all 0.3s;
        }

        .stats-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.15) 0%, transparent 70%);
            transition: all 0.3s;
        }

        .stats-card:hover {
            transform: translateY(-5px) scale(1.02);
        }

        .stats-card:hover::before {
            top: -30%;
            right: -30%;
        }

        .stats-card.success {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .stats-card.warning {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        }

        .stats-card.danger {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
        }

        .table-custom {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(139, 0, 0, 0.1);
        }

        .table-custom thead th {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            font-weight: 600;
            border: none;
            padding: 1rem;
        }

        .table-custom tbody tr {
            transition: all 0.2s;
        }

        .table-custom tbody tr:hover {
            background-color: rgba(220, 20, 60, 0.05);
            transform: scale(1.01);
        }

        .modal-custom .modal-content {
            border: none;
            border-radius: 20px;
            box-shadow: 0 15px 40px rgba(139, 0, 0, 0.2);
            overflow: hidden;
        }

        .modal-custom .modal-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border: none;
            padding: 1.5rem 2rem;
        }

        .form-control-custom {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            transition: all 0.3s;
            padding: 12px 16px;
        }

        .form-control-custom:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.2rem rgba(220, 20, 60, 0.25);
        }

        .sidebar-toggle {
            display: none;
        }

        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                left: -280px;
                width: 280px;
                z-index: 1000;
                transition: left 0.3s;
            }

            .sidebar.show {
                left: 0;
                box-shadow: 0 0 50px rgba(139, 0, 0, 0.3);
            }

            .sidebar-toggle {
                display: block;
            }

            .main-content {
                margin: 10px;
                padding: 20px;
                border-radius: 15px;
            }
        }

        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        .fade-in {
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from { 
                opacity: 0; 
                transform: translateY(20px); 
            }
            to { 
                opacity: 1; 
                transform: translateY(0); 
            }
        }

        .footer-copyright {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            text-align: center;
            padding: 15px;
            font-size: 0.85rem;
            margin-top: 20px;
        }

        .footer-copyright a {
            color: white;
            text-decoration: none;
            font-weight: 600;
        }

        .footer-copyright a:hover {
            color: #ffcccc;
        }

        .produto-row {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            border: 1px solid #dee2e6;
        }

        /* DataTables customization */
        .dataTables_wrapper .dataTables_length select,
        .dataTables_wrapper .dataTables_filter input {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 8px 12px;
        }

        .dataTables_wrapper .dataTables_filter input:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.2rem rgba(220, 20, 60, 0.25);
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)) !important;
            border-color: var(--secondary-color) !important;
            color: white !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: var(--secondary-light) !important;
            border-color: var(--secondary-color) !important;
            color: white !important;
        }
    </style>
</head>

<body>
    <?php if (!$isLoggedIn): ?>
    <!-- Tela de Login -->
    <div id="loginScreen" class="login-container d-flex align-items-center justify-content-center">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-4">
                    <div class="card login-card">
                        <div class="card-body p-5">
                            <div class="text-center mb-4">
                                <div class="system-logo">Master Sky</div>
                                <p class="text-muted">Sistema de Gestão Empresarial</p>
                            </div>
                            
                            <?php if ($loginError): ?>
                                <div class="alert alert-danger">
                                    <?= htmlspecialchars($loginError) ?>
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST">
                                <input type="hidden" name="login" value="1">
                                <div class="mb-3">
                                    <label class="form-label">Usuário</label>
                                    <input type="text" class="form-control form-control-custom" name="usuario" required value="admin">
                                </div>
                                <div class="mb-4">
                                    <label class="form-label">Senha</label>
                                    <input type="password" class="form-control form-control-custom" name="senha" required value="password">
                                </div>
                                <button type="submit" class="btn btn-primary-custom btn-custom w-100">
                                    <i class="bi bi-box-arrow-in-right me-2"></i>Entrar
                                </button>
                            </form>
                            
                            <div class="text-center mt-3">
                                <small class="text-muted">
                                    Login inicial: <strong>admin</strong> / <strong>password</strong>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- Sistema Principal -->
    <div id="mainSystem">
        <!-- Navbar -->
        <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
            <div class="container-fluid">
                <button class="btn btn-outline-light sidebar-toggle me-3" id="sidebarToggle">
                    <i class="bi bi-list"></i>
                </button>
                
                <a class="navbar-brand" href="#">
                    <i class="bi bi-stars me-2"></i>Master Sky
                </a>

                <div class="navbar-nav ms-auto">
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($userData['nome']) ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" data-section="perfil"><i class="bi bi-person me-2"></i>Perfil</a></li>
                            <li><a class="dropdown-item" href="#" data-section="configuracoes"><i class="bi bi-gear me-2"></i>Configurações</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="?logout=1"><i class="bi bi-box-arrow-right me-2"></i>Sair</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>

        <div class="container-fluid">
            <div class="row">
                <!-- Sidebar -->
                <nav class="col-md-3 col-lg-2 sidebar" id="sidebar">
                    <div class="position-sticky pt-3">
                        <ul class="nav flex-column">
                            <li class="nav-item">
                                <a class="nav-link active" href="#" data-section="dashboard">
                                    <i class="bi bi-speedometer2 me-2"></i>Dashboard
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#" data-section="colaboradores">
                                    <i class="bi bi-people me-2"></i>Colaboradores
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#" data-section="cargos">
                                    <i class="bi bi-diagram-3 me-2"></i>Cargos
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#" data-section="produtos">
                                    <i class="bi bi-box me-2"></i>Produtos
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#" data-section="clientes">
                                    <i class="bi bi-person-rolodex me-2"></i>Clientes
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#" data-section="vendas">
                                    <i class="bi bi-cart me-2"></i>Vendas
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#" data-section="financeiro">
                                    <i class="bi bi-graph-up me-2"></i>Financeiro
                                </a>
                            </li>
                        </ul>
                    </div>
                </nav>

                <!-- Conteúdo Principal -->
                <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                    
                    <!-- Dashboard -->
                    <div id="dashboard-section" class="main-content fade-in">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2><i class="bi bi-speedometer2 me-2"></i>Dashboard</h2>
                            <div class="text-muted">
                                <i class="bi bi-calendar me-1"></i>
                                <span id="currentDate"><?= date('d/m/Y') ?></span>
                            </div>
                        </div>

                        <!-- Cards de Estatísticas -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="stats-card">
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <i class="bi bi-cart-check fs-1"></i>
                                        </div>
                                        <div>
                                            <h3 class="mb-0" id="totalVendas">0</h3>
                                            <p class="mb-0">Vendas no Mês</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stats-card success">
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <i class="bi bi-currency-dollar fs-1"></i>
                                        </div>
                                        <div>
                                            <h3 class="mb-0" id="faturamento">R$ 0,00</h3>
                                            <p class="mb-0">Faturamento</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stats-card warning">
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <i class="bi bi-people fs-1"></i>
                                        </div>
                                        <div>
                                            <h3 class="mb-0" id="colaboradores">0</h3>
                                            <p class="mb-0">Colaboradores</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stats-card danger">
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <i class="bi bi-exclamation-triangle fs-1"></i>
                                        </div>
                                        <div>
                                            <h3 class="mb-0" id="estoqueBaixo">0</h3>
                                            <p class="mb-0">Estoque Baixo</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Gráficos -->
                        <div class="row">
                            <div class="col-md-8">
                                <div class="card card-custom">
                                    <div class="card-header">
                                        <h5>Vendas dos Últimos 6 Meses</h5>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="vendasChart" width="400" height="200"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card card-custom">
                                    <div class="card-header">
                                        <h5>Top Vendedores</h5>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="vendedoresChart" width="400" height="200"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Perfil -->
                    <div id="perfil-section" class="main-content d-none">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2><i class="bi bi-person me-2"></i>Meu Perfil</h2>
                        </div>

                        <div class="row">
                            <div class="col-md-8">
                                <div class="card card-custom">
                                    <div class="card-header">
                                        <h5>Informações Pessoais</h5>
                                    </div>
                                    <div class="card-body">
                                        <form id="perfilForm">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Nome</label>
                                                        <input type="text" class="form-control form-control-custom" name="nome" id="perfilNome">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Sobrenome</label>
                                                        <input type="text" class="form-control form-control-custom" name="sobrenome" id="perfilSobrenome">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Email</label>
                                                        <input type="email" class="form-control form-control-custom" name="email" id="perfilEmail">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Telefone</label>
                                                        <input type="text" class="form-control form-control-custom" name="telefone" id="perfilTelefone">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Nova Senha (deixe em branco para manter atual)</label>
                                                <input type="password" class="form-control form-control-custom" name="nova_senha">
                                            </div>
                                            <button type="button" class="btn btn-primary-custom btn-custom" onclick="salvarPerfil()">
                                                <i class="bi bi-save me-2"></i>Salvar Alterações
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card card-custom">
                                    <div class="card-header">
                                        <h5>Informações do Sistema</h5>
                                    </div>
                                    <div class="card-body">
                                        <p><strong>Cargo:</strong> <span id="perfilCargo"></span></p>
                                        <p><strong>Nível:</strong> <span id="perfilNivel"></span></p>
                                        <p><strong>Último Acesso:</strong> <span id="perfilUltimoAcesso"></span></p>
                                        <p><strong>Status:</strong> <span class="badge bg-success">Ativo</span></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Configurações -->
                    <div id="configuracoes-section" class="main-content d-none">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2><i class="bi bi-gear me-2"></i>Configurações do Sistema</h2>
                        </div>

                        <div class="card card-custom">
                            <div class="card-header">
                                <h5>Configurações Gerais</h5>
                            </div>
                            <div class="card-body">
                                <form id="configuracoesForm">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Nome da Empresa</label>
                                                <input type="text" class="form-control form-control-custom" name="empresa_nome" id="configEmpresaNome">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">CNPJ</label>
                                                <input type="text" class="form-control form-control-custom" name="empresa_cnpj" id="configEmpresaCnpj">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Sistema de Comissão</label>
                                                <select class="form-control form-control-custom" name="comissao_ativa" id="configComissaoAtiva">
                                                    <option value="true">Ativo</option>
                                                    <option value="false">Inativo</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Próximo Número de Venda</label>
                                                <input type="number" class="form-control form-control-custom" name="proxima_venda" id="configProximaVenda">
                                            </div>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-primary-custom btn-custom" onclick="salvarConfiguracoes()">
                                        <i class="bi bi-save me-2"></i>Salvar Configurações
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Colaboradores -->
                    <div id="colaboradores-section" class="main-content d-none">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2><i class="bi bi-people me-2"></i>Colaboradores</h2>
                            <button class="btn btn-primary-custom btn-custom" data-bs-toggle="modal" data-bs-target="#colaboradorModal">
                                <i class="bi bi-plus me-2"></i>Novo Colaborador
                            </button>
                        </div>

                        <div class="card card-custom">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover table-custom" id="colaboradoresTable">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Nome</th>
                                                <th>CPF</th>
                                                <th>Cargo</th>
                                                <th>Email</th>
                                                <th>Status</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Dados carregados via AJAX -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Produtos -->
                    <div id="produtos-section" class="main-content d-none">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2><i class="bi bi-box me-2"></i>Produtos</h2>
                            <button class="btn btn-primary-custom btn-custom" data-bs-toggle="modal" data-bs-target="#produtoModal">
                                <i class="bi bi-plus me-2"></i>Novo Produto
                            </button>
                        </div>

                        <div class="card card-custom">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover table-custom" id="produtosTable">
                                        <thead>
                                            <tr>
                                                <th>Código</th>
                                                <th>Nome</th>
                                                <th>Categoria</th>
                                                <th>Preço</th>
                                                <th>Comissão</th>
                                                <th>Estoque</th>
                                                <th>Status</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Dados carregados via AJAX -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Vendas -->
                    <div id="vendas-section" class="main-content d-none">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2><i class="bi bi-cart me-2"></i>Vendas</h2>
                            <button class="btn btn-primary-custom btn-custom" data-bs-toggle="modal" data-bs-target="#vendaModal">
                                <i class="bi bi-plus me-2"></i>Nova Venda
                            </button>
                        </div>

                        <div class="card card-custom">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover table-custom" id="vendasTable">
                                        <thead>
                                            <tr>
                                                <th>Número</th>
                                                <th>Data</th>
                                                <th>Cliente</th>
                                                <th>Vendedor</th>
                                                <th>Valor Total</th>
                                                <th>Status Venda</th>
                                                <th>Status Pagamento</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Dados carregados via AJAX -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Cargos -->
                    <div id="cargos-section" class="main-content d-none">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2><i class="bi bi-diagram-3 me-2"></i>Cargos</h2>
                            <button class="btn btn-primary-custom btn-custom" data-bs-toggle="modal" data-bs-target="#cargoModal">
                                <i class="bi bi-plus me-2"></i>Novo Cargo
                            </button>
                        </div>

                        <div class="card card-custom">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover table-custom" id="cargosTable">
                                        <thead>
                                            <tr>
                                                <th>Nome</th>
                                                <th>Nível de Acesso</th>
                                                <th>Comissão Padrão</th>
                                                <th>Status</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Dados carregados via AJAX -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Clientes -->
                    <div id="clientes-section" class="main-content d-none">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2><i class="bi bi-person-rolodex me-2"></i>Clientes</h2>
                            <button class="btn btn-primary-custom btn-custom" data-bs-toggle="modal" data-bs-target="#clienteModal">
                                <i class="bi bi-plus me-2"></i>Novo Cliente
                            </button>
                        </div>

                        <div class="card card-custom">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover table-custom" id="clientesTable">
                                        <thead>
                                            <tr>
                                                <th>Nome/Razão Social</th>
                                                <th>CPF/CNPJ</th>
                                                <th>Telefone</th>
                                                <th>Cidade</th>
                                                <th>Status</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Dados carregados via AJAX -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Financeiro -->
                    <div id="financeiro-section" class="main-content d-none">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2><i class="bi bi-graph-up me-2"></i>Financeiro</h2>
                            <div>
                                <button class="btn btn-outline-primary btn-custom me-2">
                                    <i class="bi bi-download me-1"></i>Exportar
                                </button>
                                <button class="btn btn-primary-custom btn-custom">
                                    <i class="bi bi-printer me-1"></i>Relatório
                                </button>
                            </div>
                        </div>

                        <!-- Cards Financeiros -->
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="card card-custom text-center">
                                    <div class="card-body">
                                        <i class="bi bi-currency-dollar fs-1 text-success mb-3"></i>
                                        <h4 id="receitaTotal">R$ 0,00</h4>
                                        <p class="text-muted">Receita Total</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card card-custom text-center">
                                    <div class="card-body">
                                        <i class="bi bi-credit-card fs-1 text-warning mb-3"></i>
                                        <h4 id="comissoesPagar">R$ 0,00</h4>
                                        <p class="text-muted">Comissões a Pagar</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card card-custom text-center">
                                    <div class="card-body">
                                        <i class="bi bi-graph-up fs-1 text-info mb-3"></i>
                                        <h4 id="lucroLiquido">R$ 0,00</h4>
                                        <p class="text-muted">Lucro Líquido</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tabela de Comissões -->
                        <div class="card card-custom">
                            <div class="card-header">
                                <h5>Comissões Pendentes</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover table-custom" id="comissoesTable">
                                        <thead>
                                            <tr>
                                                <th>Vendedor</th>
                                                <th>Venda</th>
                                                <th>Data</th>
                                                <th>Valor Venda</th>
                                                <th>% Comissão</th>
                                                <th>Valor Comissão</th>
                                                <th>Status</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Dados carregados via AJAX -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                </main>
            </div>
        </div>

        <!-- Footer Copyright -->
        <div class="footer-copyright">
            Orgulhosamente desenvolvido por <a href="https://webcoders.group" target="_blank">WebCoders.group</a> - Copyright © 2025
        </div>
    </div>

    <!-- Modais -->
    
    <!-- Modal Colaborador -->
    <div class="modal fade modal-custom" id="colaboradorModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-person-plus me-2"></i><span id="colaboradorModalTitle">Novo Colaborador</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="colaboradorForm">
                        <input type="hidden" name="id" id="colaboradorId">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Nome *</label>
                                    <input type="text" class="form-control form-control-custom" name="nome" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Sobrenome *</label>
                                    <input type="text" class="form-control form-control-custom" name="sobrenome" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">CPF *</label>
                                    <input type="text" class="form-control form-control-custom" name="cpf" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Data de Nascimento *</label>
                                    <input type="date" class="form-control form-control-custom" name="data_nascimento" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Cargo *</label>
                                    <select class="form-control form-control-custom" name="cargo_id" required>
                                        <option value="">Selecione...</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control form-control-custom" name="email">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Usuário *</label>
                                    <input type="text" class="form-control form-control-custom" name="usuario" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Senha <span id="senhaObrigatoria">*</span></label>
                                    <input type="password" class="form-control form-control-custom" name="senha">
                                    <small class="text-muted" id="senhaHelp" style="display:none;">Deixe em branco para manter a senha atual</small>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Data de Admissão *</label>
                            <input type="date" class="form-control form-control-custom" name="data_admissao" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary-custom btn-custom" onclick="salvarColaborador()">
                        <i class="bi bi-save me-2"></i>Salvar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Produto -->
    <div class="modal fade modal-custom" id="produtoModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-box me-2"></i><span id="produtoModalTitle">Novo Produto</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="produtoForm">
                        <input type="hidden" name="id" id="produtoId">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Código</label>
                                    <input type="text" class="form-control form-control-custom" name="codigo">
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label">Nome *</label>
                                    <input type="text" class="form-control form-control-custom" name="nome" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Preço de Venda *</label>
                                    <input type="number" step="0.01" min="0.01" class="form-control form-control-custom" name="preco_venda" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Comissão (%) *</label>
                                    <input type="number" step="0.01" min="0" max="100" class="form-control form-control-custom" name="comissao_percentual" required value="0">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Estoque Inicial</label>
                                    <input type="number" min="0" class="form-control form-control-custom" name="estoque_atual" value="0">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Descrição</label>
                            <textarea class="form-control form-control-custom" rows="3" name="descricao"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary-custom btn-custom" onclick="salvarProduto()">
                        <i class="bi bi-save me-2"></i>Salvar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Cliente -->
    <div class="modal fade modal-custom" id="clienteModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-person-plus me-2"></i><span id="clienteModalTitle">Novo Cliente</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="clienteForm">
                        <input type="hidden" name="id" id="clienteId">
                        <div class="mb-3">
                            <label class="form-label">Tipo de Pessoa</label>
                            <div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="tipo_pessoa" id="pf" value="fisica" checked>
                                    <label class="form-check-label" for="pf">Pessoa Física</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="tipo_pessoa" id="pj" value="juridica">
                                    <label class="form-check-label" for="pj">Pessoa Jurídica</label>
                                </div>
                            </div>
                        </div>

                        <div id="dadosPF">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Nome *</label>
                                        <input type="text" class="form-control form-control-custom" name="nome">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Sobrenome *</label>
                                        <input type="text" class="form-control form-control-custom" name="sobrenome">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">CPF *</label>
                                        <input type="text" class="form-control form-control-custom" name="cpf">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Data de Nascimento</label>
                                        <input type="date" class="form-control form-control-custom" name="data_nascimento">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="dadosPJ" style="display: none;">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Razão Social *</label>
                                        <input type="text" class="form-control form-control-custom" name="razao_social">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Nome Fantasia</label>
                                        <input type="text" class="form-control form-control-custom" name="nome_fantasia">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">CNPJ *</label>
                                        <input type="text" class="form-control form-control-custom" name="cnpj">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Inscrição Estadual</label>
                                        <input type="text" class="form-control form-control-custom" name="inscricao_estadual">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Telefone</label>
                                    <input type="text" class="form-control form-control-custom" name="telefone">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control form-control-custom" name="email">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label">Endereço *</label>
                                    <input type="text" class="form-control form-control-custom" name="endereco" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Número</label>
                                    <input type="text" class="form-control form-control-custom" name="numero">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Cidade *</label>
                                    <input type="text" class="form-control form-control-custom" name="cidade" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Estado *</label>
                                    <select class="form-control form-control-custom" name="estado" required>
                                        <option value="">Selecione...</option>
                                        <option value="SP">São Paulo</option>
                                        <option value="RJ">Rio de Janeiro</option>
                                        <option value="MG">Minas Gerais</option>
                                        <option value="RS">Rio Grande do Sul</option>
                                        <option value="PR">Paraná</option>
                                        <option value="SC">Santa Catarina</option>
                                        <option value="BA">Bahia</option>
                                        <option value="GO">Goiás</option>
                                        <option value="PE">Pernambuco</option>
                                        <option value="CE">Ceará</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">CEP</label>
                                    <input type="text" class="form-control form-control-custom" name="cep">
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary-custom btn-custom" onclick="salvarCliente()">
                        <i class="bi bi-save me-2"></i>Salvar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Cargo -->
    <div class="modal fade modal-custom" id="cargoModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-diagram-3 me-2"></i><span id="cargoModalTitle">Novo Cargo</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="cargoForm">
                        <input type="hidden" name="id" id="cargoId">
                        <div class="mb-3">
                            <label class="form-label">Nome do Cargo *</label>
                            <input type="text" class="form-control form-control-custom" name="nome" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nível de Acesso *</label>
                            <select class="form-control form-control-custom" name="nivel_acesso" required>
                                <option value="">Selecione...</option>
                                <option value="admin">Administrador</option>
                                <option value="gerente">Gerente</option>
                                <option value="vendedor">Vendedor</option>
                                <option value="funcionario">Funcionário</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Comissão Padrão (%)</label>
                            <input type="number" step="0.01" min="0" max="100" class="form-control form-control-custom" name="comissao_padrao" value="0">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Descrição</label>
                            <textarea class="form-control form-control-custom" rows="3" name="descricao"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary-custom btn-custom" onclick="salvarCargo()">
                        <i class="bi bi-save me-2"></i>Salvar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Venda -->
    <div class="modal fade modal-custom" id="vendaModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-cart-plus me-2"></i><span id="vendaModalTitle">Nova Venda</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="vendaForm">
                        <input type="hidden" name="id" id="vendaId">
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h6>Dados do Cliente</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">Cliente *</label>
                                            <select class="form-control form-control-custom" name="cliente_id" required>
                                                <option value="">Selecione o cliente...</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Protocolo de Instalação</label>
                                            <input type="text" class="form-control form-control-custom" name="protocolo_instalacao">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h6>Dados da Venda</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">Vendedor *</label>
                                            <select class="form-control form-control-custom" name="vendedor_id" required>
                                                <option value="">Selecione o vendedor...</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Data da Venda</label>
                                            <input type="date" class="form-control form-control-custom" name="data_venda" value="<?= date('Y-m-d') ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h6>Status</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">Status da Venda</label>
                                            <select class="form-control form-control-custom" name="status_venda">
                                                <option value="orcamento">Orçamento</option>
                                                <option value="confirmada">Confirmada</option>
                                                <option value="instalada">Instalada</option>
                                                <option value="cancelada">Cancelada</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Status do Pagamento</label>
                                            <select class="form-control form-control-custom" name="status_pagamento">
                                                <option value="pendente">Pendente</option>
                                                <option value="parcial">Parcial</option>
                                                <option value="pago">Pago</option>
                                                <option value="cancelado">Cancelado</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6>Produtos</h6>
                                <button type="button" class="btn btn-sm btn-primary" onclick="adicionarProdutoVenda()">
                                    <i class="bi bi-plus"></i> Adicionar Produto
                                </button>
                            </div>
                            <div class="card-body">
                                <div id="produtosVenda">
                                    <!-- Produtos adicionados dinamicamente -->
                                </div>
                                <div class="row mt-3">
                                    <div class="col-md-8"></div>
                                    <div class="col-md-4">
                                        <div class="card bg-light">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between">
                                                    <strong>Subtotal:</strong>
                                                    <span id="vendaSubtotal">R$ 0,00</span>
                                                </div>
                                                <div class="d-flex justify-content-between">
                                                    <span>Desconto:</span>
                                                    <span id="vendaDesconto">R$ 0,00</span>
                                                </div>
                                                <div class="d-flex justify-content-between">
                                                    <span>Acréscimo:</span>
                                                    <span id="vendaAcrescimo">R$ 0,00</span>
                                                </div>
                                                <hr>
                                                <div class="d-flex justify-content-between">
                                                    <strong>Total:</strong>
                                                    <strong id="vendaTotal">R$ 0,00</strong>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary-custom btn-custom" onclick="salvarVenda()">
                        <i class="bi bi-save me-2"></i>Salvar Venda
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php endif; ?>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/js/jquery.dataTables.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/js/dataTables.bootstrap5.min.js"></script>

    <script>
        // Variáveis globais
        let produtoVendaCounter = 0;
        let currentEditId = null;
        let vendedoresChart = null;
        let vendasChart = null;

        // Configuração global AJAX
        $.ajaxSetup({
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        $(document).ready(function() {
            <?php if ($isLoggedIn): ?>
            initSistema();
            <?php endif; ?>
        });

        function initSistema() {
            // Navegação entre seções
            $('.nav-link[data-section], .dropdown-item[data-section]').on('click', function(e) {
                e.preventDefault();
                
                const section = $(this).data('section');
                
                // Atualizar navegação apenas para sidebar
                if ($(this).hasClass('nav-link')) {
                    $('.sidebar .nav-link').removeClass('active');
                    $(this).addClass('active');
                }
                
                // Mostrar seção
                $('.main-content').addClass('d-none');
                $(`#${section}-section`).removeClass('d-none').addClass('fade-in');
                
                // Carregar dados
                carregarSecao(section);
                
                // Fechar sidebar em mobile
                if (window.innerWidth <= 768) {
                    $('#sidebar').removeClass('show');
                }
            });
            
            // Toggle sidebar mobile
            $('#sidebarToggle').on('click', function() {
                $('#sidebar').toggleClass('show');
            });
            
            // Alternância tipo pessoa no cliente
            $('input[name="tipo_pessoa"]').on('change', function() {
                toggleTipoPessoa();
            });
            
            // Limpar modais ao fechar
            $('.modal').on('hidden.bs.modal', function() {
                $(this).find('form')[0].reset();
                $(this).find('input[type="hidden"]').val('');
                $(this).find('.modal-title span').text($(this).find('.modal-title span').text().replace('Editar', 'Novo'));
                $('#senhaObrigatoria').show();
                $('#senhaHelp').hide();
                $('#produtosVenda').empty();
                produtoVendaCounter = 0;
                currentEditId = null;
            });
            
            // Carregar dashboard inicial
            carregarDashboard();
            carregarCargosSelect();
            carregarClientesSelect();
            carregarVendedoresSelect();
            carregarProdutosSelect();
        }

        function toggleTipoPessoa() {
            const tipo = $('input[name="tipo_pessoa"]:checked').val();
            
            if (tipo === 'fisica') {
                $('#dadosPF').show();
                $('#dadosPJ').hide();
                // Tornar campos PF obrigatórios
                $('#dadosPF input[name="nome"], #dadosPF input[name="cpf"]').attr('required', true);
                $('#dadosPJ input').attr('required', false);
            } else {
                $('#dadosPF').hide();
                $('#dadosPJ').show();
                // Tornar campos PJ obrigatórios
                $('#dadosPJ input[name="razao_social"], #dadosPJ input[name="cnpj"]').attr('required', true);
                $('#dadosPF input').attr('required', false);
            }
        }

        function carregarSecao(section) {
            switch(section) {
                case 'dashboard':
                    carregarDashboard();
                    break;
                case 'perfil':
                    carregarPerfil();
                    break;
                case 'configuracoes':
                    carregarConfiguracoes();
                    break;
                case 'colaboradores':
                    carregarColaboradores();
                    break;
                case 'produtos':
                    carregarProdutos();
                    break;
                case 'vendas':
                    carregarVendas();
                    break;
                case 'clientes':
                    carregarClientes();
                    break;
                case 'cargos':
                    carregarCargos();
                    break;
                case 'financeiro':
                    carregarFinanceiro();
                    break;
            }
        }

        function carregarDashboard() {
            $.get('api/endpoints.php?path=dashboard')
                .done(function(response) {
                    if (response.success && response.data) {
                        const data = response.data;
                        $('#totalVendas').text(data.cards?.vendas || 0);
                        $('#faturamento').text(formatMoeda(data.cards?.faturamento || 0));
                        $('#colaboradores').text(data.cards?.colaboradores || 0);
                        $('#estoqueBaixo').text(data.cards?.estoque_baixo || 0);
                        
                        // Atualizar gráficos com dados reais
                        if (data.graficos?.vendas_por_mes) {
                            criarGraficoVendas(data.graficos.vendas_por_mes);
                        } else {
                            criarGraficoVendas();
                        }
                        
                        if (data.graficos?.top_vendedores) {
                            criarGraficoVendedores(data.graficos.top_vendedores);
                        } else {
                            criarGraficoVendedores();
                        }
                    } else {
                        // Dados padrão se não conseguir carregar
                        criarGraficoVendas();
                        criarGraficoVendedores();
                    }
                })
                .fail(function() {
                    // Dados padrão se API falhar
                    criarGraficoVendas();
                    criarGraficoVendedores();
                });
        }

        function carregarPerfil() {
            $.get('api/endpoints.php?path=profile')
                .done(function(response) {
                    if (response.success) {
                        const data = response.data;
                        $('#perfilNome').val(data.nome);
                        $('#perfilSobrenome').val(data.sobrenome);
                        $('#perfilEmail').val(data.email);
                        $('#perfilTelefone').val(data.telefone);
                        $('#perfilCargo').text(data.cargo_nome);
                        $('#perfilNivel').text(data.nivel_acesso);
                        $('#perfilUltimoAcesso').text(data.ultimo_acesso ? formatDataBR(data.ultimo_acesso) : 'Nunca');
                    }
                })
                .fail(function() {
                    showAlert('error', 'Erro ao carregar perfil');
                });
        }

        function carregarConfiguracoes() {
            $.get('api/endpoints.php?path=settings')
                .done(function(response) {
                    if (response.success) {
                        const data = response.data;
                        $('#configEmpresaNome').val(data.empresa_nome || '');
                        $('#configEmpresaCnpj').val(data.empresa_cnpj || '');
                        $('#configComissaoAtiva').val(data.comissao_ativa || 'true');
                        $('#configProximaVenda').val(data.proxima_venda || '1');
                    }
                })
                .fail(function() {
                    showAlert('error', 'Erro ao carregar configurações');
                });
        }

        function carregarColaboradores() {
            $.get('api/endpoints.php?path=colaboradores')
                .done(function(response) {
                    if (response.success) {
                        const tbody = $('#colaboradoresTable tbody');
                        tbody.empty();
                        
                        response.data.forEach(function(colaborador) {
                            const row = `
                                <tr>
                                    <td>${colaborador.id}</td>
                                    <td>${colaborador.nome} ${colaborador.sobrenome}</td>
                                    <td>${formatCPF(colaborador.cpf)}</td>
                                    <td><span class="badge bg-primary">${colaborador.cargo_nome || 'N/A'}</span></td>
                                    <td>${colaborador.email || '-'}</td>
                                    <td><span class="badge ${colaborador.ativo ? 'bg-success' : 'bg-danger'}">${colaborador.ativo ? 'Ativo' : 'Inativo'}</span></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary me-1" onclick="editarColaborador(${colaborador.id})" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="excluirColaborador(${colaborador.id})" title="Excluir">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            `;
                            tbody.append(row);
                        });
                        
                        initDataTable('#colaboradoresTable');
                    }
                })
                .fail(function() {
                    showAlert('error', 'Erro ao carregar colaboradores');
                });
        }

        function carregarCargosSelect() {
            $.get('api/endpoints.php?path=cargos')
                .done(function(response) {
                    if (response.success) {
                        const selects = $('select[name="cargo_id"], select[name="vendedor_id"]');
                        selects.each(function() {
                            const $select = $(this);
                            const isVendedor = $select.attr('name') === 'vendedor_id';
                            
                            $select.empty().append('<option value="">Selecione...</option>');
                            
                            response.data.forEach(function(cargo) {
                                if (cargo.ativo) {
                                    // Para vendedor, mostrar apenas vendedores/gerentes/admin
                                    if (!isVendedor || ['vendedor', 'gerente', 'admin'].includes(cargo.nivel_acesso)) {
                                        $select.append(`<option value="${cargo.id}">${cargo.nome}</option>`);
                                    }
                                }
                            });
                        });
                    }
                })
                .fail(function() {
                    console.log('Erro ao carregar cargos');
                });
        }

        function carregarClientesSelect() {
            $.get('api/endpoints.php?path=clientes')
                .done(function(response) {
                    if (response.success) {
                        const select = $('select[name="cliente_id"]');
                        select.empty().append('<option value="">Selecione o cliente...</option>');
                        
                        response.data.forEach(function(cliente) {
                            if (cliente.ativo) {
                                const nome = cliente.tipo_pessoa === 'fisica' 
                                    ? `${cliente.nome || ''} ${cliente.sobrenome || ''}`.trim()
                                    : cliente.razao_social || '';
                                select.append(`<option value="${cliente.id}">${nome}</option>`);
                            }
                        });
                    }
                })
                .fail(function() {
                    console.log('Erro ao carregar clientes');
                });
        }

        function carregarVendedoresSelect() {
            $.get('api/endpoints.php?path=colaboradores')
                .done(function(response) {
                    if (response.success) {
                        const select = $('select[name="vendedor_id"]');
                        select.empty().append('<option value="">Selecione o vendedor...</option>');
                        
                        response.data.forEach(function(colaborador) {
                            if (colaborador.ativo && ['vendedor', 'gerente', 'admin'].includes(colaborador.nivel_acesso)) {
                                select.append(`<option value="${colaborador.id}">${colaborador.nome} ${colaborador.sobrenome}</option>`);
                            }
                        });
                    }
                })
                .fail(function() {
                    console.log('Erro ao carregar vendedores');
                });
        }

        function carregarProdutosSelect() {
            $.get('api/endpoints.php?path=produtos')
                .done(function(response) {
                    if (response.success) {
                        window.produtosList = response.data.filter(p => p.ativo);
                    }
                })
                .fail(function() {
                    console.log('Erro ao carregar produtos');
                });
        }

        function carregarProdutos() {
            $.get('api/endpoints.php?path=produtos')
                .done(function(response) {
                    if (response.success) {
                        const tbody = $('#produtosTable tbody');
                        tbody.empty();
                        
                        response.data.forEach(function(produto) {
                            const row = `
                                <tr>
                                    <td>${produto.codigo || '-'}</td>
                                    <td>${produto.nome}</td>
                                    <td>${produto.categoria_nome || '-'}</td>
                                    <td>${formatMoeda(produto.preco_venda)}</td>
                                    <td>${produto.comissao_percentual}%</td>
                                    <td><span class="badge ${produto.estoque_atual <= produto.estoque_minimo ? 'bg-warning' : 'bg-success'}">${produto.estoque_atual}</span></td>
                                    <td><span class="badge ${produto.ativo ? 'bg-success' : 'bg-danger'}">${produto.ativo ? 'Ativo' : 'Inativo'}</span></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary me-1" onclick="editarProduto(${produto.id})" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="excluirProduto(${produto.id})" title="Excluir">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            `;
                            tbody.append(row);
                        });
                        
                        initDataTable('#produtosTable');
                    }
                })
                .fail(function() {
                    showAlert('error', 'Erro ao carregar produtos');
                });
        }

        function carregarClientes() {
            $.get('api/endpoints.php?path=clientes')
                .done(function(response) {
                    if (response.success) {
                        const tbody = $('#clientesTable tbody');
                        tbody.empty();
                        
                        response.data.forEach(function(cliente) {
                            const nome = cliente.tipo_pessoa === 'fisica' 
                                ? `${cliente.nome || ''} ${cliente.sobrenome || ''}`.trim()
                                : cliente.razao_social || '';
                            const documento = cliente.tipo_pessoa === 'fisica' 
                                ? formatCPF(cliente.cpf) 
                                : formatCNPJ(cliente.cnpj);
                                
                            const row = `
                                <tr>
                                    <td>${nome}</td>
                                    <td>${documento}</td>
                                    <td>${cliente.telefone || '-'}</td>
                                    <td>${cliente.cidade || '-'}</td>
                                    <td><span class="badge ${cliente.ativo ? 'bg-success' : 'bg-danger'}">${cliente.ativo ? 'Ativo' : 'Inativo'}</span></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary me-1" onclick="editarCliente(${cliente.id})" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="excluirCliente(${cliente.id})" title="Excluir">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            `;
                            tbody.append(row);
                        });
                        
                        initDataTable('#clientesTable');
                    }
                })
                .fail(function() {
                    showAlert('error', 'Erro ao carregar clientes');
                });
        }

        function carregarCargos() {
            $.get('api/endpoints.php?path=cargos')
                .done(function(response) {
                    if (response.success) {
                        const tbody = $('#cargosTable tbody');
                        tbody.empty();
                        
                        response.data.forEach(function(cargo) {
                            const row = `
                                <tr>
                                    <td>${cargo.nome}</td>
                                    <td><span class="badge bg-info">${cargo.nivel_acesso}</span></td>
                                    <td>${cargo.comissao_padrao}%</td>
                                    <td><span class="badge ${cargo.ativo ? 'bg-success' : 'bg-danger'}">${cargo.ativo ? 'Ativo' : 'Inativo'}</span></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary me-1" onclick="editarCargo(${cargo.id})" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="excluirCargo(${cargo.id})" title="Excluir">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            `;
                            tbody.append(row);
                        });
                        
                        initDataTable('#cargosTable');
                    }
                })
                .fail(function() {
                    showAlert('error', 'Erro ao carregar cargos');
                });
        }

        function carregarVendas() {
            $.get('api/endpoints.php?path=vendas')
                .done(function(response) {
                    if (response.success) {
                        const tbody = $('#vendasTable tbody');
                        tbody.empty();
                        
                        response.data.forEach(function(venda) {
                            const statusVendaBadge = {
                                'orcamento': 'bg-warning',
                                'confirmada': 'bg-info',
                                'instalada': 'bg-success',
                                'cancelada': 'bg-danger'
                            }[venda.status_venda] || 'bg-secondary';
                            
                            const statusPagamentoBadge = {
                                'pendente': 'bg-warning',
                                'parcial': 'bg-info',
                                'pago': 'bg-success',
                                'cancelado': 'bg-danger'
                            }[venda.status_pagamento] || 