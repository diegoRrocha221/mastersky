<?php
class Utils {
    
    public static function formatarMoeda($valor) {
        return 'R$ ' . number_format($valor, 2, ',', '.');
    }
    
    public static function formatarData($data, $formato = 'd/m/Y') {
        if ($data instanceof DateTime) {
            return $data->format($formato);
        }
        
        return date($formato, strtotime($data));
    }
    
    public static function formatarCPF($cpf) {
        $cpf = preg_replace('/[^0-9]/', '', $cpf);
        return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpf);
    }
    
    public static function formatarCNPJ($cnpj) {
        $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
        return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $cnpj);
    }
    
    public static function formatarTelefone($telefone) {
        $telefone = preg_replace('/[^0-9]/', '', $telefone);
        
        if (strlen($telefone) == 11) {
            return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $telefone);
        } else if (strlen($telefone) == 10) {
            return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $telefone);
        }
        
        return $telefone;
    }
    
    public static function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeInput'], $input);
        }
        
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }
    
    public static function gerarSenhaAleatoria($tamanho = 8) {
        $caracteres = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $senha = '';
        
        for ($i = 0; $i < $tamanho; $i++) {
            $senha .= $caracteres[rand(0, strlen($caracteres) - 1)];
        }
        
        return $senha;
    }
    
    public static function logActivity($acao, $detalhes = '', $userId = null) {
        try {
            $database = new Database();
            $db = $database->connect();
            
            if (!$userId) {
                session_start();
                $userId = $_SESSION['user_id'] ?? null;
            }
            
            $sql = "INSERT INTO logs_atividade (usuario_id, acao, detalhes, ip, user_agent) 
                    VALUES (?, ?, ?, ?, ?)";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $userId,
                $acao,
                $detalhes,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            
        } catch (Exception $e) {
            // Log de erro silencioso
            error_log("Erro ao registrar atividade: " . $e->getMessage());
        }
    }
    
    public static function validarPermissao($nivelNecessario) {
        session_start();
        
        if (!isset($_SESSION['user_level'])) {
            return false;
        }
        
        $niveis = ['funcionario' => 1, 'vendedor' => 2, 'gerente' => 3, 'admin' => 4];
        
        $nivelUsuario = $niveis[$_SESSION['user_level']] ?? 0;
        $nivelRequerido = $niveis[$nivelNecessario] ?? 0;
        
        return $nivelUsuario >= $nivelRequerido;
    }
    
    public static function enviarEmail($para, $assunto, $mensagem, $headers = []) {
        $headersDefault = [
            'From' => 'sistema@empresa.com',
            'Reply-To' => 'naoresponda@empresa.com',
            'X-Mailer' => 'PHP/' . phpversion(),
            'Content-Type' => 'text/html; charset=UTF-8'
        ];
        
        $headers = array_merge($headersDefault, $headers);
        $headerString = '';
        
        foreach ($headers as $key => $value) {
            $headerString .= "$key: $value\r\n";
        }
        
        return mail($para, $assunto, $mensagem, $headerString);
    }
}
?>

