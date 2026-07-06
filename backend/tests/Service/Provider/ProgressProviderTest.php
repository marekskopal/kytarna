<?php

declare(strict_types=1);

namespace Kytarna\Tests\Service\Provider;

use Kytarna\Service\Provider\ProgressProvider;
use Kytarna\Service\Provider\ProgressProviderInterface;
use Kytarna\Tests\Support\Fixture;
use Kytarna\Tests\Support\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ProgressProvider::class)]
final class ProgressProviderTest extends IntegrationTestCase
{
	public function testPracticeSummaryAggregatesTotalsWeeksAndBpmTrend(): void
	{
		$provider = $this->provider();

		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$course = Fixture::createCourse($user, $workspace);
		$lecture = Fixture::createLecture($user, $course);

		// Two sessions in ISO week 2026-W23, one in 2026-W24.
		Fixture::createProgressEntry($user, $lecture, '2026-06-01', 'warm up', 80, 20);
		Fixture::createProgressEntry($user, $lecture, '2026-06-03', null, 90, 15);
		Fixture::createProgressEntry($user, $lecture, '2026-06-08', 'faster', 100, 30);

		$summary = $provider->summarizeLecture($user, $lecture);

		self::assertSame(3, $summary->totalEntries);
		self::assertSame(65, $summary->totalMinutes);
		self::assertSame(2, $summary->entriesPerWeek['2026-W23'] ?? 0);
		self::assertSame(1, $summary->entriesPerWeek['2026-W24'] ?? 0);
		self::assertSame(
			[
				['practicedAt' => '2026-06-01', 'tempoBpm' => 80],
				['practicedAt' => '2026-06-03', 'tempoBpm' => 90],
				['practicedAt' => '2026-06-08', 'tempoBpm' => 100],
			],
			$summary->bpmTrend,
		);
	}

	public function testPracticeSummaryRespectsDateRangeAndCourseScope(): void
	{
		$provider = $this->provider();

		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$course = Fixture::createCourse($user, $workspace);
		$lectureA = Fixture::createLecture($user, $course, 'Lecture A');
		$lectureB = Fixture::createLecture($user, $course, 'Lecture B');

		Fixture::createProgressEntry($user, $lectureA, '2026-06-01', null, null, 10);
		Fixture::createProgressEntry($user, $lectureB, '2026-06-10', null, null, 25);
		Fixture::createProgressEntry($user, $lectureB, '2026-07-01', null, null, 40);

		// Course summary spans both lectures.
		$courseSummary = $provider->summarizeCourse($user, $course);
		self::assertSame(3, $courseSummary->totalEntries);
		self::assertSame(75, $courseSummary->totalMinutes);

		// Date range filters entries out (inclusive bounds).
		$ranged = $provider->summarizeCourse($user, $course, '2026-06-01', '2026-06-30');
		self::assertSame(2, $ranged->totalEntries);
		self::assertSame(35, $ranged->totalMinutes);

		// Lecture scope limits to one lecture.
		$lectureSummary = $provider->summarizeLecture($user, $lectureB);
		self::assertSame(2, $lectureSummary->totalEntries);
	}

	private function provider(): ProgressProviderInterface
	{
		$provider = $this->container->get(ProgressProviderInterface::class);
		assert($provider instanceof ProgressProviderInterface);
		return $provider;
	}
}
