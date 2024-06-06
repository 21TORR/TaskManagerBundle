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
		$this->ulid = (new Ulid())->toBase58();
	}

	/**
	 * Defines the metadata for this task
	 */
	abstract public function getMetaData () : TaskMetaData;
}
