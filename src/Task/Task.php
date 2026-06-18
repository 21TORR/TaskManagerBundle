<?php declare(strict_types=1);

namespace Torr\TaskManager\Task;

/**
 * A runnable task
 */
abstract readonly class Task
{
	/**
	 * Defines the metadata for this task.
	 *
	 * It is important that this data is generated on the fly, so that we can change the label for already
	 * serialized messages as well.
	 */
	abstract public function getMetaData () : TaskMetaData;

	/**
	 * Is called before the task is stored in the task log entry.
	 * You can clone the task here to remove / redact / truncate fields in the normalized task.
	 */
	public function prepareForTaskLog () : static
	{
		return $this;
	}
}
