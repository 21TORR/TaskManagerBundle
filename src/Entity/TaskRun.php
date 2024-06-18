<?php declare(strict_types=1);

namespace Torr\TaskManager\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Torr\TaskManager\Exception\Log\InvalidLogActionException;

use function Symfony\Component\Clock\now;

/**
 * @final
 */
#[ORM\Entity]
#[ORM\Table(name: "task_manager_runs")]
class TaskRun
{
	// region Fields
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
	#[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
	private \DateTimeImmutable $timeStarted;

	/**
	 *
	 */
	#[ORM\Column(type: Types::FLOAT, nullable: true)]
	private ?float $duration = null;

	/**
	 */
	#[ORM\Column(type: Types::BOOLEAN, nullable: true)]
	private ?bool $success = null;

	/**
	 */
	#[ORM\Column(type: Types::BOOLEAN, nullable: true)]
	private ?bool $finishedProperly = null;

	/**
	 */
	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private ?string $output = null;

	/**
	 *
	 */
	private ?float $start = null;
	// endregion

	/**
	 */
	public function __construct (TaskLog $taskLog)
	{
		$this->taskLog = $taskLog;
		$this->timeStarted = now();
		$this->start = hrtime(true);
	}

	// region Accessors
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
	 * Whether the task was finished successfully.
	 *
	 * @return bool|null Whether the task was finished successfully. Will return null if not yet finished.
	 */
	public function isSuccess () : ?bool
	{
		return $this->success;
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
		return null !== $this->duration;
	}

	/**
	 * The duration of the run in nanoseconds (if the task is finished already)
	 */
	public function getDuration () : ?float
	{
		return $this->duration;
	}

	/**
	 * Whether the task was finished properly or was automatically finished.
	 */
	public function hasFinishedProperly () : bool
	{
		return true === $this->finishedProperly;
	}
	// endregion

	/**
	 */
	public function finish (bool $success, ?string $output) : void
	{
		$this->finalizeRun(
			success: $success,
			finishedProperly: true,
			output: $output,
		);
	}

	/**
	 * Aborts the task, without finishing it properly
	 */
	public function abort (bool $success, ?string $output = null) : void
	{
		$this->finalizeRun(
			success: $success,
			finishedProperly: false,
			output: $output,
		);
	}

	/**
	 * Finalizes the run
	 */
	private function finalizeRun (
		bool $success,
		bool $finishedProperly,
		?string $output = null,
	) : void
	{
		if ($this->isFinished())
		{
			throw new InvalidLogActionException("Can't finalize task run #{$this->id} as it is already finished.");
		}

		if (null === $this->start)
		{
			throw new InvalidLogActionException("Can't finalize a task that wasn't started in this run.");
		}

		$this->success = $success;
		$this->finishedProperly = $finishedProperly;
		$this->output = $output;
		$this->duration = hrtime(true) - $this->start;
	}
}
