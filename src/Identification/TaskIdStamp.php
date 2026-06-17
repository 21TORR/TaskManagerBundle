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
	 */
	public function __construct ()
	{
		$this->taskId = new UuidV7()->toRfc4122();
	}
}
