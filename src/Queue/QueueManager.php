<?php declare(strict_types=1);

namespace Torr\TaskManager\Queue;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Torr\TaskManager\Exception\Queue\InvalidQueuedTaskIdException;
use Torr\TaskManager\Exception\Queue\QueuedTaskAlreadyStartedException;
use Torr\TaskManager\Exception\Queue\QueuedTaskNotFoundException;
use Torr\TaskManager\Exception\Queue\UnknownQueueException;
use Torr\TaskManager\Exception\Queue\UnsupportedQueueOperationException;
use Torr\TaskManager\Identification\TaskIdStamp;
use Torr\TaskManager\Model\TaskLogModel;
use Torr\TaskManager\Task\Task;
use Torr\TaskManager\Transport\TransportsHelper;

/**
 * Lists the tasks waiting in the queues and removes single queued tasks.
 *
 * Listing and removing single tasks is supported for transports implementing `ListableReceiverInterface`
 * (e.g. Doctrine and Redis) and the in-memory transport. For all other transports (AMQP, Beanstalkd, SQS, …) only the number of queued tasks
 * is available. Sync transports are no queues, as their tasks are handled directly.
 *
 * Delayed messages (e.g. retries waiting for their delay) are not listed by the Doctrine and Redis transports.
 *
 * @api
 */
final readonly class QueueManager
{
	public const int DEFAULT_LIMIT = 100;

	/**
	 */
	public function __construct (
		private TransportsHelper $transportsHelper,
		private TaskLogModel $logModel,
	) {}

	/**
	 * Returns the queued tasks of all queues, ordered by queue priority.
	 *
	 * @param int $limit the max number of tasks listed per queue
	 *
	 * @return list<QueueListing>
	 */
	public function getQueuedTasks (int $limit = self::DEFAULT_LIMIT, bool $includeFailureQueues = false) : array
	{
		[$queues] = $this->transportsHelper->getQueues();
		$listings = [];

		foreach ($queues as $queueName => $receiver)
		{
			if (!$includeFailureQueues && $this->transportsHelper->isFailureQueue($queueName))
			{
				continue;
			}

			$listings[] = $this->createListing($queueName, $receiver, $limit);
		}

		return $listings;
	}

	/**
	 * Returns the queued tasks of the given queue.
	 *
	 * @param int $limit the max number of tasks listed
	 *
	 * @throws UnknownQueueException
	 */
	public function getQueuedTasksOfQueue (string $queue, int $limit = self::DEFAULT_LIMIT) : QueueListing
	{
		return $this->createListing($queue, $this->getReceiver($queue), $limit);
	}

	/**
	 * Finds a single queued task.
	 *
	 * @throws InvalidQueuedTaskIdException
	 * @throws UnknownQueueException
	 * @throws UnsupportedQueueOperationException
	 */
	public function findQueuedTask (QueuedTaskId|string $id) : ?QueuedTask
	{
		$id = \is_string($id) ? QueuedTaskId::fromString($id) : $id;
		$receiver = $this->getReceiver($id->queue);
		$envelope = $this->findEnvelope($receiver, $id);

		return null !== $envelope
			? $this->createQueuedTasks($id->queue, [$envelope])[0]
			: null;
	}

	/**
	 * Removes a queued task, that wasn't started yet.
	 *
	 * Removing a task while a worker fetches it is a race, that can't be fully prevented with the messenger transports:
	 * a worker that just fetched the task will still handle it.
	 *
	 * @return QueuedTask the removed task
	 *
	 * @throws InvalidQueuedTaskIdException
	 * @throws UnknownQueueException
	 * @throws UnsupportedQueueOperationException
	 * @throws QueuedTaskNotFoundException
	 * @throws QueuedTaskAlreadyStartedException
	 */
	public function removeQueuedTask (QueuedTaskId|string $id) : QueuedTask
	{
		$id = \is_string($id) ? QueuedTaskId::fromString($id) : $id;
		$receiver = $this->getReceiver($id->queue);
		$envelope = $this->findEnvelope($receiver, $id);

		if (null === $envelope)
		{
			throw new QueuedTaskNotFoundException(\sprintf("Queued task '%s' not found.", $id));
		}

		$task = $this->createQueuedTasks($id->queue, [$envelope])[0];

		if ($task->isStarted)
		{
			throw new QueuedTaskAlreadyStartedException(\sprintf("Queued task '%s' was already started.", $id));
		}

		$receiver->reject($envelope);

		return $task;
	}

	/**
	 * @throws UnknownQueueException
	 */
	private function getReceiver (string $queue) : ReceiverInterface
	{
		[$queues, $skipped] = $this->transportsHelper->getQueues();

		if (isset($queues[$queue]))
		{
			return $queues[$queue];
		}

		throw new UnknownQueueException(
			isset($skipped[$queue])
				? \sprintf("'%s' is no queue: %s.", $queue, $skipped[$queue])
				: \sprintf("Unknown queue '%s'.", $queue),
		);
	}

	/**
	 */
	private function createListing (string $queue, ReceiverInterface $receiver, int $limit) : QueueListing
	{
		$limit = max(0, $limit);
		$totalCount = $receiver instanceof MessageCountAwareInterface
			? $receiver->getMessageCount()
			: null;
		$supportsListing = $receiver instanceof ListableReceiverInterface || $receiver instanceof InMemoryTransport;
		$envelopes = [];

		if ($supportsListing && $limit > 0 && 0 !== $totalCount)
		{
			// fetch one more, to know whether there are more tasks
			$envelopes = $this->listEnvelopes($receiver, $limit + 1);
		}

		$hasMore = $limit > 0
			? \count($envelopes) > $limit
			: 0 !== $totalCount;

		return new QueueListing(
			queue: $queue,
			isFailureQueue: $this->transportsHelper->isFailureQueue($queue),
			supportsListing: $supportsListing,
			totalCount: $totalCount,
			tasks: $this->createQueuedTasks($queue, \array_slice($envelopes, 0, $limit)),
			hasMore: $hasMore,
		);
	}

	/**
	 * @return list<Envelope>
	 */
	private function listEnvelopes (ReceiverInterface $receiver, int $limit) : array
	{
		// the in-memory transport returns the queued messages without consuming them
		$envelopes = $receiver instanceof InMemoryTransport
			? $receiver->get($limit)
			: ($receiver instanceof ListableReceiverInterface ? $receiver->all($limit) : []);

		return iterator_to_array($envelopes, false);
	}

	/**
	 * @throws UnsupportedQueueOperationException
	 */
	private function findEnvelope (ReceiverInterface $receiver, QueuedTaskId $id) : ?Envelope
	{
		if ($receiver instanceof ListableReceiverInterface)
		{
			$envelope = $receiver->find($id->transportMessageId);

			// some databases cast malformed ids (e.g. "12abc" to 12) in the Doctrine transport, so make sure that the
			// found message is really the requested one
			return null !== $envelope && $this->getTransportMessageId($envelope) === $id->transportMessageId
				? $envelope
				: null;
		}

		if ($receiver instanceof InMemoryTransport)
		{
			foreach ($receiver->get(\PHP_INT_MAX) as $envelope)
			{
				if ($this->getTransportMessageId($envelope) === $id->transportMessageId)
				{
					return $envelope;
				}
			}

			return null;
		}

		throw new UnsupportedQueueOperationException(\sprintf(
			"The transport of queue '%s' doesn't support finding single tasks.",
			$id->queue,
		));
	}

	/**
	 * @param list<Envelope> $envelopes
	 *
	 * @return list<QueuedTask>
	 */
	private function createQueuedTasks (string $queue, array $envelopes) : array
	{
		$taskIds = [];

		foreach ($envelopes as $envelope)
		{
			$taskId = $envelope->last(TaskIdStamp::class)?->taskId;

			if (null !== $taskId)
			{
				$taskIds[] = $taskId;
			}
		}

		$startedTaskIds = array_flip($this->logModel->getStartedTaskIds(array_values(array_unique($taskIds))));
		$tasks = [];

		foreach ($envelopes as $envelope)
		{
			$message = $envelope->getMessage();
			$transportMessageId = $this->getTransportMessageId($envelope);
			$taskId = $envelope->last(TaskIdStamp::class)?->taskId;
			$metaData = $message instanceof Task ? $message->getMetaData() : null;

			$tasks[] = new QueuedTask(
				id: null !== $transportMessageId && "" !== $transportMessageId
					? new QueuedTaskId($queue, $transportMessageId)
					: null,
				queue: $queue,
				envelope: $envelope,
				taskId: $taskId,
				label: $metaData->label ?? get_debug_type($message),
				group: $metaData?->group,
				retryCount: $envelope->last(RedeliveryStamp::class)?->getRetryCount() ?? 0,
				isStarted: null !== $taskId && isset($startedTaskIds[$taskId]),
			);
		}

		return $tasks;
	}

	/**
	 */
	private function getTransportMessageId (Envelope $envelope) : ?string
	{
		$id = $envelope->last(TransportMessageIdStamp::class)?->getId();

		return \is_scalar($id)
			? (string) $id
			: null;
	}
}
