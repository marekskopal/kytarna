<?php

declare(strict_types=1);

namespace Ukolio\Service\Request;

use Psr\Http\Message\ServerRequestInterface;
use Ukolio\Dto\ArrayFactoryInterface;
use Ukolio\Model\Entity\User;

interface RequestServiceInterface
{
	public function getUser(ServerRequestInterface $request): User;

	/** @return array<mixed> */
	public function getRequestBody(ServerRequestInterface $request): array;

	/**
	 * @param class-string<T> $dtoClass
	 * @return T
	 * @template T of ArrayFactoryInterface
	 */
	public function getRequestBodyDto(ServerRequestInterface $request, string $dtoClass): object;
}
