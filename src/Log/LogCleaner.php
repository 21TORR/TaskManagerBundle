<?php declare(strict_types=1);

namespace Torr\TaskManager\Log;

use Torr\TaskManager\Model\TaskLogModel;

final readonly class LogCleaner
{
	public function __construct (
		private int $logTtlInDays,
		private TaskLogModel $model,
	) {}

	/**
	 * @return string[] labels for the removed tasks
	 */
	public function cleanLogEntries () : array
	{
		$deleted = [];

		foreach ($this->model->fetchOutdatedTasks($this->logTtlInDays) as $logEntry)
		{
			$deleted[] = \sprintf(
				"<fg=yellow>%s</> (%s)",
				$logEntry->getTaskLabel(),
				$logEntry->getTimeQueued()->format("c"),
			);

			$this->model->remove($logEntry);
		}

		$this->model->flush();

		return $deleted;
	}

	/**
	 * Returns the maximum age of log entries to keep (in days)
	 */
	public function getMaxLogEntryAge () : int
	{
		return $this->logTtlInDays;
	}
}
