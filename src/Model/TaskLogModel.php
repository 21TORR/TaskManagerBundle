<?php declare(strict_types=1);

namespace Torr\TaskManager\Model;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Psr\Clock\ClockInterface;
use Torr\TaskManager\Entity\TaskLog;
use Torr\TaskManager\Entity\TaskRun;
use Torr\TaskManager\Task\Task;

final class TaskLogModel
{
	/** @var EntityRepository<TaskLog> */
	private EntityRepository $repository;

	/**
	 */
	public function __construct (
		private readonly EntityManagerInterface $entityManager,
		private readonly ClockInterface $clock,
	)
	{
		$repository = $this->entityManager->getRepository(TaskLog::class);
		\assert($repository instanceof EntityRepository);
		$this->repository = $repository;
	}

	/**
	 * Gets or creates the log entry for the given task
	 */
	public function getLogForTask (Task $task) : TaskLog
	{
		$log = $this->repository->findOneBy([
			"taskId" => $task->ulid,
		]);

		if (null !== $log)
		{
			return $log;
		}

		// if it isn't created yet, create a new one
		$log = new TaskLog($task);
		$this->entityManager->persist($log);

		return $log;
	}

	/**
	 * Creates a new run for the given task (lok) and marks it as persisted.
	 */
	public function createRunForTask (TaskLog $log) : TaskRun
	{
		$run = new TaskRun($log);
		$this->entityManager->persist($run);

		return $run;
	}

	/**
	 * @return list<TaskLog>
	 */
	public function fetchOutdatedTasks (int $maxAgeInDays) : array
	{
		$oldestTimeQueued = $this->clock->now()
			->sub(new \DateInterval("P{$maxAgeInDays}D"));

		/** @var TaskLog[] $entries */
		$entries = $this->repository->createQueryBuilder("task")
			->leftJoin("task.runs", "run")
			->where("task.timeQueued <= :oldestTimestamp")
			->setParameter("oldestTimestamp", $oldestTimeQueued)
			->getQuery()
			->getResult();

		$filtered = [];

		foreach ($entries as $entry)
		{
			if ($entry->isFinished())
			{
				$filtered[] = $entry;
			}
		}

		return $filtered;
	}

	/**
	 * @return $this
	 */
	public function flush () : static
	{
		$this->entityManager->flush();

		return $this;
	}

	/**
	 * Marks the log entry for removal
	 *
	 * @return $this
	 */
	public function remove (TaskLog $log) : static
	{
		$this->entityManager->remove($log);

		return $this;
	}
}
