<?php declare(strict_types=1);

namespace Torr\TaskManager\Listener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\SendMessageToTransportsEvent;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Torr\TaskManager\Entity\TaskLog;
use Torr\TaskManager\Model\TaskLogModel;
use Torr\TaskManager\Task\Task;

final readonly class MessengerEventListener
{
	public function __construct (
		private TaskLogModel $logModel,
	) {}

	/**
	 *
	 */
	#[AsEventListener(SendMessageToTransportsEvent::class)]
	public function onSendMessageToTransports (SendMessageToTransportsEvent $event) : void
	{
		$taskLog = $this->getLogForEvent($event->getEnvelope());

		// make sure that the log entry is created and flushed
		if (null !== $taskLog)
		{
			$this->logModel->flush();
		}
	}

	/**
	 * Automatically integrate
	 */
	#[AsEventListener(WorkerMessageHandledEvent::class)]
	public function onWorkerMessageHandled (WorkerMessageHandledEvent $event) : void
	{
		$taskLog = $this->getLogForEvent($event->getEnvelope());
		$run = $taskLog?->getLastUnfinishedRun();

		if (null === $run)
		{
			return;
		}

		$run->abort(true, null);
		$this->logModel->flush();
	}

	#[AsEventListener(WorkerMessageFailedEvent::class)]
	public function onWorkerMessageFailed (WorkerMessageFailedEvent $event) : void
	{
		$taskLog = $this->getLogForEvent($event->getEnvelope());
		$run = $taskLog?->getLastUnfinishedRun();

		if (null === $run)
		{
			return;
		}

		$run->abort(false, $event->getThrowable()->getMessage());
		$this->logModel->flush();
	}

	/**
	 *
	 */
	private function getLogForEvent (Envelope $envelope) : ?TaskLog
	{
		$message = $envelope->getMessage();

		return $message instanceof Task
			? $this->logModel->getLogForTask($message)
			: null;
	}
}
