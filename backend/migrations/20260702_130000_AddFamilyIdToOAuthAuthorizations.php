<?php

declare(strict_types=1);

namespace Migrations;

use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Migrations\Migration\Migration;

/**
 * Refresh-token family revocation (security M-02, RFC 9700): every authorization code starts a
 * token family and each rotation inherits the `family_id`. Replaying an already-rotated refresh
 * token (or an already-consumed authorization code) revokes the whole family, so a stolen
 * refresh token stops working the moment the legitimate client rotates. Nullable because rows
 * issued before this column existed have no lineage; they revoke individually as before.
 */
final class AddFamilyIdToOAuthAuthorizationsMigration extends Migration
{
	public function up(): void
	{
		$this->table('oauth_authorizations')
			->addColumn('family_id', Type::String, size: 32, nullable: true, default: null)
			->alter();
	}

	public function down(): void
	{
		$this->table('oauth_authorizations')
			->dropColumn('family_id')
			->alter();
	}
}
