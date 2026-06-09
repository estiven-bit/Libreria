<?php

class DatabaseSessionHandler implements SessionHandlerInterface
{
    private $pdo;

    public function __construct()
    {
    }

    private function getPdo(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = Database::connect();
        }
        return $this->pdo;
    }

    public function open($savePath, $sessionName): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read($id): string
    {
        try {
            $stmt = $this->getPdo()->prepare('SELECT data FROM php_sessions WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? (string)$row['data'] : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function write($id, $data): bool
    {
        try {
            $stmt = $this->getPdo()->prepare(
                'REPLACE INTO php_sessions (id, data, timestamp) VALUES (:id, :data, :ts)'
            );
            $stmt->execute([
                ':id' => $id,
                ':data' => $data,
                ':ts' => time(),
            ]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function destroy($id): bool
    {
        try {
            $stmt = $this->getPdo()->prepare('DELETE FROM php_sessions WHERE id = :id');
            $stmt->execute([':id' => $id]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function gc($maxlifetime): int|false
    {
        try {
            $stmt = $this->getPdo()->prepare('DELETE FROM php_sessions WHERE timestamp < :time');
            $stmt->execute([':time' => time() - $maxlifetime]);
            return $stmt->rowCount();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
