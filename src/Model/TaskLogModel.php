<?php declare(strict_types=1);

namespace Torr\TaskManager\Model;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Psr\Log\LoggerInterface;
use Torr\TaskManager\Entity\TaskLog;
use Torr\TaskManager\Entity\TaskRun;
use Torr\TaskManager\Task\DispatchAfterRunTask\DispatchAfterRunTask;
use Torr\TaskManager\Task\Task;

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
	 *
	 */
	public function getTaskCount () : int
	{
		return $this->repository->count();
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
		return (new Paginator($builder->getQuery()))
			->getQuery()
			->getResult();
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
