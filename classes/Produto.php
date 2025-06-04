<?php
class Produto {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
    }
    
    public function listar($filtros = []) {
        try {
            $sql = "SELECT p.*, c.nome as categoria_nome 
                    FROM produtos p 
                    LEFT JOIN categorias_produtos c ON p.categoria_id = c.id 
                    WHERE 1=1";
            $params = [];
            
            if (!empty($filtros['ativo'])) {
                $sql .= " AND p.ativo = ?";
                $params[] = $filtros['ativo'];
            }
            
            if (!empty($filtros['categoria_id'])) {
                $sql .= " AND p.categoria_id = ?";
                $params[] = $filtros['categoria_id'];
            }
            
            if (!empty($filtros['estoque_baixo'])) {
                $sql .= " AND p.estoque_atual <= p.estoque_minimo";
            }
            
            $sql .= " ORDER BY p.nome";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return [
                'success' => true,
                'data' => $stmt->fetchAll()
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function criar($dados) {
        try {
            // Validações
            $validacao = $this->validarDados($dados);
            if (!$validacao['success']) {
                return $validacao;
            }
            
            // Gerar código se não fornecido
            if (empty($dados['codigo'])) {
                $dados['codigo'] = $this->gerarCodigo();
            }
            
            $sql = "INSERT INTO produtos (
                        codigo, nome, descricao, categoria_id, preco_custo, preco_venda,
                        comissao_percentual, estoque_atual, estoque_minimo, unidade_medida
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                $dados['codigo'],
                $dados['nome'],
                $dados['descricao'] ?? null,
                $dados['categoria_id'] ?? null,
                $dados['preco_custo'] ?? 0,
                $dados['preco_venda'],
                $dados['comissao_percentual'],
                $dados['estoque_atual'] ?? 0,
                $dados['estoque_minimo'] ?? 0,
                $dados['unidade_medida'] ?? 'UN'
            ]);
            
            if ($result) {
                $produtoId = $this->db->lastInsertId();
                
                // Registrar movimentação de estoque se houver estoque inicial
                if (!empty($dados['estoque_atual']) && $dados['estoque_atual'] > 0) {
                    $this->registrarMovimentacaoEstoque(
                        $produtoId,
                        'entrada',
                        $dados['estoque_atual'],
                        0,
                        $dados['estoque_atual'],
                        null,
                        $_SESSION['user_id'] ?? 1,
                        'Estoque inicial'
                    );
                }
                
                return [
                    'success' => true,
                    'message' => 'Produto criado com sucesso',
                    'id' => $produtoId
                ];
            } else {
                return ['success' => false, 'message' => 'Erro ao criar produto'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function atualizarEstoque($produtoId, $quantidade, $tipo, $motivo, $vendaId = null) {
        try {
            $this->db->beginTransaction();
            
            // Buscar estoque atual
            $sql = "SELECT estoque_atual FROM produtos WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$produtoId]);
            $produto = $stmt->fetch();
            
            if (!$produto) {
                throw new Exception('Produto não encontrado');
            }
            
            $estoqueAnterior = $produto['estoque_atual'];
            $novoEstoque = $tipo === 'entrada' 
                ? $estoqueAnterior + $quantidade 
                : $estoqueAnterior - $quantidade;
            
            if ($novoEstoque < 0) {
                throw new Exception('Estoque insuficiente');
            }
            
            // Atualizar estoque
            $sql = "UPDATE produtos SET estoque_atual = ? WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$novoEstoque, $produtoId]);
            
            // Registrar movimentação
            $this->registrarMovimentacaoEstoque(
                $produtoId, $tipo, $quantidade, $estoqueAnterior, 
                $novoEstoque, $vendaId, $_SESSION['user_id'] ?? 1, $motivo
            );
            
            $this->db->commit();
            return ['success' => true, 'novo_estoque' => $novoEstoque];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    private function registrarMovimentacaoEstoque($produtoId, $tipo, $quantidade, $estoqueAnterior, $estoqueAtual, $vendaId, $colaboradorId, $motivo) {
        $sql = "INSERT INTO movimentacao_estoque 
                (produto_id, tipo_movimentacao, quantidade, estoque_anterior, estoque_atual, venda_id, colaborador_id, motivo)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $produtoId, $tipo, $quantidade, $estoqueAnterior, 
            $estoqueAtual, $vendaId, $colaboradorId, $motivo
        ]);
    }
    
    private function gerarCodigo() {
        $sql = "SELECT MAX(CAST(SUBSTRING(codigo, 2) AS UNSIGNED)) as ultimo FROM produtos WHERE codigo REGEXP '^P[0-9]+$'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch();
        
        $proximo = ($result['ultimo'] ?? 0) + 1;
        return 'P' . str_pad($proximo, 3, '0', STR_PAD_LEFT);
    }
    
    private function validarDados($dados) {
        $erros = [];
        
        if (empty($dados['nome'])) $erros[] = 'Nome é obrigatório';
        if (empty($dados['preco_venda']) || $dados['preco_venda'] <= 0) $erros[] = 'Preço de venda deve ser maior que zero';
        if (!isset($dados['comissao_percentual']) || $dados['comissao_percentual'] < 0) $erros[] = 'Percentual de comissão inválido';
        
        if (!empty($erros)) {
            return ['success' => false, 'message' => implode(', ', $erros)];
        }
        
        return ['success' => true];
    }
}

?>
