<?php declare(strict_types=1);

namespace Torr\TaskManager\Worker;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * The limits a messenger worker was started with (the options of `messenger:consume`).
 * A `null` value means that the limit isn't set.
 */
#[Exclude]
final readonly class WorkerLimits
{
	/**
	 */
	public function __construct (
		/**
		 * The number of messages after which the worker stops (`--limit`)
		 */
		public ?int $messageLimit = null,
		/**
		 * The number of failed messages after which the worker stops (`--failure-limit`)
		 */
		public ?int $failureLimit = null,
		/**
		 * The memory usage (in bytes) after which the worker stops (`--memory-limit`)
		 */
		public ?int $memoryLimit = null,
		/**
		 * The time (in seconds) after which the worker stops (`--time-limit`)
		 */
		public ?int $timeLimit = null,
		/**
		 * The time (in seconds) the worker sleeps if no message was found (`--sleep`)
		 */
		public ?float $sleep = null,
	) {}

	/**
	 * Reads the limits from the (bound) input of the `messenger:consume` command
	 */
	public static function fromInput (InputInterface $input) : self
	{
		$memoryLimit = self::getOption($input, "memory-limit");

		return new self(
			messageLimit: self::toInt(self::getOption($input, "limit")),
			failureLimit: self::toInt(self::getOption($input, "failure-limit")),
			memoryLimit: null !== $memoryLimit ? self::parseBytes($memoryLimit) : null,
			timeLimit: self::toInt(self::getOption($input, "time-limit")),
			sleep: is_numeric($sleep = self::getOption($input, "sleep")) ? (float) $sleep : null,
		);
	}

	/**
	 * Parses a memory value like `128M` or `1G` (= the format of the `memory_limit` ini setting) to bytes.
	 * Returns `null` for unlimited / invalid values.
	 */
	public static function parseBytes (string $value) : ?int
	{
		try
		{
			$bytes = ini_parse_quantity($value);
		}
		catch (\Throwable)
		{
			return null;
		}

		return $bytes > 0 ? $bytes : null;
	}

	/**
	 */
	private static function getOption (InputInterface $input, string $name) : ?string
	{
		if (!$input->hasOption($name))
		{
			return null;
		}

		$value = $input->getOption($name);

		return \is_scalar($value) && "" !== (string) $value
			? (string) $value
			: null;
	}

	/**
	 */
	private static function toInt (?string $value) : ?int
	{
		return is_numeric($value) && (int) $value > 0
			? (int) $value
			: null;
	}
}
