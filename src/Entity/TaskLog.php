<?php declare(strict_types=1);

namespace Torr\TaskManager\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Psr\Log\LoggerInterface;
use Torr\TaskManager\Exception\Log\InvalidLogActionException;
use Torr\TaskManager\Task\Task;

use function Symfony\Component\Clock\now;

/**
 * @phpstan-type TaskDetails array{
 *     "handledBy"?: string|null,
 *     "label"?: string,
 *     "task"?: string,
 *     "transport"?: string|null,
 * }
 *
 * @final
 */
#[ORM\Entity]
#[ORM\Table(name: "task_manager_tasks")]
#[ORM\Index(name: "idx_task_manager_tasks_time_queued", fields: ["timeQueued"])]
#[ORM\Index(name: "idx_task_manager_task_id", fields: ["taskId"])]
class TaskLog
{
	/**
	 */
	#[ORM\Id]
	#[ORM\GeneratedValue(strategy: "AUTO")]
	#[ORM\Column(name: "id", type: Types::INTEGER)]
	public private(set) ?int $id = null;

	/**
	 * UUIDv7s have only 36 characters, but just to be sure we use 50 characters
	 */
	#[ORM\Column(type: Types::STRING, length: 50, unique: true)]
	public private(set) string $taskId;

	/**
	 *
	 */
	#[ORM\Column(type: Types::STRING, length: 1000)]
	public string $taskClass;

	/**
	 * The encoded task details
	 *
	 * @var TaskDetails
	 */
	#[ORM\Column(type: Types::JSON)]
	private array $taskDetails = [];

	/**
	 *
	 */
	#[ORM\Column(name: "time_queued", type: Types::DATETIMETZ_IMMUTABLE)]
	public private(set) \DateTimeImmutable $timeQueued;

	/** @var Collection<int, TaskRun> */
	#[ORM\OneToMany(mappedBy: "taskLog", targetEntity: TaskRun::class, cascade: ["remove"], orphanRemoval: true)]
	#[ORM\OrderBy(["timeStarted" => "asc"])]
	public private(set) Collection $runs;

	/**
	 */
	public function __construct (
		object $task,
		string $uuid,
	)
	{
		$this->taskClass = $task::class;
		$this->taskId = $uuid;
		$this->runs = new ArrayCollection();
		$this->timeQueued = now();
	}

	/**
	 * @deprecated use the property directly instead
	 */
	public function getId () : ?int
	{
		return $this->id;
	}

	/**
	 * @deprecated use the property directly instead
	 */
	public function getTaskId () : string
	{
		return $this->taskId;
	}

	/**
	 * @deprecated use the property directly instead
	 */
	public function getTimeQueued () : \DateTimeImmutable
	{
		return $this->timeQueued;
	}

	/**
	 * @return Collection<int, TaskRun>
	 *
	 * @deprecated use the property directly instead
	 */
	public function getRuns () : Collection
	{
		return $this->runs;
	}

	/**
	 * Returns whether the task was finished successfully
	 */
	public function isSuccess () : bool
	{
		foreach ($this->runs as $run)
		{
			if ($run->success)
			{
				return true;
			}
		}

		return false;
	}

	/**
	 */
	public function getLastUnfinishedRun () : ?TaskRun
	{
		foreach ($this->runs as $run)
		{
			if (!$run->isFinished)
			{
				return $run;
			}
		}

		return null;
	}

	/**
	 * Returns whether all runs for this task are finished
	 */
	public function isFinished () : bool
	{
		return null === $this->getLastUnfinishedRun();
	}

	/**
	 *
	 */
	public function createRun (?LoggerInterface $logger = null) : TaskRun
	{
		if ($this->isSuccess())
		{
			throw new InvalidLogActionException(\sprintf(
				"Can't start a run for a task #%s (task id: '%s') that is already finished.",
				$this->id,
				$this->taskId,
			));
		}

		$run = new TaskRun($this, $logger);
		$this->runs->add($run);

		return $run;
	}

	/**
	 * @return TaskDetails
	 */
	public function getTaskDetails () : array
	{
		return $this->taskDetails;
	}

	/**
	 * @param TaskDetails $taskDetails
	 */
	public function setTaskDetails (array $taskDetails) : void
	{
		$this->taskDetails = $taskDetails;
	}

	/**
	 * Returns a label of the task
	 */
	public function getTaskLabel () : ?string
	{
		return $this->getTaskDetails()["label"] ?? null;
	}

	/**
	 * @deprecated Use TaskDetailsNormalizer::deserializeTask() instead. Will be removed in 4.0.
	 *
	 * @todo Remove in 4.0.
	 */
	public function getTaskObject () : null
	{
		// @phpstan-ignore-next-line todoBy.sfDeprecation
		trigger_deprecation("21torr/task-manager", "3.2.5", "TaskLog::getTaskObject() is deprecated, use TaskDetailsNormalizer::deserializeTask() instead.");

		return null;
	}

	/**
	 * @return bool|null whether the task succeeded/failed or null, if it hasn't run yet
	 */
	public function getStatus () : ?bool
	{
		$result = null;

		foreach ($this->runs as $run)
		{
			if (!$run->isFinished)
			{
				continue;
			}

			if ($run->success)
			{
				return true;
			}

			// we have a run that is finished and if we don't early exit, they apparently failed
			$result = false;
		}

		return $result;
	}

	/**
	 * Returns the total duration for all runs
	 */
	public function getTotalDuration () : float
	{
		$duration = 0;

		foreach ($this->runs as $run)
		{
			$duration += (float) $run->duration;
		}

		return $duration;
	}

	/**
	 * Returns the message handler that handled the message
	 */
	public function getHandledBy () : ?string
	{
		return $this->getTaskDetails()["handledBy"] ?? null;
	}

	/**
	 * Returns the transport this message was handled on
	 */
	public function getTransport () : ?string
	{
		return $this->getTaskDetails()["transport"] ?? null;
	}

	/**
	 * Returns the class of the message
	 *
	 * @deprecated use the property directly instead
	 */
	public function getTaskClass () : string
	{
		return $this->taskClass;
	}
}
