<?php declare(strict_types=1);

namespace Tests\Torr\TaskManager\Fixtures;

use Torr\TaskManager\Task\Task;
use Torr\TaskManager\Task\TaskMetaData;

/**
 * A task with a configurable label and group.
 */
final readonly class ExampleTask extends Task
{
	/**
	 */
	public function __construct (
		private string $label,
		private ?string $group = null,
	) {}

	/**
	 *
	 */
	#[\Override]
	public function getMetaData () : TaskMetaData
	{
		return new TaskMetaData($this->label, $this->group);
	}
}
