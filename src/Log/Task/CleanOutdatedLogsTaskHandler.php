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
		$io->comment(sprintf(
			"Cleaning log entries older than <fg=blue>%d days</>",
			$this->logCleaner->getMaxLogEntryAge(),
		));

		$deletedEntries = $this->logCleaner->cleanLogEntries();

		if ([] === $deletedEntries)
		{
			$io->success("No entries to remove found");
			$run->finish(true);

			return;
		}

		$io->writeln("Removed:");
		$io->listing($deletedEntries);

		$io->success(sprintf(
			"Deleted <fg=yellow>%d</> %s:",
			\count($deletedEntries),
			1 !== \count($deletedEntries)
				? "entries"
				: "entry",
		));

		$run->finish(true);
	}
}
