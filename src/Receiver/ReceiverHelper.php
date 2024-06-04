<?php declare(strict_types=1);

namespace Torr\TaskManager\Receiver;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Messenger\Transport\Sync\SyncTransport;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * @internal
 */
final class ReceiverHelper
{
	/**
	 */
	public function __construct (
		/** @var iterable<TransportInterface> */
		#[AutowireIterator(tag: "messenger.receiver")]
		private readonly iterable $transports,
	) {}

	/**
	 * Returns whether the app uses sync transports
	 */
	public function hasSyncTransport () : bool
	{
		foreach ($this->transports as $transport)
		{
			if ($transport instanceof SyncTransport)
			{
				return true;
			}
		}

		return false;
	}
}
