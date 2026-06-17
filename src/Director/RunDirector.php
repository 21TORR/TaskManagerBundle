<?php declare(strict_types=1);

namespace Torr\TaskManager\Director;

use Symfony\Component\Console\Input\ArrayInput;
use Torr\Cli\Console\Style\TorrStyle;
use Torr\TaskManager\Console\ChainOutput;
use Torr\TaskManager\Entity\TaskRun;
use Torr\TaskManager\Model\TaskLogModel;

final class RunDirector
{
	public private(set) TorrStyle $io;
	private ChainOutput $output;

	/**
	 */
	public function __construct (
		private readonly TaskLogModel $logModel,
		private readonly ?TaskRun $run,
	)
	{
		$this->output = new ChainOutput();
		$this->io = new TorrStyle(
			new ArrayInput([]),
			$this->output,
		);
	}

	/**
	 *
	 */
	public function getIo () : TorrStyle
	{
		return $this->io;
	}

	/**
	 * Marks the task as finished
	 */
	public function finish (bool $success) : void
	{
		if (null === $this->run)
		{
			return;
		}

		$this->run->finish($success, $this->output->getBufferedOutput());
		$this->logModel->flush();
	}
}
