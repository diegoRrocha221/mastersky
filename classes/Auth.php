<?php
class Auth {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
    }
    
    public function login($usuario, $senha) {
        try {
            $sql = "SELECT c.*, car.nivel_acesso, car.nome as cargo_nome 
                    FROM colaboradores c 
                    JOIN cargos car ON c.cargo_id = car.id 
                    WHERE c.usuario = ? AND c.ativo = 1 AND c.bloqueado = 0";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$usuario]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($senha, $user['senha'])) {
                // Atualizar último acesso
                $this->updateLastAccess($user['id']);
                
                // Criar sessão
                session_start();
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['nome'] . ' ' . $user['sobrenome'];
                $_SESSION['user_level'] = $user['nivel_acesso'];
                $_SESSION['user_cargo'] = $user['cargo_nome'];
                
                return [
                    'success' => true,
                    'user' => [
                        'id' => $user['id'],
                        'nome' => $user['nome'] . ' ' . $user['sobrenome'],
                        'nivel_acesso' => $user['nivel_acesso'],
                        'cargo' => $user['cargo_nome']
                    ]
                ];
            } else {
                // Incrementar tentativas de login
                $this->incrementLoginAttempts($usuario);
                return ['success' => false, 'message' => 'Usuário ou senha inválidos'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro interno do servidor'];
        }
    }
    
    public function logout() {
        session_start();
        session_destroy();
        return ['success' => true];
    }
    
    public function isLoggedIn() {
        session_start();
        return isset($_SESSION['user_id']);
    }
    
    public function hasPermission($level) {
        session_start();
        if (!isset($_SESSION['user_level'])) return false;
        
        $levels = ['funcionario', 'vendedor', 'gerente', 'admin'];
        $userLevel = array_search($_SESSION['user_level'], $levels);
        $requiredLevel = array_search($level, $levels);
        
        return $userLevel >= $requiredLevel;
    }
    
    private function updateLastAccess($userId) {
        $sql = "UPDATE colaboradores SET ultimo_acesso = NOW(), tentativas_login = 0 WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
    }
    
    private function incrementLoginAttempts($usuario) {
        $sql = "UPDATE colaboradores SET tentativas_login = tentativas_login + 1 WHERE usuario = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$usuario]);
        
        // Bloquear usuário após 5 tentativas
        $sql = "UPDATE colaboradores SET bloqueado = 1 WHERE usuario = ? AND tentativas_login >= 5";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$usuario]);
    }
}

?>
