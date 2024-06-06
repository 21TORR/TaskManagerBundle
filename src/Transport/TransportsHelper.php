<?php declare(strict_types=1);

namespace Torr\TaskManager\Transport;

use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Transport\Sync\SyncTransport;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Torr\TaskManager\Config\BundleConfig;
use Torr\TaskManager\Exception\Transport\InvalidMessageTransportException;

/**
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
		$registeredQueueNames = $this->getAllRegisteredQueueNames();
		$ordered = [];

		foreach ($this->bundleConfig->sortedQueues as $queueName)
		{
			if (\array_key_exists($queueName, $registeredQueueNames))
			{
				$ordered[] = $queueName;
			}
		}

		foreach ($registeredQueueNames as $queueName)
		{
			if (
				!\in_array($queueName, $ordered, true)
				&& !\in_array($queueName, $this->bundleConfig->failureTransports, true)
			)
			{
				$ordered[] = $queueName;
			}
		}

		return $ordered;
	}
}
