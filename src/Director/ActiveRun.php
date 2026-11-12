<?php declare(strict_types=1);

namespace Torr\TaskManager\Director;

use Torr\TaskManager\Console\ChainOutput;
use Torr\TaskManager\Entity\TaskRun;

/**
 * The run of the message that is currently being handled.
 *
 * It collects the output and results of all handlers, so that the run can be finalized after the message was handled.
 *
 * @internal
 */
final class ActiveRun
{
	/** @var list<ChainOutput> */
	private array $outputs = [];
	private int $finishedDirectors = 0;
	private bool $failed = false;

	/**
	 */
	public function __construct (
		public readonly TaskRun $run,
	) {}

	/**
	 * Registers the output of a handler, that started working on the run.
	 */
	public function registerOutput (ChainOutput $output) : void
	{
		$this->outputs[] = $output;
	}

	/**
	 * Stores the result of a handler.
	 */
	public function markAsFinished (bool $success) : void
	{
		++$this->finishedDirectors;

		if (!$success)
		{
			$this->failed = true;
		}
	}

	/**
	 * Returns whether any handler reported a failure.
	 */
	public function hasFailed () : bool
	{
		return $this->failed;
	}

	/**
	 * Returns whether every handler, that started working on the run, also finished it.
	 */
	public function wasFinishedProperly () : bool
	{
		return [] !== $this->outputs && $this->finishedDirectors >= \count($this->outputs);
	}

	/**
	 * Returns the combined output of all handlers, or null if no handler started working on the run.
	 */
	public function getOutput () : ?string
	{
		if ([] === $this->outputs)
		{
			return null;
		}

		$parts = [];

		foreach ($this->outputs as $output)
		{
			$buffered = $output->getBufferedOutput();

			if ("" !== trim($buffered))
			{
				$parts[] = rtrim($buffered);
			}
		}

		return implode("\n\n", $parts);
	}
}
