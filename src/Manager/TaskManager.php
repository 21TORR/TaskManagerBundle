<?php declare(strict_types=1);

namespace Torr\TaskManager\Manager;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Sync\SyncTransport;
use Symfony\Component\Scheduler\Messenger\SchedulerTransport;
use Torr\TaskManager\Exception\Transport\InvalidMessageTransportException;
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
	 * @return bool whether the message was added. If this is false, an identical job is already queued.
	 *
	 * @api
	 */
	public function enqueue (Task $task, array $stamps = []) : bool
	{
		// if we find a message with the same unique task id, we don't queue it again
		if ($this->isTaskWithSameTaskIdAlreadyQueued($task->getMetaData()->uniqueTaskId))
		{
			return false;
		}

		$envelope = new Envelope($task);
		$envelope->with(...$stamps);

		$this->messageBus->dispatch($envelope);

		return true;
	}

	/**
	 * Finds a queued message with the given job id
	 */
	private function isTaskWithSameTaskIdAlreadyQueued (?string $uniqueTaskId) : bool
	{
		// no task id, so this task is not deduplicated. No need to check anything, just enqueue it.
		if (null === $uniqueTaskId)
		{
			return false;
		}

		foreach ($this->transportsHelper->getOrderedQueueNames() as $queueName)
		{
			foreach ($this->fetchTasksInQueue($queueName) as $envelope)
			{
				$message = $envelope->getMessage();

				if ($message instanceof Task && $message->getMetaData()->uniqueTaskId === $uniqueTaskId)
				{
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Fetches all tasks for the given priority
	 *
	 * @return iterable<Envelope>
	 *
	 * @api
	 */
	public function fetchTasksInQueue (string $queueName) : iterable
	{
		$receiver = $this->transportsHelper->getTransport($queueName);

		// ignore schedulers
		if ($receiver instanceof SchedulerTransport)
		{
			return [];
		}

		// skip, as sync transports can't queue messages like regular transports
		if ($receiver instanceof SyncTransport)
		{
			return [];
		}

		if (!$receiver instanceof ListableReceiverInterface)
		{
			throw new InvalidMessageTransportException(sprintf(
				"Transport for queue '%s' must implement ListableReceiverInterface",
				$queueName,
			));
		}

		return $receiver->all();
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
