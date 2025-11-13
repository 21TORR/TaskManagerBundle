<?php declare(strict_types=1);

namespace Torr\TaskManager\Task\DispatchAfterRunTask;

use Symfony\Component\Messenger\Attribute\AsMessage;
use Torr\TaskManager\Task\Task;
use Torr\TaskManager\Task\TaskMetaData;
use Torr\TaskManager\Transport\TransportsHelper;

/**
 * This task takes another task and puts it into the queue.
 *
 * This task is supposed to be worked on synchronously, as it is pretty lightweight and only
 * redispatches the given task.
 */
#[AsMessage(transport: TransportsHelper::INTERNAL_TRANSPORT_NAME)]
readonly class DispatchAfterRunTask extends Task
{
	public function __construct (
		public Task $task,
		public array|string $transportNames = [],
	)
	{
		parent::__construct();
	}

	/**
	 *
	 */
	#[\Override]
	public function getMetaData () : TaskMetaData
	{
		return new TaskMetaData(
			\sprintf("Redispatch task '%s' after the current run", $this->task->getMetaData()->label),
		);
	}
}
