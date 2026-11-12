<?php declare(strict_types=1);

namespace Torr\TaskManager\Director;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;
use Torr\Cli\Console\Style\TorrStyle;
use Torr\TaskManager\Console\ChainOutput;

final class RunDirector
{
	public private(set) TorrStyle $io;
	private ChainOutput $output;
	private bool $finished = false;

	/**
	 */
	public function __construct (
		private readonly ?ActiveRun $activeRun,
		?OutputInterface $mainOutput = null,
	)
	{
		$this->output = new ChainOutput(mainOutput: $mainOutput);
		$this->io = new TorrStyle(
			new ArrayInput([]),
			$this->output,
		);

		$this->activeRun?->registerOutput($this->output);
	}

	/**
	 *
	 */
	public function getIo () : TorrStyle
	{
		return $this->io;
	}

	/**
	 * Marks the task as finished.
	 *
	 * The result is stored together with the output as soon as the message is handled completely.
	 */
	public function finish (bool $success) : void
	{
		if ($this->finished)
		{
			return;
		}

		$this->finished = true;
		$this->activeRun?->markAsFinished($success);
	}
}
