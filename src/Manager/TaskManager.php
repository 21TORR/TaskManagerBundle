<?php declare(strict_types=1);

namespace Torr\TaskManager\Manager;

use Psr\Container\ContainerExceptionInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Sync\SyncTransport;
use Torr\TaskManager\Config\BundleConfig;
use Torr\TaskManager\Exception\Manager\InvalidMessageTransportException;
use Torr\TaskManager\Stamp\UniqueJobStamp;

final class TaskManager
{
	public function __construct (
		private readonly ServiceLocator $receivers,
		private readonly MessageBusInterface $messageBus,
		private readonly BundleConfig $bundleConfig,
	) {}


	/**
	 * Enqueues a task. You can give the job a unique id, so that only a single task with this id can be enqueued at the same time.
	 *
	 * @return bool whether the message was added. If this is false, an identical job is already queued.
	 */
	public function enqueue (
		object $message,
		?string $jobId = null,
	) : bool
	{
		if (null === $jobId)
		{
			$this->messageBus->dispatch($message);
			return true;
		}

		if (null !== $this->findQueuedMessageByUniqueJobId($jobId))
		{
			return false;
		}

		$envelope = $message instanceof Envelope
			? $message
			: new Envelope($message);

		$envelope = $envelope->with(new UniqueJobStamp($jobId));
		$this->messageBus->dispatch($envelope);
		return true;

	}


	/**
	 * Finds a queued message with the given job id
	 */
	private function findQueuedMessageByUniqueJobId (string $jobId) : ?Envelope
	{
		foreach ($this->getAllQueues() as $queueName)
		{
			foreach ($this->fetchTasksInQueue($queueName) as $envelope)
			{
				$stamp = $envelope->last(UniqueJobStamp::class);

				if (null !== $stamp && $stamp->jobId === $jobId)
				{
					return $envelope;
				}
			}
		}

		return null;
	}


	/**
	 * Fetches all tasks for the given priority
	 *
	 * @return iterable<Envelope>
	 */
	public function fetchTasksInQueue (string $queueName) : iterable
	{
		try
		{
			$receiver = $this->receivers->get($queueName);

			// skip, as sync transports can't queue messages like regular transports
			if ($receiver instanceof SyncTransport)
			{
				return [];
			}

			if (!$receiver instanceof ListableReceiverInterface)
			{
				throw new InvalidMessageTransportException(\sprintf(
					"Transport for queue '%s' must implement ListableReceiverInterface",
					$queueName,
				));
			}

			return $receiver->all();
		}
		catch (ContainerExceptionInterface $exception)
		{
			throw new InvalidMessageTransportException(\sprintf(
				"Could not fetch transport: %s",
				$exception->getMessage(),
			), previous: $exception);
		}
	}


	/**
	 * Returns all queues
	 *
	 * @return string[]
	 */
	public function getAllQueues () : array
	{
		if (!empty($this->bundleConfig->sortedQueues))
		{
			return $this->bundleConfig->sortedQueues;
		}

		return \array_filter(
			\array_keys($this->receivers->getProvidedServices()),
			fn (string $serviceId) => !\str_starts_with($serviceId, "messenger.transport.") && !\in_array($serviceId, $this->bundleConfig->failureTransports, true),
		);
	}
}
