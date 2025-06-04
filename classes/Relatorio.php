<?php
class Relatorio {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
    }
    
    public function vendasPorPeriodo($dataInicio, $dataFim, $vendedorId = null) {
        try {
            $sql = "SELECT 
                        v.id, v.numero_venda, v.data_venda, v.valor_total, v.status_venda,
                        CASE 
                            WHEN c.tipo_pessoa = 'fisica' THEN CONCAT(c.nome, ' ', c.sobrenome)
                            ELSE c.razao_social 
                        END as cliente_nome,
                        CONCAT(col.nome, ' ', col.sobrenome) as vendedor_nome
                    FROM vendas v
                    JOIN clientes c ON v.cliente_id = c.id
                    JOIN colaboradores col ON v.vendedor_id = col.id
                    WHERE DATE(v.data_venda) BETWEEN ? AND ?";
            
            $params = [$dataInicio, $dataFim];
            
            if ($vendedorId) {
                $sql .= " AND v.vendedor_id = ?";
                $params[] = $vendedorId;
            }
            
            $sql .= " ORDER BY v.data_venda DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $vendas = $stmt->fetchAll();
            
            // Calcular totais
            $totalVendas = count($vendas);
            $valorTotal = array_sum(array_column($vendas, 'valor_total'));
            
            return [
                'success' => true,
                'data' => [
                    'vendas' => $vendas,
                    'resumo' => [
                        'total_vendas' => $totalVendas,
                        'valor_total' => $valorTotal,
                        'ticket_medio' => $totalVendas > 0 ? $valorTotal / $totalVendas : 0
                    ]
                ]
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function comissoesPendentes($vendedorId = null) {
        try {
            $sql = "SELECT 
                        com.id, com.valor_venda, com.percentual_comissao, com.valor_comissao,
                        v.numero_venda, v.data_venda,
                        CONCAT(col.nome, ' ', col.sobrenome) as vendedor_nome
                    FROM comissoes com
                    JOIN vendas v ON com.venda_id = v.id
                    JOIN colaboradores col ON com.colaborador_id = col.id
                    WHERE com.status_pagamento = 'pendente'";
            
            $params = [];
            
            if ($vendedorId) {
                $sql .= " AND com.colaborador_id = ?";
                $params[] = $vendedorId;
            }
            
            $sql .= " ORDER BY v.data_venda";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $comissoes = $stmt->fetchAll();
            
            $totalComissoes = array_sum(array_column($comissoes, 'valor_comissao'));
            
            return [
                'success' => true,
                'data' => [
                    'comissoes' => $comissoes,
                    'total_pendente' => $totalComissoes
                ]
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function estoqueBaixo() {
        try {
            $sql = "SELECT 
                        p.id, p.codigo, p.nome, p.estoque_atual, p.estoque_minimo,
                        c.nome as categoria_nome
                    FROM produtos p
                    LEFT JOIN categorias_produtos c ON p.categoria_id = c.id
                    WHERE p.estoque_atual <= p.estoque_minimo AND p.ativo = 1
                    ORDER BY (p.estoque_atual - p.estoque_minimo)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $produtos = $stmt->fetchAll();
            
            return [
                'success' => true,
                'data' => $produtos
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function topVendedores($periodo = '30') {
        try {
            $sql = "SELECT 
                        CONCAT(col.nome, ' ', col.sobrenome) as vendedor_nome,
                        COUNT(v.id) as total_vendas,
                        SUM(v.valor_total) as valor_total,
                        AVG(v.valor_total) as ticket_medio
                    FROM vendas v
                    JOIN colaboradores col ON v.vendedor_id = col.id
                    WHERE v.data_venda >= DATE_SUB(CURRENT_DATE, INTERVAL ? DAY)
                    GROUP BY v.vendedor_id
                    ORDER BY valor_total DESC
                    LIMIT 10";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$periodo]);
            $vendedores = $stmt->fetchAll();
            
            return [
                'success' => true,
                'data' => $vendedores
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function dashboard($periodo = '30') {
        try {
            $dataInicio = date('Y-m-d', strtotime("-{$periodo} days"));
            $dataFim = date('Y-m-d');
            
            // Vendas no período
            $sql = "SELECT COUNT(*) as total FROM vendas WHERE DATE(data_venda) BETWEEN ? AND ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$dataInicio, $dataFim]);
            $totalVendas = $stmt->fetch()['total'];
            
            // Faturamento
            $sql = "SELECT COALESCE(SUM(valor_total), 0) as total FROM vendas WHERE DATE(data_venda) BETWEEN ? AND ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$dataInicio, $dataFim]);
            $faturamento = $stmt->fetch()['total'];
            
            // Colaboradores ativos
            $sql = "SELECT COUNT(*) as total FROM colaboradores WHERE ativo = 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $colaboradores = $stmt->fetch()['total'];
            
            // Produtos com estoque baixo
            $sql = "SELECT COUNT(*) as total FROM produtos WHERE estoque_atual <= estoque_minimo AND ativo = 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $estoqueBaixo = $stmt->fetch()['total'];
            
            // Gráfico de vendas por mês (últimos 6 meses)
            $sql = "SELECT 
                        DATE_FORMAT(data_venda, '%Y-%m') as mes,
                        COUNT(*) as total_vendas,
                        SUM(valor_total) as valor_total
                    FROM vendas 
                    WHERE data_venda >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
                    GROUP BY DATE_FORMAT(data_venda, '%Y-%m')
                    ORDER BY mes";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $vendasPorMes = $stmt->fetchAll();
            
            return [
                'success' => true,
                'data' => [
                    'cards' => [
                        'vendas' => $totalVendas,
                        'faturamento' => $faturamento,
                        'colaboradores' => $colaboradores,
                        'estoque_baixo' => $estoqueBaixo
                    ],
                    'graficos' => [
                        'vendas_por_mes' => $vendasPorMes
                    ]
                ]
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
?>