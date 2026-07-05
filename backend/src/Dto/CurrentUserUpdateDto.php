<?php

declare(strict_types=1);

namespace Ukolio\Dto;

/**
 * @implements ArrayFactoryInterface<array{
 *     name?: string,
 *     locale?: string,
 *     theme?: string,
 *     defaultSavedViewId?: ?int,
 * }>
 */
final readonly class CurrentUserUpdateDto implements ArrayFactoryInterface
{
	public function __construct(
		public ?string $name,
		public ?string $locale,
		public ?string $theme,
		public ?int $defaultSavedViewId,
		public bool $defaultSavedViewIdProvided,
	) {
	}

	public static function fromArray(array $data): static
	{
		$defaultProvided = array_key_exists('defaultSavedViewId', $data);
		$defaultSavedViewId = $defaultProvided ? $data['defaultSavedViewId'] : null;

		return new self(
			name: $data['name'] ?? null,
			locale: $data['locale'] ?? null,
			theme: $data['theme'] ?? null,
			defaultSavedViewId: $defaultSavedViewId,
			defaultSavedViewIdProvided: $defaultProvided,
		);
	}
}
