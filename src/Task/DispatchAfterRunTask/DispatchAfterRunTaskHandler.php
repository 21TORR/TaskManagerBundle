<?php declare(strict_types=1);

namespace Torr\TaskManager\Task\DispatchAfterRunTask;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
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
	) {}

	/**
	 *
	 */
	#[AsMessageHandler]
	public function onDispatchAfterRunTask (DispatchAfterRunTask $task) : void
	{
		$stamps = !empty($task->transportNames)
			? [new TransportNamesStamp($task->transportNames)]
			: [];

		$this->taskManager->enqueue($task->task, $stamps);
	}
}
