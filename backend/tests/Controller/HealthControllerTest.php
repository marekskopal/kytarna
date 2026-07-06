<?php

declare(strict_types=1);

namespace Kytarna\Tests\Controller;

use Kytarna\Controller\HealthController;
use Kytarna\Tests\Support\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(HealthController::class)]
final class HealthControllerTest extends IntegrationTestCase
{
	public function testHealthEndpointReturnsOk(): void
	{
		$response = $this->request('GET', '/api/health');

		self::assertSame(200, $response->getStatusCode());
		self::assertSame(['status' => 'ok', 'database' => 'ok'], $this->jsonBody($response));
	}
}
