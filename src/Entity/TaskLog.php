<?php declare(strict_types=1);

namespace Torr\TaskManager\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Torr\TaskManager\Exception\Log\InvalidLogActionException;
use Torr\TaskManager\Task\Task;

use function Symfony\Component\Clock\now;

/**
 * @phpstan-type TaskDetails array{
 *     "class"?: class-string|null,
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
class TaskLog
{
	/**
	 */
	#[ORM\Id]
	#[ORM\GeneratedValue(strategy: "AUTO")]
	#[ORM\Column(name: "id", type: Types::INTEGER)]
	private ?int $id = null;

	/**
	 * ULIDs have only 22 characters, but just to be sure
	 */
	#[ORM\Column(type: Types::STRING, length: 50, unique: true)]
	private string $taskId;

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
	private \DateTimeImmutable $timeQueued;

	/** @var Collection<int, TaskRun> */
	#[ORM\OneToMany(mappedBy: "taskLog", targetEntity: TaskRun::class)]
	#[ORM\OrderBy(["timeStarted" => "asc"])]
	private Collection $runs;

	/**
	 */
	public function __construct (
		Task $task,
	)
	{
		$this->taskId = $task->ulid;
		$this->runs = new ArrayCollection();
		$this->timeQueued = now();
	}

	/**
	 */
	public function getId () : ?int
	{
		return $this->id;
	}

	/**
	 */
	public function getTaskId () : string
	{
		return $this->taskId;
	}

	/**
	 */
	public function getTimeQueued () : \DateTimeImmutable
	{
		return $this->timeQueued;
	}

	/**
	 * @return Collection<int, TaskRun>
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
			if ($run->isSuccess())
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
			if (!$run->isFinished())
			{
				return $run;
			}
		}

		return null;
	}

	/**
	 *
	 */
	public function startRun () : TaskRun
	{
		if ($this->isSuccess())
		{
			throw new InvalidLogActionException("Can't start a run for a task #{$this->id} that is already finished.");
		}

		$run = new TaskRun($this);
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
	 * @return bool|null whether the task succeeded/failed or null, if it hasn't run yet
	 */
	public function getStatus () : ?bool
	{
		$result = null;

		foreach ($this->runs as $run)
		{
			if (!$run->isFinished())
			{
				continue;
			}

			if ($run->isSuccess())
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
			$duration += (float) $run->getDuration();
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
	 */
	public function getTaskClass () : ?string
	{
		return $this->getTaskDetails()["class"] ?? null;
	}
}
