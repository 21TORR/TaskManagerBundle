<?php declare(strict_types=1);

namespace Torr\TaskManager\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Psr\Log\LoggerInterface;
use Torr\TaskManager\Duration\DurationCalculator;

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
	public private(set) ?int $id = null;

	/**
	 */
	#[ORM\ManyToOne(targetEntity: TaskLog::class, inversedBy: "runs")]
	#[ORM\JoinColumn(name: "task_log_id", referencedColumnName: "id", nullable: false)]
	public private(set) TaskLog $taskLog;

	/**
	 *
	 */
	#[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
	public private(set) \DateTimeImmutable $timeStarted;

	/**
	 *
	 */
	#[ORM\Column(type: Types::FLOAT, nullable: true)]
	public private(set) ?float $duration = null;

	/**
	 */
	#[ORM\Column(type: Types::BOOLEAN, nullable: true)]
	public private(set) ?bool $success = null;

	/**
	 */
	#[ORM\Column(type: Types::BOOLEAN, nullable: true)]
	public private(set) ?bool $finishedProperly = null;

	/**
	 */
	#[ORM\Column(type: Types::TEXT, nullable: true)]
	public private(set) ?string $output = null;

	/**
	 */
	public bool $isFinished {
		get => null !== $this->duration;
	}
	// endregion

	/**
	 */
	public function __construct (
		TaskLog $taskLog,
		private readonly ?LoggerInterface $logger = null,
	)
	{
		$this->taskLog = $taskLog;
		$this->timeStarted = now();
	}

	// region Accessors
	/**
	 * @deprecated use the property directly instead
	 */
	public function getTaskLog () : TaskLog
	{
		return $this->taskLog;
	}

	/**
	 * @deprecated use the property directly instead
	 */
	public function getTimeStarted () : \DateTimeImmutable
	{
		return $this->timeStarted;
	}

	/**
	 * @deprecated use the property directly instead
	 *
	 * Whether the task was finished successfully
	 *
	 * @return bool|null Whether the task was finished successfully. Will return null if not yet finished.
	 */
	public function isSuccess () : ?bool
	{
		return $this->success;
	}

	/**
	 * @deprecated use the property directly instead
	 */
	public function getOutput () : ?string
	{
		return $this->output;
	}

	/**
	 * @deprecated use the property directly instead
	 */
	public function isFinished () : bool
	{
		return null !== $this->duration;
	}

	/**
	 * The duration of the run in nanoseconds (if the task is finished already)
	 *
	 * @deprecated use the property directly instead
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
		if ($this->isFinished)
		{
			$this->logger?->error("Can't finalize task run {id} as it is already finished.", [
				"id" => $this->id,
				"success" => $success,
				"finishedProperly" => $finishedProperly,
			]);

			return;
		}

		$this->success = $success;
		$this->finishedProperly = $finishedProperly;
		$this->output = $output;
		$this->duration = new DurationCalculator()->calculateDuration($this->timeStarted, now());
	}
}
