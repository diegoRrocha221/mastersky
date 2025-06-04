<?php
class Cargo {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
    }
    
    public function listar($filtros = []) {
        try {
            $sql = "SELECT * FROM cargos WHERE 1=1";
            $params = [];
            
            if (!empty($filtros['ativo'])) {
                $sql .= " AND ativo = ?";
                $params[] = $filtros['ativo'];
            }
            
            $sql .= " ORDER BY nome";
            
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
            
            $sql = "INSERT INTO cargos (nome, descricao, nivel_acesso, salario_base, comissao_padrao) 
                    VALUES (?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                $dados['nome'],
                $dados['descricao'] ?? null,
                $dados['nivel_acesso'],
                $dados['salario_base'] ?? 0,
                $dados['comissao_padrao'] ?? 0
            ]);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Cargo criado com sucesso',
                    'id' => $this->db->lastInsertId()
                ];
            } else {
                return ['success' => false, 'message' => 'Erro ao criar cargo'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function atualizar($id, $dados) {
        try {
            // Validações
            $validacao = $this->validarDados($dados);
            if (!$validacao['success']) {
                return $validacao;
            }
            
            $sql = "UPDATE cargos SET 
                        nome = ?, descricao = ?, nivel_acesso = ?, 
                        salario_base = ?, comissao_padrao = ?,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                $dados['nome'],
                $dados['descricao'] ?? null,
                $dados['nivel_acesso'],
                $dados['salario_base'] ?? 0,
                $dados['comissao_padrao'] ?? 0,
                $id
            ]);
            
            if ($result) {
                return ['success' => true, 'message' => 'Cargo atualizado com sucesso'];
            } else {
                return ['success' => false, 'message' => 'Erro ao atualizar cargo'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function excluir($id) {
        try {
            // Verificar se cargo tem colaboradores
            $sql = "SELECT COUNT(*) as total FROM colaboradores WHERE cargo_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $result = $stmt->fetch();
            
            if ($result['total'] > 0) {
                return [
                    'success' => false,
                    'message' => 'Não é possível excluir cargo com colaboradores vinculados'
                ];
            } else {
                $sql = "DELETE FROM cargos WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $result = $stmt->execute([$id]);
                
                return ['success' => true, 'message' => 'Cargo excluído com sucesso'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    private function validarDados($dados) {
        $erros = [];
        
        if (empty($dados['nome'])) $erros[] = 'Nome é obrigatório';
        if (empty($dados['nivel_acesso'])) $erros[] = 'Nível de acesso é obrigatório';
        
        $niveisValidos = ['admin', 'gerente', 'vendedor', 'funcionario'];
        if (!in_array($dados['nivel_acesso'], $niveisValidos)) {
            $erros[] = 'Nível de acesso inválido';
        }
        
        if (isset($dados['comissao_padrao']) && ($dados['comissao_padrao'] < 0 || $dados['comissao_padrao'] > 100)) {
            $erros[] = 'Comissão deve estar entre 0 e 100%';
        }
        
        if (!empty($erros)) {
            return ['success' => false, 'message' => implode(', ', $erros)];
        }
        
        return ['success' => true];
    }
}

?>
