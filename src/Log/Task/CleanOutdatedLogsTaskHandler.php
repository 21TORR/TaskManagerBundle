<?php declare(strict_types=1);

namespace Torr\TaskManager\Log\Task;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Torr\TaskManager\Director\TaskDirector;
use Torr\TaskManager\Log\LogCleaner;

final readonly class CleanOutdatedLogsTaskHandler
{
	/**
	 */
	public function __construct (
		private LogCleaner $logCleaner,
		private TaskDirector $taskDirector,
	) {}

	/**
	 */
	#[AsMessageHandler]
	public function onCleanOutdatedLogs (CleanOutdatedLogsTask $task) : void
	{
		$run = $this->taskDirector->startRun($task);
		$io = $run->getIo();

		$io->title("Task Manager: Cleaning Outdated Log Entries");
		$io->comment(\sprintf(
			"Cleaning log entries older than <fg=blue>%d days</> and keeping at most <fg=blue>%d entries</>",
			$this->logCleaner->getMaxLogEntryAge(),
			$this->logCleaner->getMaxLogEntryNumber(),
		));

		$deletedEntries = $this->logCleaner->cleanLogEntries();

		$io->success(\sprintf(
			"Deleted %d %s",
			$deletedEntries,
			1 !== $deletedEntries
				? "entries"
				: "entry",
		));

		$run->finish(true);
	}
}
