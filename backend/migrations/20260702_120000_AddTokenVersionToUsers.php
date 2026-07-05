<?php

declare(strict_types=1);

namespace Migrations;

use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Migrations\Migration\Migration;

/**
 * Session revocation (security H-01): `token_version` is embedded as the `tv` claim in every
 * issued JWT and re-checked on each authenticated request. Bumping it (on password change /
 * reset confirm) invalidates all outstanding access + refresh tokens, so a stolen token can be
 * killed by changing the password. Defaults to 0; tokens issued before this column existed carry
 * no `tv` claim and are treated as version 0, so they stay valid until they expire naturally.
 */
final class AddTokenVersionToUsersMigration extends Migration
{
	public function up(): void
	{
		$this->table('users')
			->addColumn('token_version', Type::Int, default: 0)
			->alter();
	}

	public function down(): void
	{
		$this->table('users')
			->dropColumn('token_version')
			->alter();
	}
}
