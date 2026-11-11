<?php declare(strict_types=1);

namespace Tests\Torr\TaskManager\Fixtures;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * A listable transport, that behaves like the Doctrine transport (string ids, `reject()` deletes the message).
 */
final class ListableTestTransport implements TransportInterface, ListableReceiverInterface, MessageCountAwareInterface
{
	/** @var array<int, Envelope> */
	private array $messages = [];
	private int $nextId = 1;

	/**
	 * @param list<object> $messages messages or envelopes
	 */
	public function __construct (array $messages = [])
	{
		foreach ($messages as $message)
		{
			$this->send(Envelope::wrap($message));
		}
	}

	/**
	 * Creates a transport with the given number of (dummy) messages
	 */
	public static function withMessages (int $count) : self
	{
		$messages = [];

		for ($i = 0; $i < $count; ++$i)
		{
			$messages[] = new \stdClass();
		}

		return new self($messages);
	}

	/**
	 *
	 */
	#[\Override]
	public function get () : iterable
	{
		return [];
	}

	/**
	 *
	 */
	#[\Override]
	public function ack (Envelope $envelope) : void
	{
		$this->reject($envelope);
	}

	/**
	 *
	 */
	#[\Override]
	public function reject (Envelope $envelope) : void
	{
		$key = $this->toKey($envelope->last(TransportMessageIdStamp::class)?->getId());

		if (null !== $key)
		{
			unset($this->messages[$key]);
		}
	}

	/**
	 *
	 */
	#[\Override]
	public function send (Envelope $envelope) : Envelope
	{
		$key = $this->nextId++;
		// like the Doctrine transport, the ids are numeric strings
		$envelope = $envelope->with(new TransportMessageIdStamp((string) $key));
		$this->messages[$key] = $envelope;

		return $envelope;
	}

	/**
	 *
	 */
	#[\Override]
	public function all (?int $limit = null) : iterable
	{
		return array_values(\array_slice($this->messages, 0, $limit));
	}

	/**
	 *
	 */
	#[\Override]
	public function find (mixed $id) : ?Envelope
	{
		$key = $this->toKey($id);

		return null !== $key
			? ($this->messages[$key] ?? null)
			: null;
	}

	/**
	 *
	 */
	#[\Override]
	public function getMessageCount () : int
	{
		return \count($this->messages);
	}

	/**
	 * Converts the (numeric string) transport message id to the internal key
	 */
	private function toKey (mixed $id) : ?int
	{
		return \is_string($id) && ctype_digit($id)
			? (int) $id
			: null;
	}
}
