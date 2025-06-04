<?php

class Database {
    private $host = 'localhost';
    private $dbname = 'diego780_masterskysjc';
    private $username = 'diego780_mastersky';
    private $password = 'Security.4uall!';
    private $charset = 'utf8mb4';
    private $pdo;
    
    public function connect() {
        if ($this->pdo === null) {
            try {
                $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset={$this->charset}";
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ];
                
                $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
            } catch (PDOException $e) {
                throw new Exception("Erro na conex���o: " . $e->getMessage());
            }
        }
        
        return $this->pdo;
    }
}
?>