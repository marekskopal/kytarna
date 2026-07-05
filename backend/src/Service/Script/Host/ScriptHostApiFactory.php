<?php

declare(strict_types=1);

namespace Ukolio\Service\Script\Host;

use Ukolio\Mcp\Tool\Helper\PriorityResolver;
use Ukolio\Mcp\Tool\Helper\StatusResolver;
use Ukolio\Service\Provider\EventProviderInterface;
use Ukolio\Service\Provider\ProjectProviderInterface;
use Ukolio\Service\Provider\StatusProviderInterface;
use Ukolio\Service\Provider\TaskCodeResolverInterface;
use Ukolio\Service\Provider\TaskCommentProviderInterface;
use Ukolio\Service\Provider\TaskProviderInterface;
use Ukolio\Service\Provider\TaskTagProviderInterface;
use Ukolio\Service\Provider\WorkflowProviderInterface;
use Ukolio\Service\Script\ScriptVariableProviderInterface;

/**
 * Assembles the per-run `ukolio` host object graph, wiring the sandbox to the real domain
 * providers. One ScriptHostApi is built per ScriptRunContext (i.e. per execution).
 */
final readonly class ScriptHostApiFactory
{
	public function __construct(
		private TaskProviderInterface $taskProvider,
		private TaskCodeResolverInterface $taskCodeResolver,
		private ProjectProviderInterface $projectProvider,
		private StatusProviderInterface $statusProvider,
		private WorkflowProviderInterface $workflowProvider,
		private PriorityResolver $priorityResolver,
		private StatusResolver $statusResolver,
		private TaskCommentProviderInterface $commentProvider,
		private TaskTagProviderInterface $taskTagProvider,
		private ScriptVariableProviderInterface $variableProvider,
		private EventProviderInterface $eventProvider,
		private HttpFetcher $fetcher,
	) {
	}

	public function create(ScriptRunContext $context): ScriptHostApi
	{
		$tasks = new TasksApi(
			$context,
			$this->taskProvider,
			$this->taskCodeResolver,
			$this->projectProvider,
			$this->priorityResolver,
			$this->statusResolver,
			$this->commentProvider,
			$this->taskTagProvider,
			$this->eventProvider,
		);

		return new ScriptHostApi(
			tasks: $tasks,
			projects: new ProjectsApi($context, $this->projectProvider),
			events: new EventsApi($context, $this->eventProvider),
			vars: new VarsApi($context, $this->variableProvider),
			context: $context->contextArray(),
			runContext: $context,
			projectProvider: $this->projectProvider,
			statusProvider: $this->statusProvider,
			workflowProvider: $this->workflowProvider,
			fetcher: $this->fetcher,
		);
	}
}
