<?php declare(strict_types=1);

namespace Torr\TaskManager\Model;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Psr\Log\LoggerInterface;
use Torr\TaskManager\Entity\TaskLog;
use Torr\TaskManager\Entity\TaskRun;
use Torr\TaskManager\Task\DispatchAfterRunTask\DispatchAfterRunTask;

final class TaskLogModel
{
	public const bool SHOW_ALL_TASKS = true;
	public const bool HIDE_INTERNAL_TASKS = false;

	/** @var EntityRepository<TaskLog> */
	private EntityRepository $repository;

	/**
	 */
	public function __construct (
		private readonly EntityManagerInterface $entityManager,
		private readonly LoggerInterface $logger,
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
	 *
	 */
	public function findByTaskId (string $taskId) : ?TaskLog
	{
		return $this->repository->findOneBy([
			"taskId" => $taskId,
		]);
	}

	/**
	 * Returns the ids of the given tasks that are currently being handled (= have an unfinished run)
	 *
	 * @param list<string> $taskIds
	 *
	 * @return list<string>
	 */
	public function getStartedTaskIds (array $taskIds) : array
	{
		if (empty($taskIds))
		{
			return [];
		}

		/** @var list<string> */
		return $this->repository->createQueryBuilder("task")
			->select("DISTINCT task.taskId")
			->innerJoin("task.runs", "run")
			->where("task.taskId IN (:taskIds)")
			->andWhere("run.duration IS NULL")
			->setParameter("taskIds", $taskIds)
			->getQuery()
			->getSingleColumnResult();
	}

	/**
	 * Gets or creates the log entry for the given task uuid
	 */
	public function getLogForUuid (object $task, string $uuid) : TaskLog
	{
		$log = $this->findByTaskId($uuid);

		if (null !== $log)
		{
			return $log;
		}

		// if it isn't created yet, create a new one
		$log = new TaskLog($task, $uuid);
		$this->entityManager->persist($log);

		return $log;
	}

	/**
	 * Returns the latest task log entries
	 *
	 * @return TaskLog[]
	 */
	public function getMostRecentEntries (
		int $limit = 100,
		bool $showAll = self::SHOW_ALL_TASKS,
	) : array
	{
		$builder = $this->repository->createQueryBuilder("task")
			->select("task, run")
			->leftJoin("task.runs", "run")
			->addOrderBy("task.timeQueued", "DESC")
			->setMaxResults($limit);

		if (self::HIDE_INTERNAL_TASKS === $showAll)
		{
			$builder
				->andWhere("task.taskClass NOT IN (:internalTasks)")
				->setParameter("internalTasks", [
					DispatchAfterRunTask::class,
				]);
		}

		/** @var TaskLog[] */
		return $builder->getQuery()->getResult();
	}

	/**
	 * Creates a new run for the given task (lok) and marks it as persisted.
	 */
	public function createRunForTask (TaskLog $log) : TaskRun
	{
		$run = $log->createRun($this->logger);
		$this->entityManager->persist($run);

		return $run;
	}

	/**
	 * Returns the managed instance of the run.
	 *
	 * The run might be detached in the meantime, e.g. if the entity manager was cleared while handling the message.
	 */
	public function getManagedRun (TaskRun $run) : ?TaskRun
	{
		if ($this->entityManager->contains($run))
		{
			return $run;
		}

		return null !== $run->id
			? $this->entityManager->find(TaskRun::class, $run->id)
			: null;
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
