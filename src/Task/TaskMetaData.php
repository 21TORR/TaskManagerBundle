<?php declare(strict_types=1);

namespace Torr\TaskManager\Task;

use Symfony\Component\String\Slugger\AsciiSlugger;
use function Symfony\Component\String\u;

/**
 * A VO that describes a task
 */
final class TaskMetaData
{
	/**
	 */
	public function __construct (
		public readonly string $label,
		public readonly ?string $group = null,
		public readonly ?string $uniqueTaskId = null,
	) {}


	/**
	 * Returns a unique key for this task
	 */
	public function getKey () : string
	{
		$slugger = new AsciiSlugger("en");
		$key = u($this->label)->lower()->toString();
		return $slugger->slug($key)->toString();
	}
}
