<?php
class DeployManager {
    private $logger;
    private $backup;
    
    public function __construct() {
        $this->logger = new Logger();
        $this->backup = new BackupManager();
    }
    
    public function deploy($version = null) {
        try {
            $this->logger->info("Iniciando processo de deploy", ['version' => $version]);
            
            // 1. Criar backup antes do deploy
            $this->logger->info("Criando backup pré-deploy");
            $backupResult = $this->backup->createBackup();
            
            if (!$backupResult['success']) {
                throw new Exception("Falha ao criar backup: " . $backupResult['message']);
            }
            
            // 2. Validar ambiente
            $this->validateEnvironment();
            
            // 3. Executar migrações de banco
            $this->runMigrations();
            
            // 4. Limpar cache
            $this->clearCache();
            
            // 5. Otimizar sistema
            $this->optimizeSystem();
            
            // 6. Verificar integridade
            $this->verifyIntegrity();
            
            $this->logger->info("Deploy concluído com sucesso", ['version' => $version]);
            
            return [
                'success' => true,
                'message' => 'Deploy realizado com sucesso',
                'backup_file' => $backupResult['file'] ?? null
            ];
            
        } catch (Exception $e) {
            $this->logger->error("Erro durante deploy", ['error' => $e->getMessage()]);
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    private function validateEnvironment() {
        $this->logger->info("Validando ambiente");
        
        // Verificar versão PHP
        if (version_compare(PHP_VERSION, '7.4.0', '<')) {
            throw new Exception("PHP 7.4 ou superior é necessário. Versão atual: " . PHP_VERSION);
        }
        
        // Verificar extensões necessárias
        $requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'openssl', 'zip'];
        
        foreach ($requiredExtensions as $extension) {
            if (!extension_loaded($extension)) {
                throw new Exception("Extensão PHP necessária não encontrada: $extension");
            }
        }
        
        // Verificar permissões de diretórios
        $writableDirs = ['logs', 'uploads', 'backups', 'cache'];
        
        foreach ($writableDirs as $dir) {
            $path = __DIR__ . "/../$dir/";
            
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
            
            if (!is_writable($path)) {
                throw new Exception("Diretório não tem permissão de escrita: $dir");
            }
        }
        
        // Verificar conectividade com banco
        try {
            $database = new Database();
            $db = $database->connect();
            $db->query("SELECT 1");
        } catch (Exception $e) {
            throw new Exception("Erro de conexão com banco de dados: " . $e->getMessage());
        }
    }
    
    private function runMigrations() {
        $this->logger->info("Executando migrações de banco");
        
        $migrationDir = __DIR__ . '/../database/migrations/';
        
        if (!is_dir($migrationDir)) {
            return;
        }
        
        $database = new Database();
        $db = $database->connect();
        
        // Criar tabela de migrações se não existir
        $db->exec("
            CREATE TABLE IF NOT EXISTS migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                filename VARCHAR(255) NOT NULL,
                executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_filename (filename)
            )
        ");
        
        // Executar migrações pendentes
        $migrations = glob($migrationDir . '*.sql');
        sort($migrations);
        
        foreach ($migrations as $migration) {
            $filename = basename($migration);
            
            // Verificar se migração já foi executada
            $stmt = $db->prepare("SELECT id FROM migrations WHERE filename = ?");
            $stmt->execute([$filename]);
            
            if (!$stmt->fetch()) {
                $this->logger->info("Executando migração: $filename");
                
                $sql = file_get_contents($migration);
                $db->exec($sql);
                
                // Registrar migração executada
                $stmt = $db->prepare("INSERT INTO migrations (filename) VALUES (?)");
                $stmt->execute([$filename]);
            }
        }
    }
    
    private function clearCache() {
        $this->logger->info("Limpando cache");
        
        $cacheDir = __DIR__ . '/../cache/';
        
        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . '*');
            
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
        
        // Limpar cache do OPcache se disponível
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
    }
    
    private function optimizeSystem() {
        $this->logger->info("Otimizando sistema");
        
        $database = new Database();
        $db = $database->connect();
        
        // Otimizar tabelas do banco
        $stmt = $db->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($tables as $table) {
            $db->exec("OPTIMIZE TABLE `$table`");
        }
        
        // Analisar tabelas para estatísticas
        foreach ($tables as $table) {
            $db->exec("ANALYZE TABLE `$table`");
        }
    }
    
    private function verifyIntegrity() {
        $this->logger->info("Verificando integridade do sistema");
        
        $database = new Database();
        $db = $database->connect();
        
        // Verificar integridade referencial
        $checks = [
            "SELECT COUNT(*) FROM colaboradores c LEFT JOIN cargos car ON c.cargo_id = car.id WHERE car.id IS NULL",
            "SELECT COUNT(*) FROM vendas v LEFT JOIN clientes c ON v.cliente_id = c.id WHERE c.id IS NULL",
            "SELECT COUNT(*) FROM vendas v LEFT JOIN colaboradores col ON v.vendedor_id = col.id WHERE col.id IS NULL",
            "SELECT COUNT(*) FROM itens_venda iv LEFT JOIN vendas v ON iv.venda_id = v.id WHERE v.id IS NULL",
            "SELECT COUNT(*) FROM itens_venda iv LEFT JOIN produtos p ON iv.produto_id = p.id WHERE p.id IS NULL"
        ];
        
        foreach ($checks as $check) {
            $stmt = $db->query($check);
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                throw new Exception("Problema de integridade detectado: $count registros órfãos");
            }
        }
        
        // Verificar arquivos essenciais
        $essentialFiles = [
            'index.php',
            'config/database.php',
            'classes/Auth.php',
            'api/endpoints.php'
        ];
        
        foreach ($essentialFiles as $file) {
            $path = __DIR__ . "/../$file";
            
            if (!file_exists($path)) {
                throw new Exception("Arquivo essencial não encontrado: $file");
            }
        }
    }
}

// Script executável via linha de comando
if (php_sapi_name() === 'cli') {
    $deploy = new DeployManager();
    $version = $argv[1] ?? null;
    $result = $deploy->deploy($version);
    
    if ($result['success']) {
        echo "Deploy realizado com sucesso!\n";
        if (isset($result['backup_file'])) {
            echo "Backup criado: " . $result['backup_file'] . "\n";
        }
    } else {
        echo "Erro no deploy: " . $result['message'] . "\n";
        exit(1);
    }
}

// ====================================
// database/migrations/001_add_security_tables.sql
// ====================================
?>
