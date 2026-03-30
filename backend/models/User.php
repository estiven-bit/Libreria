<?php

require_once __DIR__ . '/BaseModel.php';

class User extends BaseModel
{
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    // Busca al usuario por el token que recibió en el email
    public function findByActivationToken(string $token): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE activation_token = :token LIMIT 1');
        $stmt->execute(['token' => $token]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    // Activa la cuenta y limpia el token
    public function activateUser(int $id): bool
    {
        $stmt = $this->db->prepare('UPDATE users SET is_active = 1, activation_token = NULL WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public function create(array $data): int
    {
        // Añadimos activation_token e is_active a la consulta SQL
        $stmt = $this->db->prepare('INSERT INTO users (name, email, phone, password_hash, role, activation_token, is_active, created_at) 
                                    VALUES (:name, :email, :phone, :password_hash, :role, :activation_token, :is_active, NOW())');
        
        $stmt->execute([
            'name'             => $data['name'],
            'email'            => $data['email'],
            'phone'            => $data['phone'],
            'password_hash'    => $data['password_hash'],
            'role'             => $data['role'] ?? 'USUARIO',
            'activation_token' => $data['activation_token'] ?? null,
            'is_active'        => $data['is_active'] ?? 0
        ]);

        return (int)$this->db->lastInsertId();
    }
}