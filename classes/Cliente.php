<?php
// ====================================
// classes/Cliente.php - CRUD Clientes
// ====================================

class Cliente {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
    }
    
    public function listar($filtros = []) {
        try {
            $sql = "SELECT * FROM clientes WHERE 1=1";
            $params = [];
            
            if (!empty($filtros['ativo'])) {
                $sql .= " AND ativo = ?";
                $params[] = $filtros['ativo'];
            }
            
            if (!empty($filtros['tipo_pessoa'])) {
                $sql .= " AND tipo_pessoa = ?";
                $params[] = $filtros['tipo_pessoa'];
            }
            
            if (!empty($filtros['busca'])) {
                $sql .= " AND (nome LIKE ? OR sobrenome LIKE ? OR razao_social LIKE ? OR cpf LIKE ? OR cnpj LIKE ?)";
                $busca = "%{$filtros['busca']}%";
                $params = array_merge($params, [$busca, $busca, $busca, $busca, $busca]);
            }
            
            $sql .= " ORDER BY COALESCE(nome, razao_social)";
            
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
    
    public function buscarPorId($id) {
        try {
            $sql = "SELECT * FROM clientes WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $cliente = $stmt->fetch();
            
            if ($cliente) {
                return ['success' => true, 'data' => $cliente];
            } else {
                return ['success' => false, 'message' => 'Cliente não encontrado'];
            }
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
            
            // Verificar duplicatas
            if ($dados['tipo_pessoa'] === 'fisica' && $this->cpfExiste($dados['cpf'])) {
                return ['success' => false, 'message' => 'CPF já cadastrado'];
            }
            
            if ($dados['tipo_pessoa'] === 'juridica' && $this->cnpjExiste($dados['cnpj'])) {
                return ['success' => false, 'message' => 'CNPJ já cadastrado'];
            }
            
            $sql = "INSERT INTO clientes (
                        tipo_pessoa, nome, sobrenome, cpf, rg, data_nascimento,
                        razao_social, nome_fantasia, cnpj, inscricao_estadual,
                        telefone, celular, email, cep, endereco, numero,
                        complemento, bairro, cidade, estado
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                $dados['tipo_pessoa'],
                $dados['nome'] ?? null,
                $dados['sobrenome'] ?? null,
                $dados['cpf'] ?? null,
                $dados['rg'] ?? null,
                $dados['data_nascimento'] ?? null,
                $dados['razao_social'] ?? null,
                $dados['nome_fantasia'] ?? null,
                $dados['cnpj'] ?? null,
                $dados['inscricao_estadual'] ?? null,
                $dados['telefone'] ?? null,
                $dados['celular'] ?? null,
                $dados['email'] ?? null,
                $dados['cep'] ?? null,
                $dados['endereco'],
                $dados['numero'] ?? null,
                $dados['complemento'] ?? null,
                $dados['bairro'] ?? null,
                $dados['cidade'],
                $dados['estado']
            ]);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Cliente criado com sucesso',
                    'id' => $this->db->lastInsertId()
                ];
            } else {
                return ['success' => false, 'message' => 'Erro ao criar cliente'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function atualizar($id, $dados) {
        try {
            // Validações
            $validacao = $this->validarDados($dados, $id);
            if (!$validacao['success']) {
                return $validacao;
            }
            
            $sql = "UPDATE clientes SET 
                        tipo_pessoa = ?, nome = ?, sobrenome = ?, cpf = ?, rg = ?, data_nascimento = ?,
                        razao_social = ?, nome_fantasia = ?, cnpj = ?, inscricao_estadual = ?,
                        telefone = ?, celular = ?, email = ?, cep = ?, endereco = ?, numero = ?,
                        complemento = ?, bairro = ?, cidade = ?, estado = ?,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                $dados['tipo_pessoa'],
                $dados['nome'] ?? null,
                $dados['sobrenome'] ?? null,
                $dados['cpf'] ?? null,
                $dados['rg'] ?? null,
                $dados['data_nascimento'] ?? null,
                $dados['razao_social'] ?? null,
                $dados['nome_fantasia'] ?? null,
                $dados['cnpj'] ?? null,
                $dados['inscricao_estadual'] ?? null,
                $dados['telefone'] ?? null,
                $dados['celular'] ?? null,
                $dados['email'] ?? null,
                $dados['cep'] ?? null,
                $dados['endereco'],
                $dados['numero'] ?? null,
                $dados['complemento'] ?? null,
                $dados['bairro'] ?? null,
                $dados['cidade'],
                $dados['estado'],
                $id
            ]);
            
            if ($result) {
                return ['success' => true, 'message' => 'Cliente atualizado com sucesso'];
            } else {
                return ['success' => false, 'message' => 'Erro ao atualizar cliente'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function excluir($id) {
        try {
            // Verificar se cliente tem vendas
            $sql = "SELECT COUNT(*) as total FROM vendas WHERE cliente_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $result = $stmt->fetch();
            
            if ($result['total'] > 0) {
                // Não excluir, apenas inativar
                $sql = "UPDATE clientes SET ativo = 0, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $result = $stmt->execute([$id]);
                
                return [
                    'success' => true,
                    'message' => 'Cliente inativado (possui vendas cadastradas)'
                ];
            } else {
                // Excluir definitivamente
                $sql = "DELETE FROM clientes WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $result = $stmt->execute([$id]);
                
                return ['success' => true, 'message' => 'Cliente excluído com sucesso'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    private function validarDados($dados, $id = null) {
        $erros = [];
        
        if (empty($dados['tipo_pessoa'])) $erros[] = 'Tipo de pessoa é obrigatório';
        if (empty($dados['endereco'])) $erros[] = 'Endereço é obrigatório';
        if (empty($dados['cidade'])) $erros[] = 'Cidade é obrigatória';
        if (empty($dados['estado'])) $erros[] = 'Estado é obrigatório';
        
        if ($dados['tipo_pessoa'] === 'fisica') {
            if (empty($dados['nome'])) $erros[] = 'Nome é obrigatório para pessoa física';
            if (empty($dados['cpf'])) $erros[] = 'CPF é obrigatório para pessoa física';
            if (!empty($dados['cpf']) && !$this->validarCPF($dados['cpf'])) {
                $erros[] = 'CPF inválido';
            }
        } else if ($dados['tipo_pessoa'] === 'juridica') {
            if (empty($dados['razao_social'])) $erros[] = 'Razão social é obrigatória para pessoa jurídica';
            if (empty($dados['cnpj'])) $erros[] = 'CNPJ é obrigatório para pessoa jurídica';
            if (!empty($dados['cnpj']) && !$this->validarCNPJ($dados['cnpj'])) {
                $erros[] = 'CNPJ inválido';
            }
        }
        
        // Validar email
        if (!empty($dados['email']) && !filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
            $erros[] = 'Email inválido';
        }
        
        if (!empty($erros)) {
            return ['success' => false, 'message' => implode(', ', $erros)];
        }
        
        return ['success' => true];
    }
    
    private function cpfExiste($cpf, $id = null) {
        $sql = "SELECT id FROM clientes WHERE cpf = ?";
        $params = [$cpf];
        
        if ($id) {
            $sql .= " AND id != ?";
            $params[] = $id;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() !== false;
    }
    
    private function cnpjExiste($cnpj, $id = null) {
        $sql = "SELECT id FROM clientes WHERE cnpj = ?";
        $params = [$cnpj];
        
        if ($id) {
            $sql .= " AND id != ?";
            $params[] = $id;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() !== false;
    }
    
    private function validarCPF($cpf) {
        // Remove caracteres não numéricos
        $cpf = preg_replace('/[^0-9]/', '', $cpf);
        
        // Verifica se tem 11 dígitos
        if (strlen($cpf) != 11) return false;
        
        // Verifica se todos os dígitos são iguais
        if (preg_match('/(\d)\1{10}/', $cpf)) return false;
        
        // Calcula os dígitos verificadores
        for ($t = 9; $t < 11; $t++) {
            for ($d = 0, $c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) return false;
        }
        
        return true;
    }
    
    private function validarCNPJ($cnpj) {
        // Remove caracteres não numéricos
        $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
        
        // Verifica se tem 14 dígitos
        if (strlen($cnpj) != 14) return false;
        
        // Verifica se todos os dígitos são iguais
        if (preg_match('/(\d)\1{13}/', $cnpj)) return false;
        
        // Calcula o primeiro dígito verificador
        $soma = 0;
        $peso = 5;
        for ($i = 0; $i < 12; $i++) {
            $soma += $cnpj[$i] * $peso;
            $peso = $peso == 2 ? 9 : $peso - 1;
        }
        $digito1 = $soma % 11 < 2 ? 0 : 11 - ($soma % 11);
        
        // Calcula o segundo dígito verificador
        $soma = 0;
        $peso = 6;
        for ($i = 0; $i < 13; $i++) {
            $soma += $cnpj[$i] * $peso;
            $peso = $peso == 2 ? 9 : $peso - 1;
        }
        $digito2 = $soma % 11 < 2 ? 0 : 11 - ($soma % 11);
        
        return $cnpj[12] == $digito1 && $cnpj[13] == $digito2;
    }
}
?>