<?php

declare(strict_types=1);

namespace Kytario\Command;

use DateTimeImmutable;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Kytario\App\ApplicationFactory;
use Kytario\Model\Entity\Enum\NotificationTypeEnum;
use Kytario\Model\Entity\Task;
use Kytario\Model\Entity\User;
use Kytario\Model\Repository\TaskRepository;
use Kytario\Service\Notification\NotificationDispatcherInterface;
use Kytario\Service\Provider\NotificationProviderInterface;
use Kytario\Service\Provider\TaskWatcherProviderInterface;
use const DATE_ATOM;

/**
 * Emits due-date reminders (U-83) for tasks due tomorrow (DueSoon) and today (DueToday), to each
 * task's assignee and watchers. Intended to run hourly from system cron:
 * `0 * * * * php /app/bin/console notifications:due-tick`. Per (task, user, type) de-duplication via
 * the notifications table makes the hourly schedule idempotent — each reminder fires at most once a day.
 */
final class NotificationDueTickCommand extends AbstractCommand
{
	protected function configure(): void
	{
		$this->setName('notifications:due-tick')
			->setDescription('Send due-date reminders for tasks due today and tomorrow (run hourly from cron).');
	}

	protected function process(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);
		$application = ApplicationFactory::create();

		$taskRepository = $application->container->get(TaskRepository::class);
		assert($taskRepository instanceof TaskRepository);
		$watcherProvider = $application->container->get(TaskWatcherProviderInterface::class);
		assert($watcherProvider instanceof TaskWatcherProviderInterface);
		$notificationProvider = $application->container->get(NotificationProviderInterface::class);
		assert($notificationProvider instanceof NotificationProviderInterface);
		$dispatcher = $application->container->get(NotificationDispatcherInterface::class);
		assert($dispatcher instanceof NotificationDispatcherInterface);

		$today = new DateTimeImmutable('today');
		$tomorrow = $today->modify('+1 day');

		$sent = 0;
		$sent += $this->remind(
			$taskRepository,
			$watcherProvider,
			$notificationProvider,
			$dispatcher,
			$today,
			NotificationTypeEnum::DueToday,
		);
		$sent += $this->remind(
			$taskRepository,
			$watcherProvider,
			$notificationProvider,
			$dispatcher,
			$tomorrow,
			NotificationTypeEnum::DueSoon,
		);

		$io->writeln(sprintf('Sent %d due-date reminder(s) at %s.', $sent, (new DateTimeImmutable())->format(DATE_ATOM)));

		return self::SUCCESS;
	}

	private function remind(
		TaskRepository $taskRepository,
		TaskWatcherProviderInterface $watcherProvider,
		NotificationProviderInterface $notificationProvider,
		NotificationDispatcherInterface $dispatcher,
		DateTimeImmutable $date,
		NotificationTypeEnum $type,
	): int {
		$sent = 0;

		foreach ($taskRepository->findDueOn($date) as $task) {
			foreach ($this->recipients($task, $watcherProvider) as $recipient) {
				if ($notificationProvider->dueReminderExistsToday($recipient->id, $task->id, $type)) {
					continue;
				}

				$dispatcher->dispatchDueReminder($task, $type, $recipient);
				$sent++;
			}
		}

		return $sent;
	}

	/**
	 * Assignee ∪ watchers, de-duplicated by user id.
	 *
	 * @return array<int, User>
	 */
	private function recipients(Task $task, TaskWatcherProviderInterface $watcherProvider): array
	{
		$recipients = [];

		if ($task->assignee !== null) {
			$recipients[$task->assignee->id] = $task->assignee;
		}

		foreach ($watcherProvider->listWatchers($task) as $watcher) {
			$recipients[$watcher->user->id] = $watcher->user;
		}

		return $recipients;
	}
}
