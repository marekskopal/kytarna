<?php

declare(strict_types=1);

namespace Kytario\Dto;

use DateTimeImmutable;
use RuntimeException;

/**
 * Strict YYYY-MM-DD parsing for request-body / MCP date inputs. Null or empty string means "no date";
 * anything else that isn't a valid calendar date throws a RuntimeException so the controller answers
 * 422 (and MCP surfaces a tool error) instead of an uncaught DateMalformedStringException → 500.
 */
final class DateInput
{
	public static function parse(mixed $value, string $field): ?DateTimeImmutable
	{
		if ($value === null || $value === '') {
			return null;
		}

		if (is_string($value)) {
			$date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
			$errors = DateTimeImmutable::getLastErrors();
			if ($date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
				return $date;
			}
		}

		throw new RuntimeException(sprintf('Invalid %s value; expected YYYY-MM-DD.', $field));
	}
}
