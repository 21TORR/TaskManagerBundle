<?php declare(strict_types=1);

namespace Torr\TaskManager\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Torr\TaskManager\Exception\Log\InvalidLogActionException;

use function Symfony\Component\Clock\now;

/**
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
	 *
	 */
	#[ORM\Column(name: "time_queued", type: Types::DATETIMETZ_IMMUTABLE)]
	private \DateTimeImmutable $timeQueued;

	/** @var Collection<int, TaskRun> */
	#[ORM\OneToMany(mappedBy: "taskLog", targetEntity: TaskRun::class)]
	#[ORM\OrderBy(["timeStarted" => "asc"])]
	private Collection $runs;

	public function __construct (
		string $taskId,
	)
	{
		$this->taskId = $taskId;
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
	 */
	public function isFinishedSuccessfully () : ?bool
	{
		foreach ($this->runs as $run)
		{
			if ($run->isFinishedSuccessfully())
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
		if ($this->isFinishedSuccessfully())
		{
			throw new InvalidLogActionException("Can't start a run for a task #{$this->id} that is already finished.");
		}

		$run = new TaskRun($this);
		$this->runs->add($run);

		return $run;
	}
}
