<?php declare(strict_types=1);

namespace Torr\TaskManager\Transport;

use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Transport\Sync\SyncTransport;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Torr\TaskManager\Config\BundleConfig;
use Torr\TaskManager\Exception\Transport\InvalidMessageTransportException;

/**
 * Helper to interact with transports of the Symfony messenger component.
 *
 * @internal
 */
final readonly class TransportsHelper
{
	/**
	 */
	public function __construct (
		/** @var ServiceLocator<TransportInterface> */
		private ServiceLocator $transports,
		private BundleConfig $bundleConfig,
	) {}

	/**
	 * Returns whether the app uses sync transports
	 */
	public function hasSyncTransport () : bool
	{
		foreach ($this->getAllTransports() as $transport)
		{
			if ($transport instanceof SyncTransport)
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * @return array<string, TransportInterface>
	 */
	public function getAllTransports () : array
	{
		$transports = [];

		foreach (array_keys($this->transports->getProvidedServices()) as $key)
		{
			// the container contains every service twice: once with the name of the transport and once with
			// name "messenger.transport.$name". We only use the ones without the prefix.
			if (str_starts_with($key, "messenger.transport."))
			{
				continue;
			}

			$transports[$key] = $this->transports->get($key);
		}

		return $transports;
	}

	public function getTransport (string $queueName) : TransportInterface
	{
		try
		{
			return $this->transports->get($queueName);
		}
		catch (ServiceNotFoundException $exception)
		{
			throw new InvalidMessageTransportException(
				message: sprintf(
					"No transport found with queue name '%s'",
					$queueName,
				),
				previous: $exception,
			);
		}
	}

	/**
	 * Returns all registered transport keys
	 */
	private function getAllRegisteredQueueNames () : array
	{
		$registered = [];

		foreach (array_keys($this->transports->getProvidedServices()) as $queueName)
		{
			// The container contains every service twice: once with the queue name and once with
			// name "messenger.transport.$name". We only use the ones without the prefix.
			if (str_starts_with($queueName, "messenger.transport."))
			{
				continue;
			}

			$registered[$queueName] = $queueName;
		}

		return $registered;
	}

	/**
	 * @return string[]
	 */
	public function getOrderedQueueNames () : array
	{
		$indexMap = [];

		foreach ($this->bundleConfig->sortedQueues as $queueName)
		{
			$indexMap[$queueName] = \count($indexMap);
		}

		$ordered = $this->getAllRegisteredQueueNames();
		\usort(
			$ordered,
			function (string $queueNameLeft, string $queueNameRight) use ($indexMap) : int
			{
				$indexLeft = $indexMap[$queueNameLeft] ?? null;
				$indexRight = $indexMap[$queueNameRight] ?? null;
				$leftIsScheduler = \str_starts_with($queueNameLeft, "scheduler_");
				$rightIsScheduler = \str_starts_with($queueNameRight, "scheduler_");

				// if left is a schedule, then sort to top except if right is also schedule.
				// If both are schedules, keep the order
				if ($leftIsScheduler)
				{
					return $rightIsScheduler
						? 0
						: -1;
				}

				// left is no schedule, so if right is one, sort it to the top
				if ($rightIsScheduler)
				{
					return 1;
				}

				// if left is indexed, check if right is indexed as well. If not put left at the top,
				// otherwise sort according to index
				if (null !== $indexLeft)
				{
					return null !== $indexRight
						? $indexLeft - $indexRight
						: -1;
				}

				// if right is indexed, move to top, otherwise keep order
				return null !== $indexRight
					? 1
					: 0;
			},
		);

		return $ordered;
	}
}
