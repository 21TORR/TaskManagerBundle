<?php declare(strict_types=1);

namespace Torr\TaskManager\Task;

use Symfony\Component\Uid\UuidV7;

/**
 * A runnable task
 */
abstract readonly class Task
{
	/**
	 * @deprecated use $taskId instead
	 *
	 * @todo remove in 4.0
	 *
	 * @phpstan-ignore-next-line property.deprecated (The uuid integration will be refactored in v4)
	 */
	public string $ulid;

	/**
	 */
	public function __construct ()
	{
		$uuid = new UuidV7()->toString();
		/** @phpstan-ignore-next-line property.deprecated (We still need to support the deprecated property) */
		$this->ulid = $uuid;
	}

	/**
	 * Defines the metadata for this task.
	 *
	 * It is important that this data is generated on the fly, so that we can change the label for already
	 * serialized messages as well.
	 */
	abstract public function getMetaData () : TaskMetaData;

	/**
	 *
	 */
	public function withNewTaskUlid () : static
	{
		$uuid = new UuidV7()->toString();

		return clone($this, [
			"ulid" => $uuid,
		]);
	}

	/**
	 * Is called before the task is stored in the task log entry.
	 * You can clone the task here to remove / redact / truncate fields in the normalized task.
	 */
	public function prepareForTaskLog () : static
	{
		return $this;
	}
}
