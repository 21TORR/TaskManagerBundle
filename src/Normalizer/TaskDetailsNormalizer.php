<?php declare(strict_types=1);

namespace Torr\TaskManager\Normalizer;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Torr\TaskManager\Entity\TaskLog;
use Torr\TaskManager\Task\Task;

/**
 * @phpstan-import-type TaskDetails from TaskLog
 */
final readonly class TaskDetailsNormalizer
{
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
			$details["task"] = serialize($task);
		}

		return $details;
	}
}
