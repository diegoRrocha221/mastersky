<?php

class Venda {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
    }
    
    public function criar($dados) {
        try {
            $this->db->beginTransaction();
            
            // Validações
            $validacao = $this->validarDados($dados);
            if (!$validacao['success']) {
                return $validacao;
            }
            
            // Criar venda
            $sql = "INSERT INTO vendas (
                        cliente_id, vendedor_id, protocolo_instalacao, data_venda,
                        data_instalacao, subtotal, desconto, acrescimo, valor_total,
                        forma_pagamento, parcelas, observacoes, observacoes_internas
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                $dados['cliente_id'],
                $dados['vendedor_id'],
                $dados['protocolo_instalacao'] ?? null,
                $dados['data_venda'] ?? date('Y-m-d H:i:s'),
                $dados['data_instalacao'] ?? null,
                $dados['subtotal'],
                $dados['desconto'] ?? 0,
                $dados['acrescimo'] ?? 0,
                $dados['valor_total'],
                $dados['forma_pagamento'] ?? 'dinheiro',
                $dados['parcelas'] ?? 1,
                $dados['observacoes'] ?? null,
                $dados['observacoes_internas'] ?? null
            ]);
            
            $vendaId = $this->db->lastInsertId();
            
            // Adicionar itens da venda
            foreach ($dados['itens'] as $item) {
                $this->adicionarItemVenda($vendaId, $item);
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Venda criada com sucesso',
                'id' => $vendaId,
                'numero_venda' => $this->obterNumeroVenda($vendaId)
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    private function adicionarItemVenda($vendaId, $item) {
        // Buscar dados do produto
        $sql = "SELECT preco_venda, comissao_percentual FROM produtos WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$item['produto_id']]);
        $produto = $stmt->fetch();
        
        $precoUnitario = $item['preco_unitario'] ?? $produto['preco_venda'];
        $quantidade = $item['quantidade'];
        $descontoItem = $item['desconto_item'] ?? 0;
        $subtotal = ($precoUnitario * $quantidade) - $descontoItem;
        $comissaoPercentual = $item['comissao_percentual'] ?? $produto['comissao_percentual'];
        $comissaoValor = $subtotal * ($comissaoPercentual / 100);
        
        // Inserir item
        $sql = "INSERT INTO itens_venda (
                    venda_id, produto_id, quantidade, preco_unitario, 
                    desconto_item, subtotal, comissao_percentual, comissao_valor
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $vendaId, $item['produto_id'], $quantidade, $precoUnitario,
            $descontoItem, $subtotal, $comissaoPercentual, $comissaoValor
        ]);
    }
    
    public function listar($filtros = []) {
        try {
            $sql = "SELECT v.*, 
                           CASE 
                               WHEN c.tipo_pessoa = 'fisica' THEN CONCAT(c.nome, ' ', c.sobrenome)
                               ELSE c.razao_social 
                           END as cliente_nome,
                           CONCAT(col.nome, ' ', col.sobrenome) as vendedor_nome
                    FROM vendas v
                    JOIN clientes c ON v.cliente_id = c.id
                    JOIN colaboradores col ON v.vendedor_id = col.id
                    WHERE 1=1";
            $params = [];
            
            if (!empty($filtros['data_inicio'])) {
                $sql .= " AND DATE(v.data_venda) >= ?";
                $params[] = $filtros['data_inicio'];
            }
            
            if (!empty($filtros['data_fim'])) {
                $sql .= " AND DATE(v.data_venda) <= ?";
                $params[] = $filtros['data_fim'];
            }
            
            if (!empty($filtros['vendedor_id'])) {
                $sql .= " AND v.vendedor_id = ?";
                $params[] = $filtros['vendedor_id'];
            }
            
            if (!empty($filtros['status_venda'])) {
                $sql .= " AND v.status_venda = ?";
                $params[] = $filtros['status_venda'];
            }
            
            $sql .= " ORDER BY v.data_venda DESC";
            
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
    
    private function obterNumeroVenda($vendaId) {
        $sql = "SELECT numero_venda FROM vendas WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$vendaId]);
        $result = $stmt->fetch();
        return $result['numero_venda'] ?? null;
    }
    
    private function validarDados($dados) {
        $erros = [];
        
        if (empty($dados['cliente_id'])) $erros[] = 'Cliente é obrigatório';
        if (empty($dados['vendedor_id'])) $erros[] = 'Vendedor é obrigatório';
        if (empty($dados['itens']) || !is_array($dados['itens'])) $erros[] = 'Pelo menos um item é obrigatório';
        if (empty($dados['valor_total']) || $dados['valor_total'] <= 0) $erros[] = 'Valor total inválido';
        
        if (!empty($erros)) {
            return ['success' => false, 'message' => implode(', ', $erros)];
        }
        
        return ['success' => true];
    }
}
?>
