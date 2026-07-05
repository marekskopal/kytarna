<?php

declare(strict_types=1);

namespace Kytario\Tests\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use Kytario\Controller\HealthController;
use Kytario\Tests\Support\IntegrationTestCase;

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
