<?php declare(strict_types=1);

namespace Torr\TaskManager\Event;

use Symfony\Component\String\Slugger\AsciiSlugger;
use function Symfony\Component\String\u;
use Torr\TaskManager\Exception\Registry\DuplicateTaskRegisteredException;
use Torr\TaskManager\Registry\Data\Task;

/**
 * This event lets you register your tasks, so that the UI can make them selectable
 * and runnable.
 *
 * You can always register tasks yourself, this event is the integration into the automated
 * tools of this bundle.
 */
final class RegisterTasksEvent
{
	/** @var array<string, Task> */
	public array $tasks = [];
	private readonly AsciiSlugger $slugger;

	/**
	 */
	public function __construct ()
	{
		$this->slugger = new AsciiSlugger("en");
	}


	/**
	 * Registers a task
	 *
	 * @throws DuplicateTaskRegisteredException
	 */
	public function registerTask (
		string $label,
		object $message,
		?string $group = null,
	) : self
	{
		$key = u($label)->lower()->toString();
		$key = $this->slugger->slug($key)->toString();

		if (\array_key_exists($key, $this->tasks))
		{
			throw new DuplicateTaskRegisteredException(\sprintf(
				"Duplicate task registered with key '%s'",
				$key,
			));
		}

		$this->tasks[$key] = new Task($key, $label, $message, $group);

		return $this;
	}


	/**
	 * @return Task[]
	 */
	public function getTasks () : array
	{
		$entries = \array_values($this->tasks);

		\usort(
			$entries,
			static fn (Task $left, Task $right) => \strnatcasecmp($left->label, $right->label),
		);

		return $entries;
	}
}
