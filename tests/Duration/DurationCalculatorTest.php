<?php declare(strict_types=1);

namespace Tests\Torr\TaskManager\Duration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Torr\TaskManager\Duration\DurationCalculator;

/**
 * @internal
 */
final class DurationCalculatorTest extends TestCase
{
	/**
	 *
	 */
	public static function provideCalculation () : iterable
	{
		yield "hours-minutes-seconds-microseconds" => [
			"2020-01-01 00:00:00.000000",
			"2020-01-01 01:02:03.123456",
			(3600 + 120 + 3.123456) / 1e9,
		];

		yield "years" => [
			"2021-01-01 00:00:00.000000",
			"2022-01-01 00:00:00.000000",
			(365 * 24 * 60 * 60) / 1e9,
		];

		yield "months" => [
			"2021-01-01 00:00:00.000000",
			"2021-02-01 00:00:00.000000",
			(31 * 24 * 60 * 60) / 1e9,
		];

		yield "days" => [
			"2021-01-01 00:00:00.000000",
			"2021-01-04 00:00:00.000000",
			(3 * 24 * 60 * 60) / 1e9,
		];
	}

	/**
	 *
	 */
	#[DataProvider("provideCalculation")]
	public function testCalculation (
		string $start,
		string $end,
		float $expected,
	) : void
	{
		$start = \DateTimeImmutable::createFromFormat("!Y-m-d H:i:s.u", $start);
		$end = \DateTimeImmutable::createFromFormat("!Y-m-d H:i:s.u", $end);
		$calculator = new DurationCalculator();

		self::assertSame($expected, $calculator->calculateDuration($start, $end));
	}
}
