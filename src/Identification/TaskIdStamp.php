<?php declare(strict_types=1);

namespace Torr\TaskManager\Identification;

use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Uid\UuidV7;

/**
 * @final
 */
readonly class TaskIdStamp implements StampInterface
{
	public string $taskId;

	/**
	 * The task id is only passed when the stamp is deserialized (e.g. by a transport), so that it is kept.
	 * Otherwise a new task id is generated.
	 */
	public function __construct (?string $taskId = null)
	{
		$this->taskId = $taskId ?? new UuidV7()->toRfc4122();
	}
}
