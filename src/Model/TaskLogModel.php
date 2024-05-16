<?php declare(strict_types=1);

namespace Torr\TaskManager\Model;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
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
		$log = new TaskLog($task->ulid);
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
	 */
	public function flush () : void
	{
		$this->entityManager->flush();
	}
}
