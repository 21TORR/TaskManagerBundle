<?php declare(strict_types=1);

namespace Torr\TaskManager\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableCellStyle;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Torr\Cli\Console\Style\TorrStyle;
use Torr\TaskManager\Registry\TaskRegistry;

#[AsCommand("task-manager:list-tasks")]
final class ListTasksCommand extends Command
{
	/**
	 */
	public function __construct (
		private readonly TaskRegistry $taskRegistry,
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
			->setDescription("Lists all available tasks.");
	}

	/**
	 * @inheritDoc
	 */
	protected function execute (InputInterface $input, OutputInterface $output) : int
	{
		$io = new TorrStyle($input, $output);
		$io->title("Task Manager: List Tasks");

		$rows = [];

		foreach ($this->taskRegistry->getGroupedTasks() as $groupLabel => $tasks)
		{
			$first = true;

			if (!empty($rows))
			{
				$rows[] = new TableSeparator();
			}

			foreach ($tasks as $task)
			{
				$row = [];

				if ($first)
				{
					$row[] = new TableCell($groupLabel, [
						"rowspan" => \count($tasks),
						"style" => new TableCellStyle([
							"fg" => "blue",
						]),
					]);
					$first = false;
				}

				$row[] = \sprintf(
					"<fg=yellow>%s</>",
					$task->key,
				);
				$row[] = $task->label;
				$row[] = \get_class($task->task);
				$rows[] = $row;
			}
		}


		$io->table(
			headers: [
				"Group",
				"Key",
				"Name",
				"Task Class",
			],
			// @phpstan-ignore-next-line This is fine and the type was fixed in newer versions of the CLI bundle
			rows: $rows,
		);

		return 0;
	}
}
