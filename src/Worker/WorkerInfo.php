<?php declare(strict_types=1);

namespace Torr\TaskManager\Worker;

/**
 * Snapshot of a running messenger worker, as reported by its last heartbeat.
 */
final readonly class WorkerInfo
{
	/**
	 */
	public function __construct (
		public string $id,
		public string $hostname,
		public int $pid,
		/**
		 * The transports this worker consumes, in the order they are consumed.
		 *
		 * @var list<string>
		 */
		public array $transports,
		/**
		 * The queue names this worker is restricted to (via `--queues`), if any.
		 *
		 * @var list<string>|null
		 */
		public ?array $queues,
		public \DateTimeImmutable $startedAt,
		public \DateTimeImmutable $lastSeenAt,
		/**
		 * Whether the worker is currently handling a message
		 */
		public bool $isBusy = false,
		/**
		 * The label of the message that is currently being handled
		 */
		public ?string $currentMessage = null,
		/**
		 * The transport the current message was received from
		 */
		public ?string $currentTransport = null,
		/**
		 * The time the worker started handling the current message
		 */
		public ?\DateTimeImmutable $busySince = null,
		public int $handledMessages = 0,
		public int $failedMessages = 0,
		/**
		 * The memory (in bytes) the worker process had allocated at the last heartbeat
		 */
		public int $memoryUsage = 0,
	) {}
}
