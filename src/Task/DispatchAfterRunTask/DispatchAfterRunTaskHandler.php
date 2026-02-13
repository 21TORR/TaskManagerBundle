<?php declare(strict_types=1);

namespace Torr\TaskManager\Task\DispatchAfterRunTask;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Torr\TaskManager\Director\TaskDirector;
use Torr\TaskManager\Manager\TaskManager;

/**
 * @final
 */
readonly class DispatchAfterRunTaskHandler
{
	/**
	 */
	public function __construct (
		private TaskManager $taskManager,
		private TaskDirector $taskDirector,
	) {}

	/**
	 *
	 */
	#[AsMessageHandler]
	public function onDispatchAfterRunTask (DispatchAfterRunTask $task) : void
	{
		$run = $this->taskDirector->startRun($task);

		$run->io->writeln(\sprintf(
			"Redispatching task <fg=yellow>%s</>",
			$task->task::class,
		));

		$stamps = !empty($task->transportNames)
			? [new TransportNamesStamp($task->transportNames)]
			: [];

		$this->taskManager->enqueue($task->task, $stamps);
		$run->finish(success: true);
	}
}
