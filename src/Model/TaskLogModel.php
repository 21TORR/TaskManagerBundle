<?php declare(strict_types=1);

namespace Torr\TaskManager\Model;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
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
		$this->repository = $this->entityManager->getRepository(TaskLog::class);
	}

	/**
	 *
	 */
	public function findById (int $id) : ?TaskLog
	{
		return $this->repository->find($id);
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
	 * Returns the latest task log entries
	 *
	 * @return TaskLog[]
	 */
	public function getMostRecentEntries (int $limit = 100) : array
	{
		$query = $this->repository->createQueryBuilder("task")
			->select("task, run")
			->leftJoin("task.runs", "run")
			->addOrderBy("task.timeQueued", "DESC")
			->setMaxResults($limit)
			->getQuery();

		/** @var TaskLog[] */
		return (new Paginator($query))
			->getQuery()
			->getResult();
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
	public function fetchOutdatedTasks (
		int $maxAgeInDays,
		int $maxEntries,
	) : array
	{
		// start with a fixed TTL
		$purgeBefore = $this->clock->now()
			->sub(new \DateInterval("P{$maxAgeInDays}D"));

		// check whether the last entry at "max entries" would be newer than the
		// TTL. If so, then adjust the purge date, to fulfill both
		$cutOffEntry = $this->getCutoffEntry($maxEntries);

		if (null !== $cutOffEntry && $cutOffEntry->getTimeQueued() > $purgeBefore)
		{
			$purgeBefore = $cutOffEntry->getTimeQueued();
		}

		/** @var TaskLog[] $entries */
		$entries = $this->repository->createQueryBuilder("task")
			->select("task, run")
			->leftJoin("task.runs", "run")
			->where("task.timeQueued <= :oldestTimestamp")
			->setParameter("oldestTimestamp", $purgeBefore)
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
	 *
	 */
	private function getCutoffEntry (int $maxEntries) : ?TaskLog
	{
		/** @var TaskLog[] $result */
		$result = $this->repository->createQueryBuilder("task")
		->addOrderBy("task.timeQueued", "DESC")
		->setFirstResult($maxEntries)
		->setMaxResults(1)
		->getQuery()
		->getResult();

		return $result[0] ?? null;
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
