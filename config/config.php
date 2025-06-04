<?php
define('SISTEMA_NOME', 'Master SKY ERP');
define('SISTEMA_VERSAO', '1.0.0');
define('SISTEMA_URL', 'http://masterskysjc.webcoders.group');

// Configurações de Banco de Dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'diego780_masterskysjc');
define('DB_USER', 'diego780_mastersky');
define('DB_PASS', 'Security.4uall!');
define('DB_CHARSET', 'utf8mb4');

// Configurações de Sessão
define('SESSAO_TEMPO_LIMITE', 3600); // 1 hora em segundos
define('SESSAO_NOME', 'micro_erp_session');

// Configurações de Segurança
define('CRIPTOGRAFIA_KEY', 'Di@33R49245EuClie');
define('MAX_TENTATIVAS_LOGIN', 5);
define('TEMPO_BLOQUEIO_LOGIN', 900); // 15 minutos

// Configurações de Upload
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5MB
define('UPLOAD_ALLOWED_TYPES', ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx']);
define('UPLOAD_PATH', __DIR__ . '/../uploads/');

// Configurações de Email
define('EMAIL_HOST', 'smtp.gmail.com');
define('EMAIL_PORT', 587);
define('EMAIL_USERNAME', 'seu_email@gmail.com');
define('EMAIL_PASSWORD', 'sua_senha_de_app');
define('EMAIL_FROM', 'sistema@empresa.com');
define('EMAIL_FROM_NAME', 'Sistema Micro ERP');

// Configurações de Log
define('LOG_ENABLE', true);
define('LOG_LEVEL', 'INFO'); // DEBUG, INFO, WARNING, ERROR
define('LOG_PATH', __DIR__ . '/../logs/');

// Timezone
date_default_timezone_set('America/Sao_Paulo');

// Configurações de Erro
if ($_SERVER['SERVER_NAME'] === 'localhost') {
    // Ambiente de desenvolvimento
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    define('DEBUG_MODE', true);
} else {
    // Ambiente de produção
    error_reporting(0);
    ini_set('display_errors', 0);
    define('DEBUG_MODE', false);
}

// Autoload das classes
spl_autoload_register(function ($className) {
    $file = __DIR__ . '/../classes/' . $className . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});
?>
