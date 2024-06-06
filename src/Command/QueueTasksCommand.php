<?php declare(strict_types=1);

namespace Torr\TaskManager\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Torr\Cli\Console\Style\TorrStyle;
use Torr\TaskManager\Exception\Registry\UnknownTaskKeyException;
use Torr\TaskManager\Manager\TaskManager;
use Torr\TaskManager\Transport\TransportsHelper;
use Torr\TaskManager\Registry\TaskRegistry;
use Torr\TaskManager\Task\Task;

#[AsCommand("task-manager:queue")]
final class QueueTasksCommand extends Command
{
	/**
	 */
	public function __construct (
		private readonly TaskRegistry $taskRegistry,
		private readonly TaskManager $taskManager,
		private readonly TransportsHelper $receiverHelper,
	)
	{
		parent::__construct();
	}

	/**
	 * @inheritDoc
	 */
	protected function configure () : void
	{
		$this
			->setDescription("Helper to queue tasks.")
			->addArgument("tasks", InputArgument::IS_ARRAY | InputArgument::OPTIONAL, "The task keys to queue");
	}

	/**
	 * @inheritDoc
	 */
	protected function execute (InputInterface $input, OutputInterface $output) : int
	{
		$io = new TorrStyle($input, $output);
		$io->title("Task Manager: Queue Task");

		if ($this->receiverHelper->hasSyncTransport())
		{
			$io->caution("The app is using sync transports: that means that registered tasks are directly worked on.");
		}

		try
		{
			$tasksToQueue = $this->getTasksToQueue($input, $io);
		}
		catch (UnknownTaskKeyException $exception)
		{
			$io->error($exception->getMessage());

			return self::FAILURE;
		}

		$io->comment(sprintf(
			"Queuing <fg=magenta>%d</> task%s",
			\count($tasksToQueue),
			1 !== \count($tasksToQueue) ? "s" : "",
		));

		foreach ($tasksToQueue as $task)
		{
			$io->writeln(sprintf(
				"• Queuing task %s",
				$this->formatTaskLabel($task),
			));
			$this->taskManager->enqueue($task);
		}

		$io->success("All done.");

		return self::SUCCESS;
	}

	/**
	 * Handles the interaction to get the tasks to queue
	 *
	 * @return Task[]
	 */
	private function getTasksToQueue (InputInterface $input, TorrStyle $io) : array
	{
		/** @var string[] $taskKeysProvidedInArgument */
		$taskKeysProvidedInArgument = $input->getArgument("tasks");

		if ([] !== $taskKeysProvidedInArgument)
		{
			return $this->fetchTasksByKey($taskKeysProvidedInArgument);
		}

		$flatTasks = [];
		$choices = [];

		foreach ($this->taskRegistry->getGroupedTasks() as $tasks)
		{
			foreach ($tasks as $task)
			{
				$choices[] = $this->formatTaskLabel($task);
				$flatTasks[] = $task;
			}
		}

		/** @var string[] $selectedOptions */
		$selectedOptions = $io->choice(
			"Which tasks should be queued?",
			$choices,
			multiSelect: true,
		);

		$result = [];

		foreach ($selectedOptions as $option)
		{
			$index = array_search($option, $choices, true);
			\assert(\is_int($index));

			$result[] = $flatTasks[$index];
		}

		return $result;
	}

	/**
	 * @param string[] $keys
	 *
	 * @return Task[]
	 */
	private function fetchTasksByKey (array $keys) : array
	{
		$result = [];

		foreach ($keys as $taskKey)
		{
			$result[] = $this->taskRegistry->getTaskByKey($taskKey)
				?? throw new UnknownTaskKeyException(sprintf(
					"Unknown task key '%s'",
					$taskKey,
				));
		}

		return $result;
	}

	/**
	 *
	 */
	private function formatTaskLabel (Task $task) : string
	{
		$metaData = $task->getMetaData();

		if (null !== $metaData->group)
		{
			return sprintf(
				"<fg=blue>%s</>: %s (<fg=yellow>%s</>)",
				$metaData->group,
				$metaData->label,
				$metaData->getKey(),
			);
		}

		return $metaData->label;
	}
}
