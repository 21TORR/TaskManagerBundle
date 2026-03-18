<?php declare(strict_types=1);

namespace Torr\TaskManager\Normalizer;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerException;
use Symfony\Component\Serializer\SerializerInterface;
use Torr\TaskManager\Entity\TaskLog;
use Torr\TaskManager\Task\Task;

/**
 * @phpstan-import-type TaskDetails from TaskLog
 */
final readonly class TaskDetailsNormalizer
{
	public function __construct (
		private SerializerInterface $serializer,
		private LoggerInterface $logger,
	) {}

	/**
	 * @return TaskDetails
	 */
	public function normalizeTaskDetails (Envelope $envelope) : array
	{
		$task = $envelope->getMessage();

		/** @phpstan-var TaskDetails $details */
		$details = [
			"transport" => $envelope->last(ReceivedStamp::class)?->getTransportName(),
			"handledBy" => $envelope->last(HandledStamp::class)?->getHandlerName(),
		];

		if ($task instanceof Task)
		{
			$details["label"] = $task->getMetaData()->label;
			$details["task"] = $this->serializer->serialize($task, "json");
		}

		return $details;
	}

	/**
	 * Deserializes the task object stored in the given log entry.
	 */
	public function deserializeTask (TaskLog $log) : ?Task
	{
		$serialized = $log->getTaskDetails()["task"] ?? null;

		if (!\is_string($serialized))
		{
			return null;
		}

		try
		{
			$task = $this->serializer->deserialize($serialized, $log->taskClass, "json");

			return $task instanceof Task ? $task : null;
		}
		catch (SerializerException $exception)
		{
			$this->logger->error("Failed to deserialize task of class '{taskClass}' from log entry #{logId}: {message}", [
				"taskClass" => $log->taskClass,
				"logId" => $log->id,
				"message" => $exception->getMessage(),
				"exception" => $exception,
			]);

			return null;
		}
	}
}
