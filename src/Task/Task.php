<?php declare(strict_types=1);

namespace Torr\TaskManager\Task;

use Symfony\Component\Uid\Ulid;

/**
 * A runnable task
 */
abstract readonly class Task
{
	public string $ulid;

	/**
	 */
	public function __construct ()
	{
		$this->ulid = new Ulid()->toBase58();
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
		return clone($this, [
			"ulid" => new Ulid()->toBase58(),
		]);
	}
}
