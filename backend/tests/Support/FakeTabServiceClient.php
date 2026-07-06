<?php

declare(strict_types=1);

namespace Kytarna\Tests\Support;

use Kytarna\Service\Tab\Dto\TabConversionResult;
use Kytarna\Service\Tab\Dto\TabMetadata;
use Kytarna\Service\Tab\Dto\TabValidationError;
use Kytarna\Service\Tab\Dto\TabValidationResult;
use Kytarna\Service\Tab\Exception\TabServiceException;
use Kytarna\Service\Tab\Exception\TabValidationException;
use Kytarna\Service\Tab\TabServiceClientInterface;

/**
 * In-memory tab-service double so unit/provider tests never hit the network. Configure the next
 * validate()/convert() outcome via the public properties before exercising the code under test.
 */
final class FakeTabServiceClient implements TabServiceClientInterface
{
	/** When set, validate() returns this result; otherwise a "valid" result with $metadata is returned. */
	public ?TabValidationResult $validationResult = null;

	/** When set, convert() returns this; otherwise a default converted result is returned. */
	public ?TabConversionResult $conversionResult = null;

	/** When set, convert() throws this validation exception (simulates an unparseable .gp file). */
	public ?TabValidationException $conversionValidationException = null;

	/** When true, both endpoints throw TabServiceException (simulates the service being down). */
	public bool $unreachable = false;

	public ?TabMetadata $metadata = null;

	/** @var list<string> */
	public array $validatedAlphaTex = [];

	public int $convertCalls = 0;

	public function validate(string $alphaTex): TabValidationResult
	{
		$this->validatedAlphaTex[] = $alphaTex;

		if ($this->unreachable) {
			throw new TabServiceException('tab-service unreachable (fake).');
		}

		return $this->validationResult ?? new TabValidationResult(true, [], $this->metadata ?? $this->defaultMetadata());
	}

	public function convert(string $bytes): TabConversionResult
	{
		$this->convertCalls++;

		if ($this->unreachable) {
			throw new TabServiceException('tab-service unreachable (fake).');
		}
		if ($this->conversionValidationException !== null) {
			throw $this->conversionValidationException;
		}

		return $this->conversionResult
			?? new TabConversionResult('\\title "Imported" . :4 0.6', $this->metadata ?? $this->defaultMetadata());
	}

	public static function invalid(string $message = 'Unexpected token.', int $line = 1, int $col = 1): TabValidationResult
	{
		return new TabValidationResult(false, [new TabValidationError($message, $line, $col, 0)], null);
	}

	private function defaultMetadata(): TabMetadata
	{
		return new TabMetadata('Imported', 'Artist', null, 120, 1, []);
	}
}
