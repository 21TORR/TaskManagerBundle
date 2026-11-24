<?php declare(strict_types=1);

namespace Tests\Torr\TaskManager\Worker;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Torr\TaskManager\Worker\WorkerLimits;

/**
 * @internal
 */
final class WorkerLimitsTest extends TestCase
{
	/**
	 */
	private function createDefinition () : InputDefinition
	{
		return new InputDefinition([
			new InputOption("limit", "l", InputOption::VALUE_REQUIRED),
			new InputOption("failure-limit", "f", InputOption::VALUE_REQUIRED),
			new InputOption("memory-limit", "m", InputOption::VALUE_REQUIRED),
			new InputOption("time-limit", "t", InputOption::VALUE_REQUIRED),
			new InputOption("sleep", null, InputOption::VALUE_REQUIRED, default: 1),
		]);
	}

	public function testFromInput () : void
	{
		$input = new ArrayInput([
			"--limit" => "10",
			"--failure-limit" => "3",
			"--memory-limit" => "128M",
			"--time-limit" => "3600",
			"--sleep" => "0.5",
		], $this->createDefinition());

		$limits = WorkerLimits::fromInput($input);

		self::assertSame(10, $limits->messageLimit);
		self::assertSame(3, $limits->failureLimit);
		self::assertSame(128 * 1024 * 1024, $limits->memoryLimit);
		self::assertSame(3600, $limits->timeLimit);
		self::assertSame(0.5, $limits->sleep);
	}

	public function testFromInputWithoutLimits () : void
	{
		$limits = WorkerLimits::fromInput(new ArrayInput([], $this->createDefinition()));

		self::assertNull($limits->messageLimit);
		self::assertNull($limits->failureLimit);
		self::assertNull($limits->memoryLimit);
		self::assertNull($limits->timeLimit);
		self::assertSame(1.0, $limits->sleep);
	}

	/**
	 */
	public static function provideParseBytes () : iterable
	{
		yield ["1024", 1024];
		yield ["128M", 128 * 1024 * 1024];
		yield ["1G", 1024 * 1024 * 1024];
		yield ["-1", null];
		yield ["0", null];
	}

	/**
	 */
	#[DataProvider("provideParseBytes")]
	public function testParseBytes (string $value, ?int $expected) : void
	{
		self::assertSame($expected, WorkerLimits::parseBytes($value));
	}
}
