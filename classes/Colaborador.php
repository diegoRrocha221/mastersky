<?php

class Colaborador {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
    }
    
    public function listar($filtros = []) {
        try {
            $sql = "SELECT c.*, car.nome as cargo_nome, car.nivel_acesso 
                    FROM colaboradores c 
                    JOIN cargos car ON c.cargo_id = car.id 
                    WHERE 1=1";
            $params = [];
            
            if (!empty($filtros['ativo'])) {
                $sql .= " AND c.ativo = ?";
                $params[] = $filtros['ativo'];
            }
            
            if (!empty($filtros['cargo_id'])) {
                $sql .= " AND c.cargo_id = ?";
                $params[] = $filtros['cargo_id'];
            }
            
            $sql .= " ORDER BY c.nome, c.sobrenome";
            
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
            $sql = "SELECT c.*, car.nome as cargo_nome 
                    FROM colaboradores c 
                    JOIN cargos car ON c.cargo_id = car.id 
                    WHERE c.id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $colaborador = $stmt->fetch();
            
            if ($colaborador) {
                return ['success' => true, 'data' => $colaborador];
            } else {
                return ['success' => false, 'message' => 'Colaborador não encontrado'];
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
            
            // Verificar se CPF já existe
            if ($this->cpfExiste($dados['cpf'])) {
                return ['success' => false, 'message' => 'CPF já cadastrado'];
            }
            
            // Verificar se usuário já existe
            if ($this->usuarioExiste($dados['usuario'])) {
                return ['success' => false, 'message' => 'Usuário já cadastrado'];
            }
            
            $sql = "INSERT INTO colaboradores (
                        nome, sobrenome, data_nascimento, cpf, rg, telefone, celular, email,
                        cep, endereco, numero, complemento, bairro, cidade, estado,
                        cargo_id, data_admissao, salario, comissao_personalizada,
                        usuario, senha
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?
                    )";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                $dados['nome'],
                $dados['sobrenome'],
                $dados['data_nascimento'],
                $dados['cpf'],
                $dados['rg'] ?? null,
                $dados['telefone'] ?? null,
                $dados['celular'] ?? null,
                $dados['email'] ?? null,
                $dados['cep'] ?? null,
                $dados['endereco'] ?? null,
                $dados['numero'] ?? null,
                $dados['complemento'] ?? null,
                $dados['bairro'] ?? null,
                $dados['cidade'] ?? null,
                $dados['estado'] ?? null,
                $dados['cargo_id'],
                $dados['data_admissao'],
                $dados['salario'] ?? 0,
                $dados['comissao_personalizada'] ?? null,
                $dados['usuario'],
                password_hash($dados['senha'], PASSWORD_DEFAULT)
            ]);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Colaborador criado com sucesso',
                    'id' => $this->db->lastInsertId()
                ];
            } else {
                return ['success' => false, 'message' => 'Erro ao criar colaborador'];
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
            
            $sql = "UPDATE colaboradores SET 
                        nome = ?, sobrenome = ?, data_nascimento = ?, cpf = ?, rg = ?,
                        telefone = ?, celular = ?, email = ?, cep = ?, endereco = ?,
                        numero = ?, complemento = ?, bairro = ?, cidade = ?, estado = ?,
                        cargo_id = ?, salario = ?, comissao_personalizada = ?,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?";
            
            $params = [
                $dados['nome'], $dados['sobrenome'], $dados['data_nascimento'],
                $dados['cpf'], $dados['rg'] ?? null, $dados['telefone'] ?? null,
                $dados['celular'] ?? null, $dados['email'] ?? null, $dados['cep'] ?? null,
                $dados['endereco'] ?? null, $dados['numero'] ?? null, $dados['complemento'] ?? null,
                $dados['bairro'] ?? null, $dados['cidade'] ?? null, $dados['estado'] ?? null,
                $dados['cargo_id'], $dados['salario'] ?? 0, $dados['comissao_personalizada'] ?? null,
                $id
            ];
            
            // Atualizar senha se fornecida
            if (!empty($dados['senha'])) {
                $sql = str_replace('updated_at = CURRENT_TIMESTAMP', 'senha = ?, updated_at = CURRENT_TIMESTAMP', $sql);
                array_splice($params, -1, 0, [password_hash($dados['senha'], PASSWORD_DEFAULT)]);
            }
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute($params);
            
            if ($result) {
                return ['success' => true, 'message' => 'Colaborador atualizado com sucesso'];
            } else {
                return ['success' => false, 'message' => 'Erro ao atualizar colaborador'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function excluir($id) {
        try {
            // Verificar se colaborador tem vendas
            $sql = "SELECT COUNT(*) as total FROM vendas WHERE vendedor_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $result = $stmt->fetch();
            
            if ($result['total'] > 0) {
                // Não excluir, apenas inativar
                $sql = "UPDATE colaboradores SET ativo = 0, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $result = $stmt->execute([$id]);
                
                return [
                    'success' => true,
                    'message' => 'Colaborador inativado (possui vendas cadastradas)'
                ];
            } else {
                // Excluir definitivamente
                $sql = "DELETE FROM colaboradores WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $result = $stmt->execute([$id]);
                
                return ['success' => true, 'message' => 'Colaborador excluído com sucesso'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    private function validarDados($dados, $id = null) {
        $erros = [];
        
        if (empty($dados['nome'])) $erros[] = 'Nome é obrigatório';
        if (empty($dados['sobrenome'])) $erros[] = 'Sobrenome é obrigatório';
        if (empty($dados['cpf'])) $erros[] = 'CPF é obrigatório';
        if (empty($dados['data_nascimento'])) $erros[] = 'Data de nascimento é obrigatória';
        if (empty($dados['cargo_id'])) $erros[] = 'Cargo é obrigatório';
        if (empty($dados['usuario'])) $erros[] = 'Usuário é obrigatório';
        if (empty($dados['senha']) && $id === null) $erros[] = 'Senha é obrigatória';
        
        // Validar CPF
        if (!empty($dados['cpf']) && !$this->validarCPF($dados['cpf'])) {
            $erros[] = 'CPF inválido';
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
        $sql = "SELECT id FROM colaboradores WHERE cpf = ?";
        $params = [$cpf];
        
        if ($id) {
            $sql .= " AND id != ?";
            $params[] = $id;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() !== false;
    }
    
    private function usuarioExiste($usuario, $id = null) {
        $sql = "SELECT id FROM colaboradores WHERE usuario = ?";
        $params = [$usuario];
        
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
}

?>
