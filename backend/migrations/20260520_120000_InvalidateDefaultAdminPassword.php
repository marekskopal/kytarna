<?php

declare(strict_types=1);

namespace Migrations;

use MarekSkopal\ORM\Migrations\Migration\Migration;
use PDO;

final class InvalidateDefaultAdminPasswordMigration extends Migration
{
	public function up(): void
	{
		$pdo = $this->databaseProvider->getDatabase()->getPdo();

		$stmt = $pdo->prepare('SELECT id, password FROM users WHERE email = :email LIMIT 1');
		$stmt->execute(['email' => 'admin@ukolio.com']);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		if (!is_array($row) || !is_string($row['password'] ?? null)) {
			return;
		}

		if (!password_verify('admin', $row['password'])) {
			return;
		}

		$randomized = '!disabled:' . bin2hex(random_bytes(32));

		$update = $pdo->prepare('UPDATE users SET password = :password, updated_at = NOW() WHERE id = :id');
		$update->execute([
			'password' => $randomized,
			'id' => (int) $row['id'],
		]);
	}

	public function down(): void
	{
		// Irreversible: the old password hash is not stored anywhere.
	}
}
