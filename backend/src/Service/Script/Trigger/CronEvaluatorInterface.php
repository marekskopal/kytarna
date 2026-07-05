<?php

declare(strict_types=1);

namespace Ukolio\Service\Script\Trigger;

use DateTimeImmutable;

interface CronEvaluatorInterface
{
	public function isValid(string $expression): bool;

	public function isDue(string $expression, DateTimeImmutable $now): bool;

	/** First run strictly after `$after` (or at `$after` when `$allowCurrent`). Throws on an invalid expression. */
	public function nextRunDate(string $expression, DateTimeImmutable $after, bool $allowCurrent = false): DateTimeImmutable;
}
