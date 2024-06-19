<?php declare(strict_types=1);

namespace Torr\TaskManager\Log\Task;

use Torr\TaskManager\Task\Task;
use Torr\TaskManager\Task\TaskMetaData;

final readonly class CleanOutdatedLogsTask extends Task implements \Stringable
{
	/**
	 * @inheritDoc
	 */
	public function getMetaData () : TaskMetaData
	{
		return new TaskMetaData(
			label: "Clean log entries",
			group: "Task Manager",
			uniqueTaskId: "task-manager.clean-log",
		);
	}

	/**
	 *
	 */
	public function __toString () : string
	{
		return $this->getMetaData()->label;
	}
}
