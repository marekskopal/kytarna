<?php

declare(strict_types=1);

namespace Kytario\Service\Realtime;

use Kytario\Model\Entity\User;

interface MercureCookieIssuerInterface
{
	/**
	 * Build the `Set-Cookie` header value for `mercureAuthorization` so the
	 * browser can subscribe to every workspace the user is a member of.
	 */
	public function issue(User $user, bool $secure): string;

	/**
	 * Build the `Set-Cookie` header value that clears `mercureAuthorization`
	 * — used on logout-equivalent flows so the EventSource can no longer
	 * reattach using a stale cookie.
	 */
	public function clear(bool $secure): string;
}
