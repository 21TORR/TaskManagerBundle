<?php declare(strict_types=1);

namespace Torr\TaskManager\Task;

use Symfony\Component\String\Slugger\AsciiSlugger;
use Torr\Snail\Snail\Snailer;
use Torr\TaskManager\Exception\Task\InvalidTaskDefinitionException;

use function Symfony\Component\String\u;

/**
 * A VO that describes a task
 */
final readonly class TaskMetaData
{
	/**
	 */
	public function __construct (
		public string $label,
		public ?string $group = null,
		public ?string $uniqueTaskId = null,
	)
	{
		if (null !== $this->uniqueTaskId && !Snailer::isValidSnail($this->uniqueTaskId))
		{
			throw new InvalidTaskDefinitionException(\sprintf(
				"Invalid unique task id: '%s'",
				$this->uniqueTaskId,
			));
		}
	}

	/**
	 * Returns a unique key for this task
	 */
	public function getKey () : string
	{
		// these are validated to be safe, so we can keep using these
		if (null !== $this->uniqueTaskId)
		{
			return $this->uniqueTaskId;
		}

		$slugger = new AsciiSlugger("en");
		$key = u($this->label)->lower()->toString();

		return $slugger->slug($key)->toString();
	}
}
