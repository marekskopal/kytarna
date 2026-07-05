<?php

declare(strict_types=1);

namespace Ukolio\Service\Script\Host;

use Ukolio\Mcp\Dto\McpEventDto;
use Ukolio\Mcp\Dto\McpProjectDto;
use Ukolio\Mcp\Dto\McpStatusDto;
use Ukolio\Mcp\Dto\McpTaskDto;
use Ukolio\Model\Entity\Event;
use Ukolio\Model\Entity\Project;
use Ukolio\Model\Entity\Status;
use Ukolio\Model\Entity\Task;
use const JSON_THROW_ON_ERROR;

/**
 * Converts domain entities into plain associative arrays for the JS sandbox, reusing the MCP
 * DTOs so the shapes match what agents already see. V8Js turns the arrays into JS objects.
 */
final class HostSerializer
{
	/** @return array<string, mixed> */
	public static function task(Task $task): array
	{
		return self::toArray(McpTaskDto::fromEntity($task));
	}

	/** @return array<string, mixed> */
	public static function project(Project $project): array
	{
		return self::toArray(McpProjectDto::fromEntity($project));
	}

	/** @return array<string, mixed> */
	public static function status(Status $status): array
	{
		return self::toArray(McpStatusDto::fromEntity($status));
	}

	/** @return array<string, mixed> */
	public static function event(Event $event): array
	{
		return self::toArray(McpEventDto::fromEntity($event));
	}

	/** @return array<string, mixed> */
	private static function toArray(object $dto): array
	{
		/** @var array<string, mixed> $decoded */
		$decoded = json_decode(json_encode($dto, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);

		return $decoded;
	}
}
