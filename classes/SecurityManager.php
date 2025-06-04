<?php
class SecurityManager {
    private $db;
    private $maxAttempts = 5;
    private $lockoutTime = 900; // 15 minutos
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
    }
    
    public function checkBruteForce($ip, $username = null) {
        $sql = "SELECT COUNT(*) as attempts, MAX(created_at) as last_attempt 
                FROM security_logs 
                WHERE ip = ? AND event_type = 'failed_login' 
                AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)";
        
        $params = [$ip, $this->lockoutTime];
        
        if ($username) {
            $sql .= " AND details LIKE ?";
            $params[] = "%$username%";
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        
        if ($result['attempts'] >= $this->maxAttempts) {
            $this->logSecurityEvent('brute_force_blocked', $ip, "Tentativas: {$result['attempts']}");
            return false;
        }
        
        return true;
    }
    
    public function logSecurityEvent($eventType, $ip, $details = '', $userId = null) {
        try {
            $sql = "INSERT INTO security_logs (event_type, ip, user_id, details, user_agent) 
                    VALUES (?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $eventType,
                $ip,
                $userId,
                $details,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            
        } catch (Exception $e) {
            error_log("Erro ao registrar evento de segurança: " . $e->getMessage());
        }
    }
    
    public function validateCSRF($token) {
        session_start();
        
        if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            $this->logSecurityEvent('csrf_attack', $_SERVER['REMOTE_ADDR'] ?? '');
            return false;
        }
        
        return true;
    }
    
    public function generateCSRFToken() {
        session_start();
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    public function sanitizeInput($input, $type = 'string') {
        if (is_array($input)) {
            return array_map(function($item) use ($type) {
                return $this->sanitizeInput($item, $type);
            }, $input);
        }
        
        switch ($type) {
            case 'email':
                return filter_var($input, FILTER_SANITIZE_EMAIL);
            case 'url':
                return filter_var($input, FILTER_SANITIZE_URL);
            case 'int':
                return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
            case 'float':
                return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            case 'string':
            default:
                return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
        }
    }
    
    public function validatePassword($password) {
        $errors = [];
        
        if (strlen($password) < 8) {
            $errors[] = 'Senha deve ter pelo menos 8 caracteres';
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Senha deve conter pelo menos uma letra maiúscula';
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Senha deve conter pelo menos uma letra minúscula';
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Senha deve conter pelo menos um número';
        }
        
        if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) {
            $errors[] = 'Senha deve conter pelo menos um caractere especial';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    public function checkSuspiciousActivity($userId) {
        // Verificar múltiplos logins de IPs diferentes
        $sql = "SELECT COUNT(DISTINCT ip) as ip_count 
                FROM security_logs 
                WHERE user_id = ? AND event_type = 'login_success' 
                AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        
        if ($result['ip_count'] > 3) {
            $this->logSecurityEvent('suspicious_activity', $_SERVER['REMOTE_ADDR'] ?? '', 
                                  "Múltiplos IPs para usuário: $userId", $userId);
            
            // Notificar administradores
            $this->notifyAdmins('Atividade Suspeita', 
                              "Usuário ID $userId fez login de múltiplos IPs na última hora.");
            
            return true;
        }
        
        return false;
    }
    
    private function notifyAdmins($subject, $message) {
        try {
            $sql = "SELECT email FROM colaboradores c 
                    JOIN cargos car ON c.cargo_id = car.id 
                    WHERE car.nivel_acesso = 'admin' AND c.ativo = 1 AND c.email IS NOT NULL";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $admins = $stmt->fetchAll();
            
            $emailService = new EmailService();
            
            foreach ($admins as $admin) {
                $emailService->send($admin['email'], $subject, $message);
            }
            
        } catch (Exception $e) {
            error_log("Erro ao notificar administradores: " . $e->getMessage());
        }
    }
    
    public function encryptSensitiveData($data) {
        $key = CRIPTOGRAFIA_KEY ?? 'default_key_change_in_production';
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    public function decryptSensitiveData($encryptedData) {
        $key = CRIPTOGRAFIA_KEY ?? 'default_key_change_in_production';
        $data = base64_decode($encryptedData);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
    
    public function generateSecureToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
    }
    
    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
}
?>