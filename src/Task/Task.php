<?php declare(strict_types=1);

namespace Torr\TaskManager\Task;

use Symfony\Component\Messenger\Envelope;

final class Task
{
	/**
	 */
	public function __construct (
		public readonly string $queueName,
		public readonly Envelope $envelope,
	) {}
}
