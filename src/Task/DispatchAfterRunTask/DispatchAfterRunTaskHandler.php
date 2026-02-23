<?php declare(strict_types=1);

namespace Torr\TaskManager\Task\DispatchAfterRunTask;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Torr\Cli\Console\Style\TorrStyle;
use Torr\TaskManager\Manager\TaskManager;

/**
 * @final
 */
readonly class DispatchAfterRunTaskHandler
{
	/**
	 */
	public function __construct (
		private TaskManager $taskManager,
	) {}

	/**
	 *
	 */
	#[AsMessageHandler]
	public function onDispatchAfterRunTask (DispatchAfterRunTask $task) : void
	{
		// DispatchAfterRun message should not use the task director, as we can't recreate the task ulid.
		$io = new TorrStyle(
			new ArrayInput([]),
			new ConsoleOutput(OutputInterface::VERBOSITY_NORMAL, true),
		);

		$io->writeln(\sprintf(
			"Redispatching task <fg=yellow>%s</>",
			$task->task::class,
		));

		$stamps = !empty($task->transportNames)
			? [new TransportNamesStamp($task->transportNames)]
			: [];

		$wrappedTask = $task->task->withNewTaskUlid();
		$this->taskManager->enqueue($wrappedTask, $stamps);
	}
}
