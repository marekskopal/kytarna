<?php

declare(strict_types=1);

namespace Ukolio\Service\Translator;

use Ukolio\Model\Entity\Enum\LocaleEnum;

final class TranslatorService implements TranslatorServiceInterface
{
	/** @var array<string, array<string, mixed>> */
	private array $loaded = [];

	public function __construct(private readonly string $translationsDir)
	{
	}

	public function translate(string $key, LocaleEnum $locale): string
	{
		$value = $this->lookup($key, $locale);

		if ($value === null && $locale !== LocaleEnum::En) {
			$value = $this->lookup($key, LocaleEnum::En);
		}

		return is_string($value) ? $value : $key;
	}

	/** @return array<string, string> */
	public function section(string $key, LocaleEnum $locale): array
	{
		$data = $this->load($locale);
		$value = $this->traverse($data, $key);

		if (!is_array($value) && $locale !== LocaleEnum::En) {
			$data = $this->load(LocaleEnum::En);
			$value = $this->traverse($data, $key);
		}

		/** @var array<string, string> $result */
		$result = is_array($value) ? $value : [];
		return $result;
	}

	private function lookup(string $key, LocaleEnum $locale): mixed
	{
		$data = $this->load($locale);
		return $this->traverse($data, $key);
	}

	/** @return array<string, mixed> */
	private function load(LocaleEnum $locale): array
	{
		if (isset($this->loaded[$locale->value])) {
			return $this->loaded[$locale->value];
		}

		$file = $this->translationsDir . '/' . $locale->value . '.json';

		if (!file_exists($file)) {
			$this->loaded[$locale->value] = [];
			return [];
		}

		$content = file_get_contents($file);
		if ($content === false) {
			$this->loaded[$locale->value] = [];
			return [];
		}

		/** @var array<string, mixed>|null $decoded */
		$decoded = json_decode($content, true);
		$data = $decoded ?? [];

		$this->loaded[$locale->value] = $data;

		return $data;
	}

	/** @param array<string, mixed> $data */
	private function traverse(array $data, string $key): mixed
	{
		$parts = explode('.', $key);
		$value = $data;

		foreach ($parts as $part) {
			if (!is_array($value) || !array_key_exists($part, $value)) {
				return null;
			}
			$value = $value[$part];
		}

		return $value;
	}
}
