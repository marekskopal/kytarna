<?php

declare(strict_types=1);

namespace Kytario\Service\Script\Host;

use Kytario\Mcp\Tool\Helper\PriorityResolver;
use Kytario\Mcp\Tool\Helper\StatusResolver;
use Kytario\Service\Provider\EventProviderInterface;
use Kytario\Service\Provider\ProjectProviderInterface;
use Kytario\Service\Provider\StatusProviderInterface;
use Kytario\Service\Provider\TaskCodeResolverInterface;
use Kytario\Service\Provider\TaskCommentProviderInterface;
use Kytario\Service\Provider\TaskProviderInterface;
use Kytario\Service\Provider\TaskTagProviderInterface;
use Kytario\Service\Provider\WorkflowProviderInterface;
use Kytario\Service\Script\ScriptVariableProviderInterface;

/**
 * Assembles the per-run `kytario` host object graph, wiring the sandbox to the real domain
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
