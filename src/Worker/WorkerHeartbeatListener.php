<?php declare(strict_types=1);

namespace Torr\TaskManager\Worker;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\Event\WorkerStartedEvent;
use Symfony\Component\Messenger\Event\WorkerStoppedEvent;
use Symfony\Component\Uid\Uuid;
use Torr\TaskManager\Task\Task;

use function Symfony\Component\Clock\now;

/**
 * Reports the state of the worker running in this process to the `WorkerRegistry`.
 *
 * There is only a single worker per process, so the state is kept in this (shared) service.
 */
final class WorkerHeartbeatListener
{
	/**
	 * Minimum interval between two heartbeats of an idle worker
	 */
	private const int HEARTBEAT_INTERVAL_SECONDS = 15;
	private ?WorkerInfo $worker = null;
	private int $lastHeartbeat = 0;

	/**
	 */
	public function __construct (
		private readonly WorkerRegistry $registry,
		private readonly LoggerInterface $logger,
	) {}

	/**
	 */
	#[AsEventListener]
	public function onWorkerStarted (WorkerStartedEvent $event) : void
	{
		$metadata = $event->getWorker()->getMetadata();
		$now = now();
		$hostname = gethostname();
		$queues = $metadata->getQueueNames();

		$this->worker = new WorkerInfo(
			id: (string) Uuid::v7(),
			hostname: false !== $hostname ? $hostname : "unknown",
			pid: (int) getmypid(),
			transports: array_values($metadata->getTransportNames()),
			queues: null !== $queues ? array_values($queues) : null,
			startedAt: $now,
			lastSeenAt: $now,
			memoryUsage: memory_get_usage(true),
		);

		$this->persist();
	}

	/**
	 */
	#[AsEventListener]
	public function onMessageReceived (WorkerMessageReceivedEvent $event) : void
	{
		if (null === $this->worker)
		{
			return;
		}

		$this->update(
			isBusy: true,
			currentMessage: $this->getMessageLabel($event->getEnvelope()),
			currentTransport: $event->getReceiverName(),
			busySince: now(),
		);
		$this->persist();
	}

	/**
	 */
	#[AsEventListener]
	public function onMessageHandled (WorkerMessageHandledEvent $event) : void
	{
		if (null === $this->worker)
		{
			return;
		}

		// the heartbeat is sent in the directly following running event
		$this->update(handledMessages: $this->worker->handledMessages + 1);
	}

	/**
	 */
	#[AsEventListener]
	public function onMessageFailed (WorkerMessageFailedEvent $event) : void
	{
		if (null === $this->worker)
		{
			return;
		}

		// the heartbeat is sent in the directly following running event
		$this->update(failedMessages: $this->worker->failedMessages + 1);
	}

	/**
	 * Called after every loop iteration of the worker: either after a message was handled or after the worker was idle.
	 */
	#[AsEventListener]
	public function onWorkerRunning (WorkerRunningEvent $event) : void
	{
		if (null === $this->worker)
		{
			return;
		}

		if ($this->worker->isBusy)
		{
			$this->update(isBusy: false);
			$this->persist();

			return;
		}

		// throttle the heartbeats of idle workers
		if (time() - $this->lastHeartbeat >= self::HEARTBEAT_INTERVAL_SECONDS)
		{
			$this->persist();
		}
	}

	/**
	 */
	#[AsEventListener]
	public function onWorkerStopped (WorkerStoppedEvent $event) : void
	{
		if (null === $this->worker)
		{
			return;
		}

		try
		{
			$this->registry->remove($this->worker->id);
		}
		catch (\Throwable $exception)
		{
			$this->logError($exception);
		}

		$this->worker = null;
	}

	/**
	 * Replaces the current worker info with an updated copy. Passing `isBusy: false` clears the current message.
	 */
	private function update (
		?bool $isBusy = null,
		?string $currentMessage = null,
		?string $currentTransport = null,
		?\DateTimeImmutable $busySince = null,
		?int $handledMessages = null,
		?int $failedMessages = null,
	) : void
	{
		$worker = $this->worker;
		\assert(null !== $worker);

		$isBusy ??= $worker->isBusy;

		$this->worker = new WorkerInfo(
			id: $worker->id,
			hostname: $worker->hostname,
			pid: $worker->pid,
			transports: $worker->transports,
			queues: $worker->queues,
			startedAt: $worker->startedAt,
			lastSeenAt: $worker->lastSeenAt,
			isBusy: $isBusy,
			currentMessage: $isBusy ? ($currentMessage ?? $worker->currentMessage) : null,
			currentTransport: $isBusy ? ($currentTransport ?? $worker->currentTransport) : null,
			busySince: $isBusy ? ($busySince ?? $worker->busySince) : null,
			handledMessages: $handledMessages ?? $worker->handledMessages,
			failedMessages: $failedMessages ?? $worker->failedMessages,
			memoryUsage: $worker->memoryUsage,
		);
	}

	/**
	 * Writes the current state (with a fresh "last seen" time and memory usage) to the registry.
	 * Failing to write the heartbeat must never break the worker, so errors are only logged.
	 */
	private function persist () : void
	{
		$worker = $this->worker;

		if (null === $worker)
		{
			return;
		}

		$this->worker = $worker = new WorkerInfo(
			id: $worker->id,
			hostname: $worker->hostname,
			pid: $worker->pid,
			transports: $worker->transports,
			queues: $worker->queues,
			startedAt: $worker->startedAt,
			lastSeenAt: now(),
			isBusy: $worker->isBusy,
			currentMessage: $worker->currentMessage,
			currentTransport: $worker->currentTransport,
			busySince: $worker->busySince,
			handledMessages: $worker->handledMessages,
			failedMessages: $worker->failedMessages,
			memoryUsage: memory_get_usage(true),
		);
		$this->lastHeartbeat = time();

		try
		{
			$this->registry->save($worker);
		}
		catch (\Throwable $exception)
		{
			$this->logError($exception);
		}
	}

	/**
	 */
	private function getMessageLabel (Envelope $envelope) : string
	{
		$message = $envelope->getMessage();

		if ($message instanceof Task)
		{
			return $message->getMetaData()->label;
		}

		return $message::class;
	}

	/**
	 */
	private function logError (\Throwable $exception) : void
	{
		$this->logger->error("Failed to update the worker registry: {message}", [
			"message" => $exception->getMessage(),
			"exception" => $exception,
		]);
	}
}
