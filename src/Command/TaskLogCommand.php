<?php declare(strict_types=1);

namespace Torr\TaskManager\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Torr\Cli\Console\Style\TorrStyle;
use Torr\TaskManager\Model\TaskLogModel;

#[AsCommand("task-manager:log")]
final class TaskLogCommand extends Command
{
	/**
	 */
	public function __construct (
		private readonly TaskLogModel $model,
	)
	{
		parent::__construct();
	}

	/**
	 *
	 */
	#[\Override]
	protected function configure () : void
	{
		$this
			->addArgument(
				"task",
				InputArgument::OPTIONAL,
				"The ID of the task to show the details of",
			)
			->addOption(
				"limit",
				null,
				InputOption::VALUE_REQUIRED,
				"The maximum count of listed log entries",
				"100",
			);
	}

	/**
	 *
	 */
	#[\Override]
	protected function execute (InputInterface $input, OutputInterface $output) : int
	{
		$io = new TorrStyle($input, $output);
		$io->title("Task Manager: Log");

		$taskId = $input->getArgument("task");

		if (null !== $taskId)
		{
			$taskId = $this->validateInt($taskId);

			if (null === $taskId)
			{
				$io->error("Invalid argument: taskId must be an integer and must be >0");

				return self::FAILURE;
			}

			return $this->showTaskDetails($io, $taskId);
		}

		$limit = $this->validateInt($input->getOption("limit"));

		if (null === $limit)
		{
			$io->error("Invalid option: limit must be an integer and must be >0");

			return self::FAILURE;
		}

		$this->showList($io, $limit);

		return self::SUCCESS;
	}

	/**
	 * @return positive-int|null
	 */
	private function validateInt (mixed $value) : ?int
	{
		return (ctype_digit($value) && ((int) $value) > 0)
			? (int) $value
			: null;
	}

	/**
	 *
	 */
	private function showTaskDetails (TorrStyle $io, int $taskId) : int
	{
		$task = $this->model->findById($taskId);

		if (null === $task)
		{
			$io->error(\sprintf("No task found with id '%d'", $taskId));

			return self::FAILURE;
		}

		$status = "<fg=yellow>queued</>";

		if ($task->isFinished())
		{
			$status = $task->isSuccess()
				? "<fg=green>succeeded</>"
				: "<fg=red>failed</>";
		}

		$handled = [];

		if (null !== $task->getHandledBy())
		{
			$handled[] = \sprintf(
				"<fg=blue>%s</>",
				$task->getHandledBy(),
			);
		}

		if (null !== $task->getTransport())
		{
			$handled[] = \sprintf(
				"<fg=blue>%s</>",
				$task->getTransport(),
			);
		}

		$io->definitionList(
			["Task ID" => $task->getId()],
			["Task" => $task->getTaskLabel()],
			["Status" => $status],
			["Task Class" => $task->getTaskClass() ?? "<fg=gray>—</>"],
			["Runs" => \count($task->getRuns())],
			["Total Duration" => $this->formatDuration($task->getTotalDuration())],
			["Handled by" => implode(" on ", $handled)],
			["Registered" => $task->getTimeQueued()->format("c")],
		);

		$index = \count($task->getRuns());

		foreach ($task->getRuns() as $run)
		{
			$status = "<fg=yellow>running</>";

			if ($run->isFinished())
			{
				$status = $run->isSuccess()
					? "<fg=green>succeeded</>"
					: "<fg=red>failed</>";
			}

			$io->section(\sprintf(
				"Run %d (%s)",
				$index,
				$status,
			));
			$io->writeln(\sprintf(
				"Started: %s",
				$run->getTimeStarted()->format("c"),
			));

			if ($run->isFinished())
			{
				$io->writeln(\sprintf(
					"Duration: %s",
					$this->formatDuration((float) $run->getDuration()),
				));
				$io->writeln("Output:");
				$io->newLine();
				$io->writeln("------------------");
				$io->writeln((string) $run->getOutput());
				$io->writeln("------------------");
			}

			if ($index > 1)
			{
				$io->newLine(2);
			}

			--$index;
		}

		return self::SUCCESS;
	}

	/**
	 *
	 */
	private function showList (TorrStyle $io, int $limit) : void
	{
		$rows = [];

		foreach ($this->model->getMostRecentEntries($limit) as $task)
		{
			$status = "<fg=yellow>queued</>";

			if ($task->isFinished())
			{
				$status = $task->isSuccess()
					? "<fg=green>succeeded</>"
					: "<fg=red>failed</>";
			}

			$rows[] = [
				$task->getId(),
				\sprintf(
					"<fg=%s>%s</>",
					null !== $task->getTaskLabel() ? "yellow" : "gray",
					$task->getTaskLabel() ?? "—",
				),
				$status,
				$task->getTaskClass() ?? "<fg=gray>—</>",
				\count($task->getRuns()),
				$this->formatDuration($task->getTotalDuration()),
				$task->getTimeQueued()->format("c"),
			];
		}
		$rows[] = [
			"ID",
			42,
			45.2,
			null,
			"Runs",
			"Duration",
			"Registered",
		];

		$io->table([
			"ID",
			"Task",
			"Status",
			"Class",
			"Runs",
			"Duration",
			"Registered",
		], $rows);
	}

	/**
	 *
	 */
	private function formatDuration (float $value) : string
	{
		$scales = [
			[1e9, "s", 1e9],
			[1e4, "ms", 1e6],
		];

		foreach ($scales as $scale)
		{
			[$minimumValue, $unit, $scaleBy] = $scale;

			if ($value < $minimumValue)
			{
				continue;
			}

			return number_format(
				$value / $scaleBy,
				2,
			) . $unit;
		}

		return $value . "ns";
	}
}
