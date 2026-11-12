<?php declare(strict_types=1);

namespace Torr\TaskManager\Director;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Torr\TaskManager\Task\Task;

final readonly class TaskDirector
{
	/**
	 * @param OutputInterface|null $mainOutput the output the handlers write to (in addition to the run), defaults to the console
	 */
	public function __construct (
		private ActiveRunRegistry $activeRuns,
		private LoggerInterface $logger,
		private ?OutputInterface $mainOutput = null,
	) {}

	/**
	 * Starts working on the run of the given task.
	 *
	 * The run itself is created as soon as the message is handled, this attaches the output and the result to it.
	 */
	public function startRun (Task $task) : RunDirector
	{
		$activeRun = $this->activeRuns->get($task);

		if (null === $activeRun)
		{
			$this->logger->critical("Could not find the run of the task of type {type}", [
				"type" => get_debug_type($task),
				"task" => $task,
			]);
		}

		return new RunDirector($activeRun, $this->mainOutput);
	}
}
