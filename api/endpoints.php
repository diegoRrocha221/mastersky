<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Incluir classes necessárias
require_once '../config/database.php';
require_once '../classes/Auth.php';
require_once '../classes/Colaborador.php';
require_once '../classes/Produto.php';
require_once '../classes/Venda.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';
$input = json_decode(file_get_contents('php://input'), true);

try {
    switch ($path) {
        case 'login':
            if ($method === 'POST') {
                $auth = new Auth();
                $result = $auth->login($input['usuario'], $input['senha']);
                echo json_encode($result);
            }
            break;
            
        case 'logout':
            if ($method === 'POST') {
                $auth = new Auth();
                $result = $auth->logout();
                echo json_encode($result);
            }
            break;
            
        case 'colaboradores':
            $colaborador = new Colaborador();
            
            switch ($method) {
                case 'GET':
                    $filtros = $_GET;
                    $result = $colaborador->listar($filtros);
                    echo json_encode($result);
                    break;
                    
                case 'POST':
                    $result = $colaborador->criar($input);
                    echo json_encode($result);
                    break;
                    
                case 'PUT':
                    $id = $_GET['id'] ?? null;
                    if ($id) {
                        $result = $colaborador->atualizar($id, $input);
                        echo json_encode($result);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'ID necessário']);
                    }
                    break;
                    
                case 'DELETE':
                    $id = $_GET['id'] ?? null;
                    if ($id) {
                        $result = $colaborador->excluir($id);
                        echo json_encode($result);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'ID necessário']);
                    }
                    break;
            }
            break;
            
        case 'produtos':
            $produto = new Produto();
            
            switch ($method) {
                case 'GET':
                    $filtros = $_GET;
                    $result = $produto->listar($filtros);
                    echo json_encode($result);
                    break;
                    
                case 'POST':
                    $result = $produto->criar($input);
                    echo json_encode($result);
                    break;
            }
            break;
            
        case 'vendas':
            $venda = new Venda();
            
            switch ($method) {
                case 'GET':
                    $filtros = $_GET;
                    $result = $venda->listar($filtros);
                    echo json_encode($result);
                    break;
                    
                case 'POST':
                    $result = $venda->criar($input);
                    echo json_encode($result);
                    break;
            }
            break;
            
        case 'dashboard':
            if ($method === 'GET') {
                // Estatísticas do dashboard
                $database = new Database();
                $db = $database->connect();
                
                // Vendas no mês
                $sql = "SELECT COUNT(*) as total FROM vendas WHERE MONTH(data_venda) = MONTH(CURRENT_DATE) AND YEAR(data_venda) = YEAR(CURRENT_DATE)";
                $stmt = $db->prepare($sql);
                $stmt->execute();
                $vendasMes = $stmt->fetch()['total'];
                
                // Faturamento
                $sql = "SELECT COALESCE(SUM(valor_total), 0) as total FROM vendas WHERE MONTH(data_venda) = MONTH(CURRENT_DATE) AND YEAR(data_venda) = YEAR(CURRENT_DATE)";
                $stmt = $db->prepare($sql);
                $stmt->execute();
                $faturamento = $stmt->fetch()['total'];
                
                // Colaboradores ativos
                $sql = "SELECT COUNT(*) as total FROM colaboradores WHERE ativo = 1";
                $stmt = $db->prepare($sql);
                $stmt->execute();
                $colaboradores = $stmt->fetch()['total'];
                
                // Produtos com estoque baixo
                $sql = "SELECT COUNT(*) as total FROM produtos WHERE estoque_atual <= estoque_minimo AND ativo = 1";
                $stmt = $db->prepare($sql);
                $stmt->execute();
                $estoqueBaixo = $stmt->fetch()['total'];
                
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'vendas_mes' => $vendasMes,
                        'faturamento' => $faturamento,
                        'colaboradores' => $colaboradores,
                        'estoque_baixo' => $estoqueBaixo
                    ]
                ]);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Endpoint não encontrado']);
            break;
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
}

?>

