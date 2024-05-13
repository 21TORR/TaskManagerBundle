<?php declare(strict_types=1);

namespace Torr\TaskManager\Registry\Data;

final class Task
{
	/**
	 * @param string      $key   Unique key of this task
	 * @param string      $label The label for any UI
	 * @param object      $task  The message object to enqueue
	 * @param string|null $group An optional group to list this item in
	 */
	public function __construct (
		public readonly string $key,
		public readonly string $label,
		public readonly object $task,
		public readonly ?string $group,
	) {}
}
