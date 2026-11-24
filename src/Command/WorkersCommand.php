<?php declare(strict_types=1);

namespace Torr\TaskManager\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Torr\Cli\Console\Style\TorrStyle;
use Torr\TaskManager\Worker\WorkerInfo;
use Torr\TaskManager\Worker\WorkerRegistry;

#[AsCommand(
	"task-manager:workers",
	description: "Lists all running messenger workers and the transports they consume",
)]
final class WorkersCommand extends Command
{
	/**
	 */
	public function __construct (
		private readonly WorkerRegistry $workerRegistry,
	)
	{
		parent::__construct();
	}

	/**
	 *
	 */
	#[\Override]
	protected function execute (InputInterface $input, OutputInterface $output) : int
	{
		$io = new TorrStyle($input, $output);
		$io->title("Task Manager: Workers");

		$workers = $this->workerRegistry->getWorkers();

		if (empty($workers))
		{
			$io->comment("No running workers found.");

			return self::SUCCESS;
		}

		$rows = [];

		foreach ($workers as $worker)
		{
			$rows[] = [
				\sprintf("%s:%d", $worker->hostname, $worker->pid),
				implode(", ", $worker->transports),
				$worker->isBusy
					? \sprintf("<fg=yellow>busy</> (%s)", $worker->currentMessage)
					: "<fg=green>idle</>",
				\sprintf("%d / <fg=red>%d</>", $worker->handledMessages, $worker->failedMessages),
				\sprintf(
					"%s / %s",
					Helper::formatMemory($worker->memoryUsage),
					null !== $worker->availableMemory ? Helper::formatMemory($worker->availableMemory) : "unlimited",
				),
				$this->formatLimits($worker),
				$worker->startedAt->format("Y-m-d H:i:s"),
				$worker->lastSeenAt->format("Y-m-d H:i:s"),
			];
		}

		$io->table(
			["Worker", "Transports", "Status", "Handled / Failed", "Memory (used / available)", "Limits", "Started", "Last seen"],
			$rows,
		);

		return self::SUCCESS;
	}

	/**
	 */
	private function formatLimits (WorkerInfo $worker) : string
	{
		$limits = $worker->limits;

		$parts = array_filter([
			null !== $limits->messageLimit ? \sprintf("%d messages", $limits->messageLimit) : null,
			null !== $limits->failureLimit ? \sprintf("%d failures", $limits->failureLimit) : null,
			null !== $limits->memoryLimit ? \sprintf("%s memory", Helper::formatMemory($limits->memoryLimit)) : null,
			null !== $limits->timeLimit ? \sprintf("%s timeout", Helper::formatTime($limits->timeLimit)) : null,
		]);

		return [] !== $parts
			? implode("\n", $parts)
			: "<fg=gray>none</>";
	}
}
