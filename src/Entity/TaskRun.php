<?php declare(strict_types=1);

namespace Torr\TaskManager\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use function Symfony\Component\Clock\now;
use Torr\TaskManager\Exception\Log\InvalidLogActionException;

/**
 * @final
 */
#[ORM\Entity]
#[ORM\Table(name: "task_manager_runs")]
class TaskRun
{
	//region Fields
	/**
	 */
	#[ORM\Id]
	#[ORM\GeneratedValue(strategy: "AUTO")]
	#[ORM\Column(name: "id", type: Types::INTEGER)]
	private ?int $id = null;

	/**
	 */
	#[ORM\ManyToOne(targetEntity: TaskLog::class, inversedBy: "runs")]
	private TaskLog $taskLog;

	/**
	 *
	 */
	#[ORM\Column(name: "time_started", type: Types::DATETIMETZ_IMMUTABLE)]
	private \DateTimeImmutable $timeStarted;

	/**
	 *
	 */
	#[ORM\Column(name: "time_finished", type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
	private ?\DateTimeImmutable $timeFinished = null;

	/**
	 */
	#[ORM\Column(type: Types::BOOLEAN, nullable: true)]
	private ?bool $successful = null;

	/**
	 */
	#[ORM\Column(type: Types::BOOLEAN, nullable: true)]
	private ?bool $finishedProperly = null;

	/**
	 */
	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private ?string $output = null;
	//endregion


	/**
	 */
	public function __construct (TaskLog $taskLog)
	{
		$this->taskLog = $taskLog;
		$this->timeStarted = now();
	}


	//region Accessors
	/**
	 */
	public function getTaskLog () : TaskLog
	{
		return $this->taskLog;
	}

	/**
	 */
	public function getTimeStarted () : \DateTimeImmutable
	{
		return $this->timeStarted;
	}

	/**
	 */
	public function getTimeFinished () : ?\DateTimeImmutable
	{
		return $this->timeFinished;
	}

	/**
	 * Whether the task was finished successfully.
	 */
	public function isFinishedSuccessfully () : bool
	{
		return true === $this->successful;
	}

	/**
	 */
	public function getOutput () : ?string
	{
		return $this->output;
	}

	/**
	 */
	public function isFinished () : bool
	{
		return null !== $this->timeFinished;
	}

	/**
	 * Whether the task was finished properly or was automatically finished.
	 */
	public function hasFinishedProperly () : bool
	{
		return true === $this->finishedProperly;
	}
	//endregion


	/**
	 */
	public function finish (bool $successful, ?string $output) : void
	{
		if ($this->isFinished())
		{
			throw new InvalidLogActionException("Can't finish task run #{$this->id} as it is already finished.");
		}

		$this->finishedProperly = true;
		$this->successful = $successful;
		$this->output = $output;
		$this->timeFinished = now();
	}


	/**
	 * Aborts the task, without finishing it properly
	 */
	public function abort (bool $successful, ?string $output = null) : void
	{
		if ($this->isFinished())
		{
			throw new InvalidLogActionException("Can't abort task run #{$this->id} as it is already finished.");
		}

		$this->finishedProperly = false;
		$this->successful = $successful;
		$this->output = $output;
		$this->timeFinished = now();
	}
}
