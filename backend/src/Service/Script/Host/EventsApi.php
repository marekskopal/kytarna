<?php

declare(strict_types=1);

namespace Ukolio\Service\Script\Host;

use RuntimeException;
use Ukolio\Model\Entity\Enum\EventTypeEnum;
use Ukolio\Service\Provider\EventProviderInterface;

/**
 * Exposed to JS as `ukolio.events`. Reads the append-only audit log, scoped to the run's workspace
 * and authorised as the script's owner. Counts against the per-run task-API cap. Lets scripts answer
 * "when did this task enter status X" by inspecting TaskMoved events.
 */
final readonly class EventsApi
{
	private const int DefaultLimit = 50;
	private const int MaxLimit = 200;

	public function __construct(private ScriptRunContext $context, private EventProviderInterface $eventProvider,)
	{
	}

	/**
	 * List workspace events, newest first. Filters: { projectId?, taskId?, type?, limit?, offset? }.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function list(mixed $filters = null): array
	{
		$this->context->recordTaskApiCall();

		$f = JsValue::toAssoc($filters);
		$limit = min(max(JsValue::int($f['limit'] ?? null) ?? self::DefaultLimit, 1), self::MaxLimit);
		$offset = max(JsValue::int($f['offset'] ?? null) ?? 0, 0);
		$projectId = JsValue::int($f['projectId'] ?? null);
		$taskId = JsValue::int($f['taskId'] ?? null);
		$type = $this->resolveType(JsValue::string($f['type'] ?? null));

		$out = [];
		$events = $this->eventProvider->getWorkspaceEventsFiltered($this->context->workspace, $projectId, $taskId, $type, $limit, $offset);
		foreach ($events as $event) {
			$out[] = HostSerializer::event($event);
		}

		return $out;
	}

	private function resolveType(?string $type): ?EventTypeEnum
	{
		if ($type === null || $type === '') {
			return null;
		}

		return EventTypeEnum::tryFrom($type)
			?? throw new RuntimeException(sprintf('Unknown event type "%s".', $type));
	}
}
