<?php

declare(strict_types=1);

namespace Ukolio\Service\Script\Trigger;

use Cron\CronExpression;
use DateTimeImmutable;
use RuntimeException;

final readonly class CronEvaluator implements CronEvaluatorInterface
{
	public function isValid(string $expression): bool
	{
		return CronExpression::isValidExpression($expression);
	}

	public function isDue(string $expression, DateTimeImmutable $now): bool
	{
		if (!CronExpression::isValidExpression($expression)) {
			return false;
		}

		return (new CronExpression($expression))->isDue($now);
	}

	public function nextRunDate(string $expression, DateTimeImmutable $after, bool $allowCurrent = false): DateTimeImmutable
	{
		if (!CronExpression::isValidExpression($expression)) {
			throw new RuntimeException('Invalid cron expression.');
		}

		$next = (new CronExpression($expression))->getNextRunDate($after, 0, $allowCurrent);

		return DateTimeImmutable::createFromInterface($next);
	}
}
