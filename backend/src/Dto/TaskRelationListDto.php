<?php

declare(strict_types=1);

namespace Kytario\Dto;

final readonly class TaskRelationListDto
{
	/**
	 * @param list<TaskRelationDto> $outgoing
	 * @param list<TaskRelationDto> $incoming
	 */
	public function __construct(public array $outgoing, public array $incoming,)
	{
	}
}
