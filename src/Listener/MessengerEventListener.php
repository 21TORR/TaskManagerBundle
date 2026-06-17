<?php declare(strict_types=1);

namespace Torr\TaskManager\Listener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Torr\TaskManager\Entity\TaskLog;
use Torr\TaskManager\Identification\TaskIdStamp;
use Torr\TaskManager\Model\TaskLogModel;
use Torr\TaskManager\Task\TaskManagerInternalTask;

/**
 * Integrates into the Symfony messenger event to automate certain integrations
 */
final readonly class MessengerEventListener
{
	public function __construct (
		private TaskLogModel $logModel,
	) {}

	/**
	 * Automatically integrate
	 */
	#[AsEventListener]
	public function onWorkerMessageHandled (WorkerMessageHandledEvent $event) : void
	{
		$envelope = $event->getEnvelope();
		$taskLog = $this->getLogForEvent($envelope);

		if (null === $taskLog)
		{
			return;
		}

		// abort run as "success". It wasn't marked as finished manually, but it succeeded nonetheless.
		$run = $taskLog->getLastUnfinishedRun();
		$run?->abort(true);

		$this->logModel->flush();
	}

	#[AsEventListener]
	public function onWorkerMessageFailed (WorkerMessageFailedEvent $event) : void
	{
		$envelope = $event->getEnvelope();
		$taskLog = $this->getLogForEvent($envelope);

		if (null === $taskLog)
		{
			return;
		}

		// abort run as "failure". It wasn't marked as finished manually, and it failed.
		$run = $taskLog->getLastUnfinishedRun();
		$run?->abort(false, $event->getThrowable()->getMessage());

		$this->logModel->flush();
	}

	/**
	 *
	 */
	private function getLogForEvent (Envelope $envelope) : ?TaskLog
	{
		$uuid = $envelope->last(TaskIdStamp::class)?->taskId;
		$message = $envelope->getMessage();

		// filter out task manager internal tasks
		return null !== $uuid && !$message instanceof TaskManagerInternalTask
			? $this->logModel->getLogForUuid($message, $uuid)
			: null;
	}
}
