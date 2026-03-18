<?php declare(strict_types=1);

namespace Torr\TaskManager\Log;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Torr\TaskManager\Entity\TaskLog;
use Torr\TaskManager\Entity\TaskRun;

final readonly class LogCleaner
{
	public function __construct (
		private int $logTtlInDays,
		private int $logMaxEntries,
		private EntityManagerInterface $entityManager,
		private ClockInterface $clock,
	) {}

	/**
	 * @return int the number of deleted tasks
	 */
	public function cleanLogEntries () : int
	{
		$taskIdToDelete = $this->fetchIdsToDelete();

		if (empty($taskIdToDelete))
		{
			return 0;
		}

		// first delete runs, as they are a foreign key on the task logs
		$this->deleteRuns($taskIdToDelete);

		// then delete tasks
		$this->deleteTasks($taskIdToDelete);

		return \count($taskIdToDelete);
	}

	/**
	 *
	 */
	private function deleteRuns (array $taskIdsToDelete) : void
	{
		$runIdsToDelete = $this->entityManager->createQueryBuilder()
			->select("run.id")
			->from(TaskRun::class, "run")
			->leftJoin("run.taskLog", "task")
			->andWhere("task.id IN (:taskIds)")
			->setParameter("taskIds", $taskIdsToDelete)
			->getQuery()
			->getArrayResult();

		if (empty($runIdsToDelete))
		{
			return;
		}

		$runIdsToDelete = array_column($runIdsToDelete, "id");

		$this->entityManager->createQueryBuilder()
			->delete()
			->from(TaskRun::class, "run")
			->andWhere("run.id IN (:runIds)")
			->setParameter("runIds", $runIdsToDelete)
			->getQuery()
			->execute();
	}

	/**
	 *
	 */
	private function deleteTasks (array $taskIdsToDelete) : void
	{
		$this->entityManager->createQueryBuilder()
			->delete()
			->from(TaskLog::class, "task")
			->andWhere("task.id IN (:taskIds)")
			->setParameter("taskIds", $taskIdsToDelete)
			->getQuery()
			->execute();
	}

	/**
	 */
	private function fetchIdsToDelete () : array
	{
		// start with a fixed TTL
		$purgeBefore = $this->clock->now()
			->sub(new \DateInterval("P{$this->logTtlInDays}D"));

		// check whether the last entry at "max entries" would be newer than the
		// TTL. If so, then adjust the purge date to fulfill both
		$cutOffEntry = $this->getCutoffEntry($this->logMaxEntries);

		if (null !== $cutOffEntry && $cutOffEntry->timeQueued > $purgeBefore)
		{
			$purgeBefore = $cutOffEntry->timeQueued;
		}

		$rows = $this->entityManager->createQueryBuilder()
			->select("distinct task.id")
			->from(TaskLog::class, "task")
			->leftJoin("task.runs", "run")
			->where("task.timeQueued <= :oldestTimestamp")
			->setParameter("oldestTimestamp", $purgeBefore)
			->getQuery()
			->getArrayResult();

		return array_column($rows, "id");
	}

	/**
	 *
	 */
	private function getCutoffEntry (int $maxEntries) : ?TaskLog
	{
		/** @var TaskLog[] $result */
		$result = $this->entityManager->createQueryBuilder()
			->select("task")
			->from(TaskLog::class, "task")
			->addOrderBy("task.timeQueued", "DESC")
			->setFirstResult($maxEntries)
			->setMaxResults(1)
			->getQuery()
			->getResult();

		return $result[0] ?? null;
	}

	/**
	 * Returns the maximum age of log entries to keep (in days)
	 */
	public function getMaxLogEntryAge () : int
	{
		return $this->logTtlInDays;
	}

	/**
	 * Returns the maximum number of log entries to keep
	 */
	public function getMaxLogEntryNumber () : int
	{
		return $this->logMaxEntries;
	}
}
