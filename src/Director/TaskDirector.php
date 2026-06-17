<?php declare(strict_types=1);

namespace Torr\TaskManager\Director;

use Psr\Log\LoggerInterface;
use Torr\TaskManager\Identification\TaskIdentifier;
use Torr\TaskManager\Model\TaskLogModel;
use Torr\TaskManager\Task\Task;

final readonly class TaskDirector
{
	/**
	 */
	public function __construct (
		private TaskLogModel $logModel,
		private LoggerInterface $logger,
		private TaskIdentifier $taskIdentifier,
	) {}

	/**
	 *
	 */
	public function startRun (Task $task) : RunDirector
	{
		$uuid = $this->taskIdentifier->getUuid($task);

		if (null === $uuid)
		{
			$this->logger->critical("Could not identify task of type {type}", [
				"type" => get_debug_type($task),
				"task" => $task,
			]);

			return new RunDirector($this->logModel, null);
		}

		$log = $this->logModel->getLogForUuid($task, $uuid);

		// create run
		$run = $this->logModel->createRunForTask($log);
		$this->logModel->flush();

		// store latest run
		$this->taskIdentifier->setLatestRun($log, $run);

		return new RunDirector($this->logModel, $run);
	}
}
