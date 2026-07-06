<?php

declare(strict_types=1);

namespace Kytarna\Service\Request;

use Kytarna\Dto\ArrayFactoryInterface;
use Kytarna\Model\Entity\User;
use Psr\Http\Message\ServerRequestInterface;

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
