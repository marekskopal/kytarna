<?php

declare(strict_types=1);

namespace Ukolio\Service\Script\Host;

use RuntimeException;
use Ukolio\Service\Provider\ProjectProviderInterface;
use Ukolio\Service\Provider\StatusProviderInterface;
use Ukolio\Service\Provider\WorkflowProviderInterface;
use const JSON_THROW_ON_ERROR;

/**
 * The object graph bound to the JS global `ukolio`. Public properties (tasks/projects/events/vars/context)
 * and public methods (log/fetch/workflow) are what V8Js surfaces to the script; private dependencies
 * stay hidden from the sandbox.
 */
final readonly class ScriptHostApi
{
	/** @param array<string, mixed> $context */
	public function __construct(
		public TasksApi $tasks,
		public ProjectsApi $projects,
		public EventsApi $events,
		public VarsApi $vars,
		public array $context,
		private ScriptRunContext $runContext,
		private ProjectProviderInterface $projectProvider,
		private StatusProviderInterface $statusProvider,
		private WorkflowProviderInterface $workflowProvider,
		private HttpFetcher $fetcher,
	) {
	}

	public function log(mixed ...$args): void
	{
		$this->runContext->log(implode(' ', array_map([self::class, 'stringify'], $args)));
	}

	public function fetch(string $url, mixed $options = null): HttpResponse
	{
		$this->runContext->recordHttpCall();

		return $this->fetcher->fetch($url, JsValue::toAssoc($options), $this->runContext->getAllowedFetchHosts());
	}

	public function workflow(int $projectId): WorkflowApi
	{
		$this->runContext->recordTaskApiCall();

		$project = $this->projectProvider->getProject($this->runContext->workspace, $projectId)
			?? throw new RuntimeException(sprintf('Project %d not found.', $projectId));

		$workflow = $this->workflowProvider->getWorkflowByProject($project)
			?? throw new RuntimeException(sprintf('Workflow for project %d not found.', $projectId));

		$statuses = [];
		foreach ($this->statusProvider->getStatuses($workflow) as $status) {
			$statuses[] = HostSerializer::status($status);
		}

		return new WorkflowApi($statuses);
	}

	private static function stringify(mixed $value): string
	{
		if (is_string($value)) {
			return $value;
		}
		if (is_bool($value)) {
			return $value ? 'true' : 'false';
		}
		if ($value === null) {
			return 'null';
		}
		if (is_scalar($value)) {
			return (string) $value;
		}

		return json_encode($value, JSON_THROW_ON_ERROR);
	}
}
