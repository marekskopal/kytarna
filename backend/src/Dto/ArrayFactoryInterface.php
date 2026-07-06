<?php

declare(strict_types=1);

namespace Kytarna\Dto;

/** @template D of array */
interface ArrayFactoryInterface
{
	/** @param D $data */
	public static function fromArray(array $data): static;
}
