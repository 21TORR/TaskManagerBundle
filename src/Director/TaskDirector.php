<?php declare(strict_types=1);

namespace Torr\TaskManager\Director;

use Torr\TaskManager\Model\TaskLogModel;
use Torr\TaskManager\Task\Task;

final readonly class TaskDirector
{
	/**
	 */
	public function __construct (
		private TaskLogModel $logModel,
	) {}


	/**
	 *
	 */
	public function startRun (Task $task) : RunDirector
	{
		$log = $this->logModel->getLogForTask($task);

		// create run
		$run = $this->logModel->createRunForTask($log);
		$this->logModel->flush();

		return new RunDirector($this->logModel, $run);
	}
}
