<?php
class Logger {
    private $logPath;
    private $maxFileSize = 10485760; // 10MB
    private $maxFiles = 5;
    
    public function __construct() {
        $this->logPath = LOG_PATH ?? __DIR__ . '/../logs/';
        
        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0755, true);
        }
    }
    
    public function log($level, $message, $context = []) {
        if (!LOG_ENABLE) return;
        
        $levels = ['DEBUG', 'INFO', 'WARNING', 'ERROR', 'CRITICAL'];
        $configLevel = LOG_LEVEL ?? 'INFO';
        
        if (array_search($level, $levels) < array_search($configLevel, $levels)) {
            return;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
        $user = $_SESSION['user_id'] ?? 'guest';
        
        $logEntry = [
            'timestamp' => $timestamp,
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'ip' => $ip,
            'user_id' => $user,
            'memory_usage' => memory_get_usage(true),
            'execution_time' => microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true))
        ];
        
        $logLine = json_encode($logEntry) . "\n";
        
        $logFile = $this->logPath . date('Y-m-d') . '.log';
        
        // Rotacionar logs se necessário
        if (file_exists($logFile) && filesize($logFile) > $this->maxFileSize) {
            $this->rotateLogs($logFile);
        }
        
        file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
        
        // Para logs críticos, enviar notificação
        if ($level === 'CRITICAL' || $level === 'ERROR') {
            $this->notifyCriticalError($message, $context);
        }
    }
    
    public function debug($message, $context = []) {
        $this->log('DEBUG', $message, $context);
    }
    
    public function info($message, $context = []) {
        $this->log('INFO', $message, $context);
    }
    
    public function warning($message, $context = []) {
        $this->log('WARNING', $message, $context);
    }
    
    public function error($message, $context = []) {
        $this->log('ERROR', $message, $context);
    }
    
    public function critical($message, $context = []) {
        $this->log('CRITICAL', $message, $context);
    }
    
    private function rotateLogs($logFile) {
        for ($i = $this->maxFiles - 1; $i > 0; $i--) {
            $oldFile = $logFile . '.' . $i;
            $newFile = $logFile . '.' . ($i + 1);
            
            if (file_exists($oldFile)) {
                rename($oldFile, $newFile);
            }
        }
        
        if (file_exists($logFile)) {
            rename($logFile, $logFile . '.1');
        }
        
        // Remover logs muito antigos
        $veryOldFile = $logFile . '.' . ($this->maxFiles + 1);
        if (file_exists($veryOldFile)) {
            unlink($veryOldFile);
        }
    }
    
    private function notifyCriticalError($message, $context) {
        try {
            $emailService = new EmailService();
            
            $content = "
                <h2>Erro Crítico no Sistema</h2>
                <p><strong>Mensagem:</strong> {$message}</p>
                <p><strong>Data/Hora:</strong> " . date('d/m/Y H:i:s') . "</p>
                <p><strong>IP:</strong> " . ($_SERVER['REMOTE_ADDR'] ?? 'N/A') . "</p>
                <p><strong>Usuário:</strong> " . ($_SESSION['user_id'] ?? 'N/A') . "</p>
                <p><strong>URL:</strong> " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . "</p>
                <p><strong>Contexto:</strong></p>
                <pre>" . print_r($context, true) . "</pre>
            ";
            
            // Enviar para administradores
            $database = new Database();
            $db = $database->connect();
            
            $sql = "SELECT email FROM colaboradores c 
                    JOIN cargos car ON c.cargo_id = car.id 
                    WHERE car.nivel_acesso = 'admin' AND c.ativo = 1 AND c.email IS NOT NULL";
            
            $stmt = $db->prepare($sql);
            $stmt->execute();
            $admins = $stmt->fetchAll();
            
            foreach ($admins as $admin) {
                $emailService->send(
                    $admin['email'],
                    'CRÍTICO - Erro no Sistema Micro ERP',
                    $content
                );
            }
            
        } catch (Exception $e) {
            error_log("Erro ao enviar notificação crítica: " . $e->getMessage());
        }
    }
    
    public function getRecentLogs($level = null, $limit = 100) {
        $logFile = $this->logPath . date('Y-m-d') . '.log';
        
        if (!file_exists($logFile)) {
            return [];
        }
        
        $lines = file($logFile, FILE_IGNORE_NEW_LINES);
        $logs = [];
        
        foreach (array_reverse($lines) as $line) {
            if (count($logs) >= $limit) break;
            
            $logEntry = json_decode($line, true);
            
            if ($logEntry && ($level === null || $logEntry['level'] === $level)) {
                $logs[] = $logEntry;
            }
        }
        
        return $logs;
    }
    
    public function searchLogs($query, $startDate = null, $endDate = null) {
        $results = [];
        $startDate = $startDate ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $endDate ?? date('Y-m-d');
        
        $current = strtotime($startDate);
        $end = strtotime($endDate);
        
        while ($current <= $end) {
            $logFile = $this->logPath . date('Y-m-d', $current) . '.log';
            
            if (file_exists($logFile)) {
                $lines = file($logFile, FILE_IGNORE_NEW_LINES);
                
                foreach ($lines as $line) {
                    $logEntry = json_decode($line, true);
                    
                    if ($logEntry && stripos($logEntry['message'], $query) !== false) {
                        $results[] = $logEntry;
                    }
                }
            }
            
            $current = strtotime('+1 day', $current);
        }
        
        return $results;
    }
}
?>