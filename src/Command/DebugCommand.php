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
use Torr\TaskManager\Transport\TransportsHelper;

#[AsCommand("task-manager:debug")]
final class DebugCommand extends Command
{
	/**
	 */
	public function __construct (
		private readonly TransportsHelper $transportsHelper,
		private readonly TaskRegistry $taskRegistry,
	)
	{
		parent::__construct();
	}

	/**
	 * @inheritDoc
	 */
	protected function execute (InputInterface $input, OutputInterface $output) : int
	{
		$io = new TorrStyle($input, $output);
		$io->title("Task Manager: Debug");

		$this->listTasks($io);
		$this->listQueues($io);

		return self::SUCCESS;
	}

	/**
	 */
	private function listTasks (TorrStyle $io) : void
	{
		$io->section("Registered Tasks");
		$rows = [];
		$hasPrivateTask = false;

		foreach ($this->taskRegistry->getGroupedTasks(TaskRegistry::INCLUDE_PRIVATE_TASKS) as $groupLabel => $tasks)
		{
			$first = true;

			if (!empty($rows))
			{
				$rows[] = new TableSeparator();
			}

			foreach ($tasks as $task)
			{
				$row = [];
				$metaData = $task->getMetaData();

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

				$taskKey = $metaData->getKey();
				$isPrivate = !$this->taskRegistry->isPublicTask($taskKey);

				if ($isPrivate)
				{
					$hasPrivateTask = true;
				}

				$row[] = sprintf(
					"<fg=yellow>%s</>%s",
					$taskKey,
					$isPrivate
						? " <fg=red>*</>"
						: "",
				);
				$row[] = $metaData->label;
				$row[] = $task::class;
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
			rows: $rows,
		);

		if ($hasPrivateTask)
		{
			$io->writeln("<fg=red>*</> private task. Only registrable via the CLI.");
			$io->newLine();
		}
	}

	/**
	 *
	 */
	private function listQueues (TorrStyle $io) : void
	{
		$io->section("Detected Queues");
		$io->listing($this->transportsHelper->getOrderedQueueNames());
	}
}
