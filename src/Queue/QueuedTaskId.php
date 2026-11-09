<?php declare(strict_types=1);

namespace Torr\TaskManager\Queue;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Torr\TaskManager\Exception\Queue\InvalidQueuedTaskIdException;

/**
 * Stable identifier of a queued task: the queue name + the id of the message in the transport.
 *
 * The string representation is safe to be used in URLs and forms (e.g. for a "remove" button), and can be parsed
 * again with `fromString()`.
 *
 * Retries are sent to the transport as new messages, so a retried task gets a new id.
 *
 * @api
 */
#[Exclude]
final readonly class QueuedTaskId implements \Stringable
{
	private const string SEPARATOR = ":";

	/**
	 */
	public function __construct (
		public string $queue,
		public string $transportMessageId,
	)
	{
		if ("" === $queue || "" === $transportMessageId)
		{
			throw new InvalidQueuedTaskIdException("Neither the queue nor the transport message id of a queued task id can be empty.");
		}
	}

	/**
	 * Parses the string representation (as returned by `toString()`)
	 *
	 * @throws InvalidQueuedTaskIdException
	 */
	public static function fromString (string $id) : self
	{
		$parts = explode(self::SEPARATOR, $id);

		if (2 !== \count($parts))
		{
			throw new InvalidQueuedTaskIdException(\sprintf("Invalid queued task id: '%s'", $id));
		}

		return new self(rawurldecode($parts[0]), rawurldecode($parts[1]));
	}

	/**
	 * Both parts are encoded, so that the separator can't be part of them.
	 */
	public function toString () : string
	{
		return rawurlencode($this->queue) . self::SEPARATOR . rawurlencode($this->transportMessageId);
	}

	/**
	 *
	 */
	#[\Override]
	public function __toString () : string
	{
		return $this->toString();
	}
}
