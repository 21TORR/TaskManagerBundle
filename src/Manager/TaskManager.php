<?php declare(strict_types=1);

namespace Torr\TaskManager\Manager;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DeduplicateStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Torr\TaskManager\Task\Task;
use Torr\TaskManager\Transport\TransportsHelper;

final readonly class TaskManager
{
	/**
	 */
	public function __construct (
		private TransportsHelper $transportsHelper,
		private MessageBusInterface $messageBus,
	) {}

	/**
	 * Enqueues a task. You can give the job a unique id, so that only a single task with this id can be enqueued at the same time.
	 *
	 * @param StampInterface[] $stamps
	 *
	 * @return string The uuid of the task
	 *
	 * @api
	 */
	public function enqueue (Task $task, array $stamps = []) : string
	{
		$uniqueTaskId = $task->getMetaData()->uniqueTaskId;

		if (null !== $uniqueTaskId)
		{
			$stamps[] = new DeduplicateStamp($uniqueTaskId);
		}

		$this->messageBus->dispatch(
			new Envelope($task, $stamps),
		);

		/** @phpstan-ignore-next-line property.deprecated (The uuid integration will be refactored in v4) */
		return $task->ulid;
	}

	/**
	 * Returns the queue names, ordered by descending priority.
	 *
	 * @return string[]
	 *
	 * @api
	 */
	public function getAllQueueNames () : array
	{
		return $this->transportsHelper->getOrderedQueueNames();
	}

	/**
	 * Returns the system has any sync transport enabled.
	 * So you need to assume that any task might be worked on synchronously.
	 *
	 * @api
	 */
	public function hasSyncTransport () : bool
	{
		return $this->transportsHelper->hasSyncTransport();
	}
}
