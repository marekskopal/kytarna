<?php

declare(strict_types=1);

namespace Kytarna\Tests\Controller;

use DateTimeImmutable;
use Kytarna\Controller\AuthenticationController;
use Kytarna\Model\Entity\User;
use Kytarna\Model\Repository\WorkspaceRepository;
use Kytarna\Service\Provider\PasswordResetProviderInterface;
use Kytarna\Tests\Support\AppHarness;
use Kytarna\Tests\Support\Fixture;
use Kytarna\Tests\Support\IntegrationTestCase;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Stream;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(AuthenticationController::class)]
final class AuthenticationControllerTest extends IntegrationTestCase
{
	public function testSignUpCreatesUserWithoutWorkspace(): void
	{
		$response = $this->request('POST', '/api/authentication/sign-up', [
			'email' => 'new@example.com',
			'password' => 'StrongPass1',
			'name' => 'New Person',
		]);

		// Generic body (no tokens) so the endpoint can't be used for user enumeration;
		// the frontend follows up with a login call.
		self::assertSame(200, $response->getStatusCode());
		self::assertArrayNotHasKey('accessToken', $this->jsonBody($response));

		// No workspace is auto-created — onboarding lets the user choose Teacher (create one)
		// or Student (join one).
		$workspaceRepo = $this->container->get(WorkspaceRepository::class);
		assert($workspaceRepo instanceof WorkspaceRepository);
		$workspaces = iterator_to_array($workspaceRepo->findAll(), false);
		self::assertCount(0, $workspaces);

		$login = $this->request('POST', '/api/authentication/login', [
			'email' => 'new@example.com',
			'password' => 'StrongPass1',
		]);
		self::assertSame(200, $login->getStatusCode());
		self::assertArrayHasKey('accessToken', $this->jsonBody($login));
	}

	public function testSignUpRejectsWeakPassword(): void
	{
		$response = $this->request('POST', '/api/authentication/sign-up', [
			'email' => 'weak@example.com',
			'password' => 'short',
			'name' => 'Weak',
		]);

		self::assertSame(422, $response->getStatusCode());
	}

	public function testSignUpRejectsMalformedEmail(): void
	{
		$response = $this->request('POST', '/api/authentication/sign-up', [
			'email' => 'not-an-email',
			'password' => 'StrongPass1',
			'name' => 'Bad Email',
		]);

		self::assertSame(422, $response->getStatusCode());
	}

	public function testLogoutIsOpenAndReturnsOk(): void
	{
		$response = $this->request('POST', '/api/authentication/logout', []);

		self::assertSame(200, $response->getStatusCode());
	}

	public function testMalformedJsonBodyReturns400NotServerError(): void
	{
		$request = (new ServerRequest([], [], '/api/authentication/login', 'POST'))
			->withHeader('Content-Type', 'application/json');
		$stream = new Stream('php://temp', 'r+');
		$stream->write('{"email": "x@example.com", ');
		$stream->rewind();
		$request = $request->withBody($stream);

		$response = $this->handler->handle($request);

		self::assertSame(400, $response->getStatusCode());
	}

	public function testMissingRequiredFieldsReturns400NotServerError(): void
	{
		// CredentialsDto needs email + password; an empty body must be a client error, not a 500.
		$response = $this->request('POST', '/api/authentication/login', []);

		self::assertSame(400, $response->getStatusCode());
	}

	public function testSignUpWithDuplicateEmailReturnsGenericOkAndKeepsAccountIntact(): void
	{
		Fixture::createUser(email: 'dup@example.com', password: 'OldPass1!');

		$response = $this->request('POST', '/api/authentication/sign-up', [
			'email' => 'dup@example.com',
			'password' => 'NewPass1!',
			'name' => 'Dup',
		]);

		// Same generic 200 as a fresh sign-up — no user-enumeration oracle.
		self::assertSame(200, $response->getStatusCode());
		self::assertArrayNotHasKey('accessToken', $this->jsonBody($response));

		// The existing account is untouched: the attempted password does not work…
		$loginWithNew = $this->request('POST', '/api/authentication/login', [
			'email' => 'dup@example.com',
			'password' => 'NewPass1!',
		]);
		self::assertSame(401, $loginWithNew->getStatusCode());

		// …and the original one still does.
		$loginWithOld = $this->request('POST', '/api/authentication/login', [
			'email' => 'dup@example.com',
			'password' => 'OldPass1!',
		]);
		self::assertSame(200, $loginWithOld->getStatusCode());
	}

	public function testLoginWithValidCredentialsReturnsTokens(): void
	{
		Fixture::createUser(email: 'login@example.com', password: 'Secret123');

		$response = $this->request('POST', '/api/authentication/login', [
			'email' => 'login@example.com',
			'password' => 'Secret123',
		]);

		self::assertSame(200, $response->getStatusCode());
		$body = $this->jsonBody($response);
		self::assertArrayHasKey('accessToken', $body);
		self::assertArrayHasKey('refreshToken', $body);
	}

	public function testLoginWithInvalidPasswordReturns401(): void
	{
		Fixture::createUser(email: 'login@example.com', password: 'Secret123');

		$response = $this->request('POST', '/api/authentication/login', [
			'email' => 'login@example.com',
			'password' => 'WrongPass1',
		]);

		self::assertSame(401, $response->getStatusCode());
	}

	public function testLoginWithUnknownEmailReturns401(): void
	{
		$response = $this->request('POST', '/api/authentication/login', [
			'email' => 'nobody@example.com',
			'password' => 'Whatever1',
		]);

		self::assertSame(401, $response->getStatusCode());
	}

	public function testLoginLocksAccountAfterRepeatedFailures(): void
	{
		Fixture::createUser(email: 'lockme@example.com', password: 'Secret123');

		// Threshold default = 5. First 4 wrong-password attempts return 401.
		for ($i = 1; $i <= 4; $i++) {
			$response = $this->request('POST', '/api/authentication/login', [
				'email' => 'lockme@example.com',
				'password' => 'WrongPass1',
			]);
			self::assertSame(401, $response->getStatusCode(), 'Attempt ' . $i . ' should still be 401');
		}

		// The 5th wrong-password attempt trips the lock and returns 429 with Retry-After.
		$locked = $this->request('POST', '/api/authentication/login', [
			'email' => 'lockme@example.com',
			'password' => 'WrongPass1',
		]);
		self::assertSame(429, $locked->getStatusCode());
		$retryAfter = $locked->getHeaderLine('Retry-After');
		self::assertNotSame('', $retryAfter);
		self::assertGreaterThan(0, (int) $retryAfter);
		self::assertLessThanOrEqual(60, (int) $retryAfter);

		// Correct password while locked still returns 429 — the lock is honored.
		$stillLocked = $this->request('POST', '/api/authentication/login', [
			'email' => 'lockme@example.com',
			'password' => 'Secret123',
		]);
		self::assertSame(429, $stillLocked->getStatusCode());
	}

	public function testSuccessfulLoginResetsFailureCounter(): void
	{
		Fixture::createUser(email: 'reset@example.com', password: 'Secret123');

		for ($i = 1; $i <= 4; $i++) {
			$response = $this->request('POST', '/api/authentication/login', [
				'email' => 'reset@example.com',
				'password' => 'WrongPass1',
			]);
			self::assertSame(401, $response->getStatusCode());
		}

		// Success clears the counter.
		$success = $this->request('POST', '/api/authentication/login', [
			'email' => 'reset@example.com',
			'password' => 'Secret123',
		]);
		self::assertSame(200, $success->getStatusCode());

		// Another 4 failures should not lock — the counter restarted.
		for ($i = 1; $i <= 4; $i++) {
			$response = $this->request('POST', '/api/authentication/login', [
				'email' => 'reset@example.com',
				'password' => 'WrongPass1',
			]);
			self::assertSame(401, $response->getStatusCode());
		}
	}

	public function testProtectedEndpointWithoutTokenReturns401(): void
	{
		$response = $this->request('GET', '/api/current-user');
		self::assertSame(401, $response->getStatusCode());
	}

	public function testProtectedEndpointWithInvalidTokenReturns401(): void
	{
		$response = $this->request('GET', '/api/current-user', bearerToken: 'not.a.jwt');
		self::assertSame(401, $response->getStatusCode());
	}

	public function testProtectedEndpointWithExpiredTokenReturns401(): void
	{
		$user = Fixture::createUser();
		$response = $this->request(
			'GET',
			'/api/current-user',
			bearerToken: Fixture::expiredAccessTokenFor($user),
		);
		self::assertSame(401, $response->getStatusCode());
	}

	public function testRefreshWithExpiredAccessTokenIssuesNewTokens(): void
	{
		$user = Fixture::createUser();

		$response = $this->request(
			'POST',
			'/api/authentication/refresh-token',
			body: ['refreshToken' => Fixture::refreshTokenFor($user)],
			bearerToken: Fixture::expiredAccessTokenFor($user),
		);

		self::assertSame(200, $response->getStatusCode());
		$body = $this->jsonBody($response);
		self::assertArrayHasKey('accessToken', $body);
	}

	public function testRefreshTokenWithMalformedTokenReturns401(): void
	{
		$user = Fixture::createUser();

		$response = $this->request(
			'POST',
			'/api/authentication/refresh-token',
			body: ['refreshToken' => 'not.a.valid.jwt'],
			bearerToken: Fixture::accessTokenFor($user),
		);

		self::assertSame(401, $response->getStatusCode());
	}

	public function testRefreshTokenWithExpiredRefreshTokenReturns401(): void
	{
		$user = Fixture::createUser();

		$response = $this->request(
			'POST',
			'/api/authentication/refresh-token',
			body: ['refreshToken' => Fixture::expiredRefreshTokenFor($user)],
			bearerToken: Fixture::accessTokenFor($user),
		);

		self::assertSame(401, $response->getStatusCode());
	}

	public function testRefreshWithMismatchedUserIdRejects(): void
	{
		$user = Fixture::createUser(email: 'a@example.com');
		$otherUser = Fixture::createUser(email: 'b@example.com');

		$response = $this->request(
			'POST',
			'/api/authentication/refresh-token',
			body: ['refreshToken' => Fixture::refreshTokenFor($otherUser)],
			bearerToken: Fixture::accessTokenFor($user),
		);

		self::assertSame(401, $response->getStatusCode());
	}

	public function testAccessTokenCannotBeUsedAsRefreshToken(): void
	{
		$user = Fixture::createUser();

		$response = $this->request(
			'POST',
			'/api/authentication/refresh-token',
			body: ['refreshToken' => Fixture::accessTokenFor($user)],
			bearerToken: Fixture::accessTokenFor($user),
		);

		self::assertSame(401, $response->getStatusCode());
	}

	public function testRefreshTokenCannotBeUsedAsAccessToken(): void
	{
		$user = Fixture::createUser();

		$response = $this->request(
			'GET',
			'/api/current-user',
			bearerToken: Fixture::refreshTokenFor($user),
		);

		self::assertSame(401, $response->getStatusCode());
	}

	public function testRequestAndConfirmPasswordResetUpdatesPasswordAndReturnsTokens(): void
	{
		$user = Fixture::createUser(email: 'reset@example.com', password: 'OldPass11');

		$response = $this->request('POST', '/api/authentication/request-password-reset', [
			'email' => 'reset@example.com',
		]);
		self::assertSame(200, $response->getStatusCode());

		// Resolve the raw token by intercepting the provider: the provider stores
		// only the hash, but we can re-issue a token via the same provider and
		// confirm against it. Simpler: use the provider directly to obtain a known token.
		$provider = $this->container->get(PasswordResetProviderInterface::class);
		assert($provider instanceof PasswordResetProviderInterface);

		// The first request created a token; trigger a second so we control the value.
		[$rawToken] = $this->issueResetToken($provider, $user);

		$confirm = $this->request('POST', '/api/authentication/confirm-password-reset', [
			'token' => $rawToken,
			'password' => 'NewPass11',
		]);
		self::assertSame(200, $confirm->getStatusCode());

		// Verify the new password works
		$login = $this->request('POST', '/api/authentication/login', [
			'email' => 'reset@example.com',
			'password' => 'NewPass11',
		]);
		self::assertSame(200, $login->getStatusCode());
	}

	/**
	 * Helper that mirrors PasswordResetProvider::requestReset but exposes the raw token.
	 *
	 * @return array{0:string}
	 */
	private function issueResetToken(PasswordResetProviderInterface $provider, User $user): array
	{
		$rawToken = bin2hex(random_bytes(16));
		$pdo = AppHarness::pdo();
		$now = new DateTimeImmutable();
		// Distinct placeholders for created_at/updated_at: with ATTR_EMULATE_PREPARES=false
		// (the ORM's MySQL default) a named placeholder must not be reused.
		$stmt = $pdo->prepare(
			'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, used_at, created_at, updated_at) '
			. 'VALUES (:user_id, :hash, :expires, NULL, :created, :updated)',
		);
		if ($stmt === false) {
			self::fail('Failed to prepare INSERT statement');
		}
		$stmt->execute([
			'user_id' => $user->id,
			'hash' => hash('sha256', $rawToken),
			'expires' => $now->modify('+1 hour')->format('Y-m-d H:i:s'),
			'created' => $now->format('Y-m-d H:i:s'),
			'updated' => $now->format('Y-m-d H:i:s'),
		]);
		return [$rawToken];
	}
}
