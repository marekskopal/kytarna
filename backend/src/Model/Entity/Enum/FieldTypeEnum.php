<?php

declare(strict_types=1);

namespace Ukolio\Model\Entity\Enum;

enum FieldTypeEnum: string
{
	case Text = 'Text';
	case Textarea = 'Textarea';
	case Select = 'Select';
	case Version = 'Version';

	public function hasOptions(): bool
	{
		return $this === self::Select || $this === self::Version;
	}
}
