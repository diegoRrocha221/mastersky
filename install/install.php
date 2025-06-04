<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalação - Micro ERP</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%); min-height: 100vh; }
        .install-card { max-width: 600px; margin: 50px auto; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card install-card">
            <div class="card-header bg-primary text-white text-center">
                <h3>Instalação do Micro ERP</h3>
            </div>
            <div class="card-body">
                <?php
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    try {
                        // Configurações do banco
                        $host = $_POST['db_host'];
                        $dbname = $_POST['db_name'];
                        $username = $_POST['db_user'];
                        $password = $_POST['db_pass'];
                        
                        // Testar conexão
                        $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        
                        // Criar banco se não existir
                        $pdo->exec("CREATE DATABASE IF NOT EXISTS $dbname");
                        $pdo->exec("USE $dbname");
                        
                        // Executar script do banco de dados
                        $sql = file_get_contents('../database/micro_erp_schema.sql');
                        $pdo->exec($sql);
                        
                        // Criar usuário administrador
                        $stmt = $pdo->prepare("UPDATE colaboradores SET usuario = ?, senha = ? WHERE id = 1");
                        $stmt->execute([
                            $_POST['admin_user'],
                            password_hash($_POST['admin_pass'], PASSWORD_DEFAULT)
                        ]);
                        
                        // Atualizar arquivo de configuração
                        $configContent = "<?php\n";
                        $configContent .= "define('DB_HOST', '$host');\n";
                        $configContent .= "define('DB_NAME', '$dbname');\n";
                        $configContent .= "define('DB_USER', '$username');\n";
                        $configContent .= "define('DB_PASS', '$password');\n";
                        
                        file_put_contents('../config/database_config.php', $configContent);
                        
                        echo '<div class="alert alert-success">
                                <h5>Instalação Concluída!</h5>
                                <p>O sistema foi instalado com sucesso. Você pode agora fazer login com suas credenciais.</p>
                                <a href="../index.php" class="btn btn-success">Acessar Sistema</a>
                              </div>';
                        
                    } catch (Exception $e) {
                        echo '<div class="alert alert-danger">
                                <h5>Erro na Instalação</h5>
                                <p>' . $e->getMessage() . '</p>
                              </div>';
                    }
                } else {
                ?>
                
                <form method="POST">
                    <h5 class="mb-3">Configuração do Banco de Dados</h5>
                    
                    <div class="mb-3">
                        <label class="form-label">Host do Banco</label>
                        <input type="text" class="form-control" name="db_host" value="localhost" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nome do Banco</label>
                        <input type="text" class="form-control" name="db_name" value="micro_erp" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Usuário do Banco</label>
                        <input type="text" class="form-control" name="db_user" value="root" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Senha do Banco</label>
                        <input type="password" class="form-control" name="db_pass">
                    </div>
                    
                    <hr>
                    
                    <h5 class="mb-3">Administrador do Sistema</h5>
                    
                    <div class="mb-3">
                        <label class="form-label">Usuário Admin</label>
                        <input type="text" class="form-control" name="admin_user" value="admin" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Senha Admin</label>
                        <input type="password" class="form-control" name="admin_pass" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">Instalar Sistema</button>
                </form>
                
                <?php } ?>
            </div>
        </div>
    </div>
</body>
</html>