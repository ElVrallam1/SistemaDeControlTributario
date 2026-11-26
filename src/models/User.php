<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class User
{
    public static function findByLogin(string $login): ?array
    {
        $stmt = db()->prepare('SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.username = :login OR u.email = :login LIMIT 1');
        $stmt->execute(['login' => $login]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        $stmt = db()->prepare('SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public static function findById(int $id): ?array
    {
        $stmt = db()->prepare('SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public static function findAuthById(int $id): ?array
    {
        $stmt = db()->prepare('SELECT u.id, u.full_name, u.username, u.email, u.department, u.role_id, u.is_active, u.must_change_password, u.temp_password_expires_at, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public static function existsByUsernameOrEmail(string $username, string $email): bool
    {
        $stmt = db()->prepare('SELECT id FROM users WHERE username = :u OR email = :e LIMIT 1');
        $stmt->execute(['u' => $username, 'e' => $email]);
        return (bool) $stmt->fetch();
    }

    public static function roleIdByName(string $name): int
    {
        $stmt = db()->prepare('SELECT id FROM roles WHERE name = :name LIMIT 1');
        $stmt->execute(['name' => $name]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    public static function create(array $data): int
    {
        $stmt = db()->prepare('INSERT INTO users (full_name, username, email, department, password_hash, role_id) VALUES (:n, :u, :e, :d, :p, :r) RETURNING id');
        $stmt->execute([
            'n' => $data['full_name'],
            'u' => $data['username'],
            'e' => $data['email'],
            'd' => $data['department'],
            'p' => $data['password_hash'],
            'r' => $data['role_id'],
        ]);
        return (int) $stmt->fetchColumn();
    }

    public static function updatePassword(int $id, string $passwordHash, bool $mustChange = false, ?string $tempExpiresAt = null): void
    {
        $stmt = db()->prepare('UPDATE users SET password_hash = :ph, must_change_password = :mc, temp_password_expires_at = :exp, updated_at = NOW() WHERE id = :id');
        $stmt->execute([
            'ph' => $passwordHash,
            'mc' => $mustChange ? 'true' : 'false',
            'exp' => $tempExpiresAt,
            'id' => $id,
        ]);
    }

    public static function markLoginSuccess(int $id, string $ip): void
    {
        $stmt = db()->prepare('UPDATE users SET last_login_at = NOW(), last_login_ip = :ip, failed_login_count = 0 WHERE id = :id');
        $stmt->execute(['ip' => $ip, 'id' => $id]);
    }

    public static function incrementFailedLogin(int $id): void
    {
        $stmt = db()->prepare('UPDATE users SET failed_login_count = failed_login_count + 1 WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public static function createRecoveryRequest(int $userId, string $email, string $ip, string $ua, string $tempHash, string $expiresAt): void
    {
        $stmt = db()->prepare('INSERT INTO password_recovery_requests (user_id, email, ip_address, user_agent, temp_password_hash, expires_at) VALUES (:uid, :email, :ip, :ua, :tph, :exp)');
        $stmt->execute([
            'uid' => $userId,
            'email' => $email,
            'ip' => $ip,
            'ua' => $ua,
            'tph' => $tempHash,
            'exp' => $expiresAt,
        ]);
    }

    public static function all(): array
    {
        return db()->query('SELECT u.id, u.full_name, u.username, u.email, u.department, u.is_active, u.must_change_password, u.last_login_at, u.created_at, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id ORDER BY u.id DESC')->fetchAll() ?: [];
    }

    public static function roles(): array
    {
        return db()->query('SELECT id, name, description FROM roles ORDER BY id ASC')->fetchAll() ?: [];
    }

    public static function updateAdmin(int $id, array $data): void
    {
        $stmt = db()->prepare('UPDATE users SET full_name=:n, department=:d, role_id=:r, is_active=:a, updated_at=NOW() WHERE id=:id');
        $stmt->execute([
            'n' => $data['full_name'],
            'd' => $data['department'],
            'r' => $data['role_id'],
            'a' => $data['is_active'] ? 'true' : 'false',
            'id' => $id,
        ]);
    }

    public static function setActive(int $id, bool $active): void
    {
        $stmt = db()->prepare('UPDATE users SET is_active=:a, updated_at=NOW() WHERE id=:id');
        $stmt->execute(['a' => $active ? 'true' : 'false', 'id' => $id]);
    }
}
