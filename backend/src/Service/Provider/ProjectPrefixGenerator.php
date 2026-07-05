<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use Ukolio\Model\Entity\Workspace;
use Ukolio\Model\Repository\ProjectRepository;

final readonly class ProjectPrefixGenerator implements ProjectPrefixGeneratorInterface
{
	private const MaxPrefixLength = 16;

	public function __construct(private ProjectRepository $projectRepository)
	{
	}

	public function generate(Workspace $workspace, string $name, ?int $excludeProjectId): string
	{
		$words = $this->extractWords($name);
		$taken = array_flip($this->projectRepository->findPrefixesInWorkspace($workspace->id, $excludeProjectId));

		if ($words === []) {
			return $this->withNumericSuffix('P', $taken);
		}

		$lengths = array_fill(0, count($words), 1);

		while (true) {
			$candidate = $this->buildCandidate($words, $lengths);
			if (strlen($candidate) > self::MaxPrefixLength) {
				break;
			}
			if (!isset($taken[$candidate])) {
				return $candidate;
			}
			if (!$this->advanceLengths($words, $lengths)) {
				break;
			}
		}

		$base = $this->buildCandidate($words, array_fill(0, count($words), 1));
		return $this->withNumericSuffix($base, $taken);
	}

	/**
	 * @param list<string> $words
	 * @param list<int> $lengths
	 */
	private function buildCandidate(array $words, array $lengths): string
	{
		$out = '';
		foreach ($words as $i => $word) {
			$out .= substr($word, 0, $lengths[$i]);
		}
		return $out;
	}

	/**
	 * @param list<string> $words
	 * @param list<int> $lengths
	 */
	private function advanceLengths(array $words, array &$lengths): bool
	{
		for ($i = count($words) - 1; $i >= 0; $i--) {
			if ($lengths[$i] < strlen($words[$i])) {
				$lengths[$i]++;
				return true;
			}
		}
		return false;
	}

	/** @param array<string,int> $taken */
	private function withNumericSuffix(string $base, array $taken): string
	{
		if ($base === '') {
			$base = 'P';
		}
		$base = substr($base, 0, self::MaxPrefixLength);
		if (!isset($taken[$base])) {
			return $base;
		}
		$n = 2;
		while (true) {
			$candidate = substr($base, 0, self::MaxPrefixLength - strlen((string) $n)) . $n;
			if (!isset($taken[$candidate])) {
				return $candidate;
			}
			$n++;
		}
	}

	/** @return list<string> */
	private function extractWords(string $name): array
	{
		$tokens = preg_split('/\s+/', trim($name));
		if ($tokens === false) {
			return [];
		}
		$words = [];
		foreach ($tokens as $token) {
			$letters = preg_replace('/[^A-Z]/', '', strtoupper($token));
			if (is_string($letters) && $letters !== '') {
				$words[] = $letters;
			}
		}
		return $words;
	}
}
