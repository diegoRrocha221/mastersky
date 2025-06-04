<?php
class EmailService {
    private $config;
    
    public function __construct() {
        $this->config = [
            'host' => EMAIL_HOST ?? 'smtp.gmail.com',
            'port' => EMAIL_PORT ?? 587,
            'username' => EMAIL_USERNAME ?? '',
            'password' => EMAIL_PASSWORD ?? '',
            'from' => EMAIL_FROM ?? 'sistema@empresa.com',
            'from_name' => EMAIL_FROM_NAME ?? 'Micro ERP'
        ];
    }
    
    public function send($to, $subject, $body, $attachments = []) {
        try {
            // Headers básicos
            $headers = [
                'MIME-Version' => '1.0',
                'Content-Type' => 'text/html; charset=UTF-8',
                'From' => $this->config['from_name'] . ' <' . $this->config['from'] . '>',
                'Reply-To' => $this->config['from'],
                'X-Mailer' => 'Micro ERP v' . (SISTEMA_VERSAO ?? '1.0.0')
            ];
            
            // Template HTML
            $htmlBody = $this->wrapTemplate($body, $subject);
            
            // Enviar email
            $headerString = '';
            foreach ($headers as $key => $value) {
                $headerString .= "$key: $value\r\n";
            }
            
            $result = mail($to, $subject, $htmlBody, $headerString);
            
            // Log do envio
            $this->logEmail($to, $subject, $result);
            
            return [
                'success' => $result,
                'message' => $result ? 'Email enviado com sucesso' : 'Erro ao enviar email'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    private function wrapTemplate($content, $title) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>{$title}</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #2c3e50, #3498db); color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f8f9fa; }
                .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
                .btn { display: inline-block; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Micro ERP</h1>
                    <p>{$title}</p>
                </div>
                <div class='content'>
                    {$content}
                </div>
                <div class='footer'>
                    <p>Este é um email automático do sistema Micro ERP.</p>
                    <p>© " . date('Y') . " - Todos os direitos reservados.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    private function logEmail($to, $subject, $success) {
        try {
            $database = new Database();
            $db = $database->connect();
            
            $sql = "INSERT INTO email_logs (destinatario, assunto, enviado, data_envio, ip) VALUES (?, ?, ?, NOW(), ?)";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $to,
                $subject,
                $success ? 1 : 0,
                $_SERVER['REMOTE_ADDR'] ?? null
            ]);
            
        } catch (Exception $e) {
            error_log("Erro ao registrar log de email: " . $e->getMessage());
        }
    }
    
    public function sendWelcomeEmail($colaborador) {
        $content = "
            <h2>Bem-vindo ao sistema!</h2>
            <p>Olá <strong>{$colaborador['nome']}</strong>,</p>
            <p>Sua conta foi criada com sucesso no sistema Micro ERP.</p>
            <p><strong>Suas credenciais de acesso:</strong></p>
            <ul>
                <li>Usuário: {$colaborador['usuario']}</li>
                <li>Senha: [Definida pelo administrador]</li>
            </ul>
            <p>Para acessar o sistema, clique no botão abaixo:</p>
            <p><a href='" . SISTEMA_URL . "' class='btn'>Acessar Sistema</a></p>
            <p>Recomendamos que você altere sua senha no primeiro acesso.</p>
        ";
        
        return $this->send(
            $colaborador['email'],
            'Bem-vindo ao Micro ERP',
            $content
        );
    }
    
    public function sendPasswordReset($colaborador, $token) {
        $content = "
            <h2>Redefinição de Senha</h2>
            <p>Olá <strong>{$colaborador['nome']}</strong>,</p>
            <p>Você solicitou a redefinição de sua senha no sistema Micro ERP.</p>
            <p>Para criar uma nova senha, clique no botão abaixo:</p>
            <p><a href='" . SISTEMA_URL . "/reset-password.php?token={$token}' class='btn'>Redefinir Senha</a></p>
            <p>Este link é válido por 24 horas.</p>
            <p>Se você não solicitou esta redefinição, ignore este email.</p>
        ";
        
        return $this->send(
            $colaborador['email'],
            'Redefinição de Senha - Micro ERP',
            $content
        );
    }
    
    public function sendComissionReport($vendedor, $comissoes) {
        $totalComissao = array_sum(array_column($comissoes, 'valor_comissao'));
        
        $content = "
            <h2>Relatório de Comissões</h2>
            <p>Olá <strong>{$vendedor['nome']}</strong>,</p>
            <p>Segue o relatório de suas comissões do período:</p>
            <table style='width: 100%; border-collapse: collapse; margin: 20px 0;'>
                <thead>
                    <tr style='background: #f8f9fa;'>
                        <th style='border: 1px solid #ddd; padding: 8px;'>Venda</th>
                        <th style='border: 1px solid #ddd; padding: 8px;'>Data</th>
                        <th style='border: 1px solid #ddd; padding: 8px;'>Valor</th>
                        <th style='border: 1px solid #ddd; padding: 8px;'>Comissão</th>
                    </tr>
                </thead>
                <tbody>";
        
        foreach ($comissoes as $comissao) {
            $content .= "
                    <tr>
                        <td style='border: 1px solid #ddd; padding: 8px;'>{$comissao['numero_venda']}</td>
                        <td style='border: 1px solid #ddd; padding: 8px;'>" . date('d/m/Y', strtotime($comissao['data_venda'])) . "</td>
                        <td style='border: 1px solid #ddd; padding: 8px;'>R$ " . number_format($comissao['valor_venda'], 2, ',', '.') . "</td>
                        <td style='border: 1px solid #ddd; padding: 8px;'>R$ " . number_format($comissao['valor_comissao'], 2, ',', '.') . "</td>
                    </tr>";
        }
        
        $content .= "
                </tbody>
                <tfoot>
                    <tr style='background: #e9ecef; font-weight: bold;'>
                        <td colspan='3' style='border: 1px solid #ddd; padding: 8px; text-align: right;'>Total:</td>
                        <td style='border: 1px solid #ddd; padding: 8px;'>R$ " . number_format($totalComissao, 2, ',', '.') . "</td>
                    </tr>
                </tfoot>
            </table>
        ";
        
        return $this->send(
            $vendedor['email'],
            'Relatório de Comissões - Micro ERP',
            $content
        );
    }
}
?>