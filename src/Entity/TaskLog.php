<?php declare(strict_types=1);

namespace Torr\TaskManager\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Messenger\Envelope;
use Torr\TaskManager\Exception\Log\InvalidLogActionException;
use Torr\TaskManager\Task\Task;

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
	 * @var resource|string|null
	 */
	#[ORM\Column(type: Types::BLOB, nullable: true)]
	private mixed $envelope = null;

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

	/**
	 */
	public function getEnvelope () : ?Envelope
	{
		if (null === $this->envelope)
		{
			return null;
		}

		$data = \is_resource($this->envelope)
			? \stream_get_contents($this->envelope)
			: $this->envelope;

		$envelope = unserialize($data);

		return $envelope instanceof Envelope
			? $envelope
			: null;
	}

	/**
	 */
	public function setEnvelope (?Envelope $envelope) : void
	{
		$this->envelope = null !== $envelope
			? serialize($envelope)
			: null;
	}

	/**
	 * Returns a label of the task
	 */
	public function getTaskLabel () : ?string
	{
		$envelope = $this->getEnvelope();

		if (null === $envelope)
		{
			return null;
		}

		$task = $envelope->getMessage();
		$label = $task instanceof Task
			? $task->getMetaData()->label
			: \get_debug_type($task);

		return "__PHP_Incomplete_Class" !== $label
			? $label
			: null;
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

			if ($run->isFinishedSuccessfully())
			{
				return true;
			}

			// we have a run that is finished and if we don't early exit, they apparently failed
			$result = false;
		}

		return $result;
	}
}
