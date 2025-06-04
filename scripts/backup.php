<?php
class BackupManager {
    private $db;
    private $backupPath;
    private $maxBackups;
    
    public function __construct() {
        require_once '../config/database.php';
        $database = new Database();
        $this->db = $database->connect();
        $this->backupPath = __DIR__ . '/../backups/';
        $this->maxBackups = 30; // Manter últimos 30 backups
        
        // Criar diretório se não existir
        if (!is_dir($this->backupPath)) {
            mkdir($this->backupPath, 0755, true);
        }
    }
    
    public function createBackup($includeUploads = true) {
        try {
            $timestamp = date('Y-m-d_H-i-s');
            $backupDir = $this->backupPath . "backup_{$timestamp}/";
            
            if (!mkdir($backupDir, 0755, true)) {
                throw new Exception("Erro ao criar diretório de backup");
            }
            
            // Backup do banco de dados
            $this->backupDatabase($backupDir);
            
            // Backup dos arquivos de upload
            if ($includeUploads) {
                $this->backupUploads($backupDir);
            }
            
            // Backup das configurações
            $this->backupConfig($backupDir);
            
            // Criar arquivo ZIP
            $zipFile = $this->backupPath . "backup_{$timestamp}.zip";
            $this->createZip($backupDir, $zipFile);
            
            // Remover diretório temporário
            $this->removeDirectory($backupDir);
            
            // Limpar backups antigos
            $this->cleanOldBackups();
            
            return [
                'success' => true,
                'file' => $zipFile,
                'size' => filesize($zipFile),
                'timestamp' => $timestamp
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    private function backupDatabase($backupDir) {
        $sqlFile = $backupDir . 'database.sql';
        
        // Obter informações de conexão
        $host = DB_HOST;
        $dbname = DB_NAME;
        $username = DB_USER;
        $password = DB_PASS;
        
        // Comando mysqldump
        $command = "mysqldump --host={$host} --user={$username} --password={$password} " .
                  "--single-transaction --routines --triggers {$dbname} > {$sqlFile}";
        
        exec($command, $output, $returnVar);
        
        if ($returnVar !== 0) {
            // Fallback: backup manual via PHP
            $this->manualDatabaseBackup($sqlFile);
        }
    }
    
    private function manualDatabaseBackup($sqlFile) {
        $handle = fopen($sqlFile, 'w');
        
        if (!$handle) {
            throw new Exception("Erro ao criar arquivo de backup do banco");
        }
        
        // Header do arquivo
        fwrite($handle, "-- Backup Micro ERP - " . date('Y-m-d H:i:s') . "\n");
        fwrite($handle, "-- Database: " . DB_NAME . "\n\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS = 0;\n\n");
        
        // Obter lista de tabelas
        $stmt = $this->db->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($tables as $table) {
            // Estrutura da tabela
            $stmt = $this->db->query("SHOW CREATE TABLE `{$table}`");
            $createTable = $stmt->fetch(PDO::FETCH_ASSOC);
            
            fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n");
            fwrite($handle, $createTable['Create Table'] . ";\n\n");
            
            // Dados da tabela
            $stmt = $this->db->query("SELECT * FROM `{$table}`");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $values = array_map(function($value) {
                    return $value === null ? 'NULL' : $this->db->quote($value);
                }, array_values($row));
                
                $columns = implode('`, `', array_keys($row));
                $valuesStr = implode(', ', $values);
                
                fwrite($handle, "INSERT INTO `{$table}` (`{$columns}`) VALUES ({$valuesStr});\n");
            }
            
            fwrite($handle, "\n");
        }
        
        fwrite($handle, "SET FOREIGN_KEY_CHECKS = 1;\n");
        fclose($handle);
    }
    
    private function backupUploads($backupDir) {
        $uploadsDir = __DIR__ . '/../uploads/';
        $backupUploadsDir = $backupDir . 'uploads/';
        
        if (is_dir($uploadsDir)) {
            $this->copyDirectory($uploadsDir, $backupUploadsDir);
        }
    }
    
    private function backupConfig($backupDir) {
        $configDir = __DIR__ . '/../config/';
        $backupConfigDir = $backupDir . 'config/';
        
        if (is_dir($configDir)) {
            $this->copyDirectory($configDir, $backupConfigDir);
        }
        
        // Criar arquivo com informações do sistema
        $systemInfo = [
            'version' => SISTEMA_VERSAO ?? '1.0.0',
            'backup_date' => date('Y-m-d H:i:s'),
            'php_version' => PHP_VERSION,
            'mysql_version' => $this->db->query('SELECT VERSION()')->fetchColumn(),
            'server_info' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
        ];
        
        file_put_contents($backupDir . 'system_info.json', json_encode($systemInfo, JSON_PRETTY_PRINT));
    }
    
    private function createZip($sourceDir, $zipFile) {
        $zip = new ZipArchive();
        
        if ($zip->open($zipFile, ZipArchive::CREATE) !== TRUE) {
            throw new Exception("Erro ao criar arquivo ZIP");
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($iterator as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($sourceDir));
                $zip->addFile($filePath, $relativePath);
            }
        }
        
        $zip->close();
    }
    
    private function copyDirectory($source, $destination) {
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $item) {
            $target = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
            
            if ($item->isDir()) {
                if (!is_dir($target)) {
                    mkdir($target, 0755, true);
                }
            } else {
                copy($item, $target);
            }
        }
    }
    
    private function removeDirectory($dir) {
        if (!is_dir($dir)) return;
        
        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        
        rmdir($dir);
    }
    
    private function cleanOldBackups() {
        $backups = glob($this->backupPath . 'backup_*.zip');
        
        if (count($backups) > $this->maxBackups) {
            // Ordenar por data de modificação
            usort($backups, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            // Remover backups mais antigos
            $toRemove = array_slice($backups, 0, count($backups) - $this->maxBackups);
            
            foreach ($toRemove as $backup) {
                unlink($backup);
            }
        }
    }
    
    public function listBackups() {
        $backups = glob($this->backupPath . 'backup_*.zip');
        $result = [];
        
        foreach ($backups as $backup) {
            $filename = basename($backup);
            $timestamp = str_replace(['backup_', '.zip'], '', $filename);
            
            $result[] = [
                'filename' => $filename,
                'path' => $backup,
                'size' => filesize($backup),
                'date' => date('d/m/Y H:i:s', filemtime($backup)),
                'timestamp' => $timestamp
            ];
        }
        
        // Ordenar por data (mais recente primeiro)
        usort($result, function($a, $b) {
            return strcmp($b['timestamp'], $a['timestamp']);
        });
        
        return $result;
    }
    
    public function restoreBackup($backupFile) {
        try {
            if (!file_exists($backupFile)) {
                throw new Exception("Arquivo de backup não encontrado");
            }
            
            $tempDir = $this->backupPath . 'temp_restore_' . time() . '/';
            
            // Extrair ZIP
            $zip = new ZipArchive();
            if ($zip->open($backupFile) !== TRUE) {
                throw new Exception("Erro ao abrir arquivo de backup");
            }
            
            $zip->extractTo($tempDir);
            $zip->close();
            
            // Restaurar banco de dados
            if (file_exists($tempDir . 'database.sql')) {
                $this->restoreDatabase($tempDir . 'database.sql');
            }
            
            // Restaurar uploads
            if (is_dir($tempDir . 'uploads/')) {
                $uploadsDir = __DIR__ . '/../uploads/';
                $this->removeDirectory($uploadsDir);
                $this->copyDirectory($tempDir . 'uploads/', $uploadsDir);
            }
            
            // Limpar diretório temporário
            $this->removeDirectory($tempDir);
            
            return ['success' => true, 'message' => 'Backup restaurado com sucesso'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    private function restoreDatabase($sqlFile) {
        $sql = file_get_contents($sqlFile);
        
        // Executar comandos SQL
        $commands = explode(';', $sql);
        
        foreach ($commands as $command) {
            $command = trim($command);
            if (!empty($command)) {
                $this->db->exec($command);
            }
        }
    }
}

// Script executável via linha de comando
if (php_sapi_name() === 'cli') {
    $backup = new BackupManager();
    $result = $backup->createBackup();
    
    if ($result['success']) {
        echo "Backup criado com sucesso: " . $result['file'] . "\n";
        echo "Tamanho: " . number_format($result['size'] / 1024 / 1024, 2) . " MB\n";
    } else {
        echo "Erro ao criar backup: " . $result['message'] . "\n";
        exit(1);
    }
}

?>