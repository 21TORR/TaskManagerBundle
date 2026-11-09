<?php declare(strict_types=1);

namespace Torr\TaskManager\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Sync\SyncTransport;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Torr\Cli\Console\Style\TorrStyle;
use Torr\TaskManager\Config\BundleConfig;
use Torr\TaskManager\Identification\TaskIdStamp;
use Torr\TaskManager\Task\Task;
use Torr\TaskManager\Transport\TransportsHelper;

#[AsCommand(
	"task-manager:clear-queue",
	description: "Removes all queued tasks from the given queues",
)]
final class ClearQueueCommand extends Command
{
	/**
	 * The number of messages that are fetched (and removed) at once, so that huge queues don't need to be
	 * loaded into memory completely.
	 */
	private const int BATCH_SIZE = 500;
	private const string STOP_WORKERS_COMMAND = "messenger:stop-workers";
	private const string REASON_FAILURE = "failure queue, clear it explicitly or via `messenger:failed:remove`";

	/**
	 */
	public function __construct (
		private readonly TransportsHelper $transportsHelper,
		private readonly BundleConfig $bundleConfig,
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
			->addArgument("queues", InputArgument::IS_ARRAY | InputArgument::OPTIONAL, "The names of the queues to clear")
			->addOption("all", null, InputOption::VALUE_NONE, "Clear all queues (except failure and scheduler queues)")
			->addOption("force", "f", InputOption::VALUE_NONE, "Actually remove the queued tasks and restart the workers")
			->addOption("limit", null, InputOption::VALUE_REQUIRED, "The max number of tasks to list per queue", "50")
			->setHelp(
				<<<'HELP'
					Without <info>--force</> this command only lists the tasks that would be removed.
					If neither queue names nor <info>--all</> are passed, you can interactively select the queues.

					<info>bin/console %command.name% queue_a queue_b</>
					<info>bin/console %command.name% --all --force</>

					With <info>--force</> the tasks are removed and all workers are restarted afterwards,
					so that no worker keeps on working on prefetched tasks.
					HELP,
			);
	}

	/**
	 *
	 */
	#[\Override]
	protected function execute (InputInterface $input, OutputInterface $output) : int
	{
		$io = new TorrStyle($input, $output);
		$io->title("Task Manager: Clear Queue");

		$force = (bool) $input->getOption("force");
		$limit = filter_var($input->getOption("limit"), \FILTER_VALIDATE_INT, ["options" => ["min_range" => 0]]);

		if (false === $limit)
		{
			$io->error("The limit must be a non-negative integer.");

			return self::FAILURE;
		}

		$resolved = $this->resolveReceivers($input, $io);

		if (null === $resolved)
		{
			return self::FAILURE;
		}

		[$receivers, $skipped] = $resolved;

		if (empty($receivers))
		{
			$io->comment("No queues to clear.");
			$this->printSkippedQueues($io, $skipped);

			return self::SUCCESS;
		}

		// only failure queues are skipped deliberately, so mention them to not surprise the user
		$this->printSkippedQueues($io, array_filter(
			$skipped,
			static fn (string $reason) => self::REASON_FAILURE === $reason,
		));

		if (!$force)
		{
			foreach ($receivers as $queueName => $receiver)
			{
				$this->listQueue($io, $queueName, $receiver, $limit);
			}

			$io->newLine();
			$io->note("Nothing was removed. Pass --force to remove the tasks and restart the workers.");

			return self::SUCCESS;
		}

		$total = 0;

		foreach ($receivers as $queueName => $receiver)
		{
			$removed = $this->clearQueue($io, $receiver);
			$total += $removed;

			$io->writeln(\sprintf(
				"• Removed <fg=magenta>%d</> task%s from queue <fg=yellow>%s</>",
				$removed,
				1 !== $removed ? "s" : "",
				$queueName,
			));
		}

		$io->success(\sprintf("Removed %d task%s.", $total, 1 !== $total ? "s" : ""));

		return $this->restartWorkers($io, $output);
	}

	/**
	 * Returns the receivers to clear (indexed by queue name) and the skipped queues (with the reason why they were
	 * skipped). Returns null on invalid input.
	 *
	 * @return array{array<string, ReceiverInterface>, array<string, string>}|null
	 */
	private function resolveReceivers (InputInterface $input, TorrStyle $io) : ?array
	{
		/** @var string[] $queueNames */
		$queueNames = $input->getArgument("queues");
		$all = (bool) $input->getOption("all");

		if ($all && !empty($queueNames))
		{
			$io->error("Either pass the names of the queues to clear or use --all, not both.");

			return null;
		}

		if ($all)
		{
			// failure transports must be cleared explicitly (or via `messenger:failed:remove`)
			return $this->getClearableReceivers(skipFailureQueues: true);
		}

		if (empty($queueNames))
		{
			if (!$input->isInteractive())
			{
				$io->error("Either pass the names of the queues to clear or use --all.");

				return null;
			}

			return $this->askForReceivers($io);
		}

		$transports = $this->transportsHelper->getAllTransports();
		$unknown = array_diff($queueNames, array_keys($transports));

		if (!empty($unknown))
		{
			$io->error(\sprintf(
				"Unknown queue(s): %s. Available queues: %s",
				implode(", ", $unknown),
				implode(", ", $this->transportsHelper->getOrderedQueueNames()),
			));

			return null;
		}

		$receivers = [];

		foreach (array_unique($queueNames) as $queueName)
		{
			$reason = $this->getSkipReason($queueName, $transports[$queueName]);

			if (null !== $reason)
			{
				$io->error(\sprintf("Can't clear queue '%s': %s.", $queueName, $reason));

				return null;
			}

			$receivers[$queueName] = $transports[$queueName];
		}

		return [$receivers, []];
	}

	/**
	 * Lets the user interactively select the queues to clear
	 *
	 * @return array{array<string, ReceiverInterface>, array<string, string>}
	 */
	private function askForReceivers (TorrStyle $io) : array
	{
		[$receivers, $skipped] = $this->getClearableReceivers(skipFailureQueues: false);

		if (empty($receivers))
		{
			return [[], $skipped];
		}

		$choices = [];

		foreach ($receivers as $queueName => $receiver)
		{
			$choices[$queueName] = $receiver instanceof MessageCountAwareInterface
				? \sprintf("%d task%s", $receiver->getMessageCount(), 1 !== $receiver->getMessageCount() ? "s" : "")
				: "unknown number of tasks";
		}

		$question = new ChoiceQuestion("Which queues do you want to clear? (comma-separated)", $choices);
		$question->setMultiselect(true);

		/** @var list<string> $selected */
		$selected = $io->askQuestion($question);

		return [array_intersect_key($receivers, array_flip($selected)), []];
	}

	/**
	 * Returns all queues that can be cleared (indexed by queue name and ordered by priority)
	 * and the skipped queues (with the reason why they were skipped).
	 *
	 * @return array{array<string, ReceiverInterface>, array<string, string>}
	 */
	private function getClearableReceivers (bool $skipFailureQueues) : array
	{
		$transports = $this->transportsHelper->getAllTransports();
		$receivers = [];
		$skipped = [];

		foreach ($this->transportsHelper->getOrderedQueueNames() as $queueName)
		{
			$reason = $this->getSkipReason($queueName, $transports[$queueName]);

			if (null === $reason && $skipFailureQueues && \in_array($queueName, $this->bundleConfig->failureTransports, true))
			{
				$reason = self::REASON_FAILURE;
			}

			if (null !== $reason)
			{
				$skipped[$queueName] = $reason;
				continue;
			}

			$receivers[$queueName] = $transports[$queueName];
		}

		return [$receivers, $skipped];
	}

	/**
	 * Returns the reason why the queue can't be cleared, or null if it can be cleared
	 */
	private function getSkipReason (string $queueName, TransportInterface $transport) : ?string
	{
		return match (true)
		{
			TransportsHelper::INTERNAL_TRANSPORT_NAME === $queueName => "internal queue of the task manager",
			str_starts_with($queueName, "scheduler_") => "scheduler queue",
			$transport instanceof SyncTransport => "uses a sync transport, tasks are handled directly and never queued",
			default => null,
		};
	}

	/**
	 * Prints the skipped queues, grouped by the reason why they were skipped
	 *
	 * @param array<string, string> $skipped
	 */
	private function printSkippedQueues (TorrStyle $io, array $skipped) : void
	{
		if (empty($skipped))
		{
			return;
		}

		$byReason = [];

		foreach ($skipped as $queueName => $reason)
		{
			$byReason[$reason][] = $queueName;
		}

		$io->writeln("Skipped queues:");

		foreach ($byReason as $reason => $queueNames)
		{
			$io->writeln(\sprintf(
				"• <fg=yellow>%s</>: %s",
				implode("</>, <fg=yellow>", $queueNames),
				$reason,
			));
		}

		$io->newLine();
	}

	/**
	 * Lists (up to the limit) the tasks in the given queue
	 */
	private function listQueue (TorrStyle $io, string $queueName, ReceiverInterface $receiver, int $limit) : void
	{
		$io->section(\sprintf("Queue: %s", $queueName));

		$count = $receiver instanceof MessageCountAwareInterface
			? $receiver->getMessageCount()
			: null;

		if (0 === $count)
		{
			$io->comment("The queue is empty.");

			return;
		}

		if (null !== $count)
		{
			$io->writeln(\sprintf(
				"<fg=magenta>%d</> task%s would be removed.",
				$count,
				1 !== $count ? "s" : "",
			));
		}

		if (!$receiver instanceof ListableReceiverInterface)
		{
			$io->comment("This queue doesn't support listing its tasks.");

			return;
		}

		if (0 === $limit)
		{
			return;
		}

		$rows = [];

		foreach ($receiver->all($limit + 1) as $envelope)
		{
			$rows[] = $this->formatEnvelope($envelope);
		}

		if (empty($rows))
		{
			$io->comment("The queue is empty.");

			return;
		}

		$hasMore = \count($rows) > $limit;
		$rows = \array_slice($rows, 0, $limit);

		$io->table(["Id", "Task", "Task Id"], $rows);

		if ($hasMore)
		{
			$io->comment(\sprintf(
				"Only the first %d tasks are listed, use --limit to list more.",
				$limit,
			));
		}
	}

	/**
	 * Removes all tasks from the queue, in batches
	 */
	private function clearQueue (TorrStyle $io, ReceiverInterface $receiver) : int
	{
		$count = $receiver instanceof MessageCountAwareInterface
			? $receiver->getMessageCount()
			: null;
		$removed = 0;
		$seenIds = [];

		$progress = $io->createProgressBar($count ?? 0);
		$progress->setMessage("");
		$progress->start();

		while (true)
		{
			$batch = $receiver instanceof ListableReceiverInterface
				? $receiver->all(self::BATCH_SIZE)
				: $receiver->get();
			$removedInBatch = 0;

			foreach ($batch as $envelope)
			{
				$id = $envelope->last(TransportMessageIdStamp::class)?->getId();

				// safeguard against transports that keep on returning the same (already rejected) messages
				if (null !== $id)
				{
					$key = \is_scalar($id) ? (string) $id : serialize($id);

					if (isset($seenIds[$key]))
					{
						continue;
					}

					$seenIds[$key] = true;
				}

				$receiver->reject($envelope);
				++$removed;
				++$removedInBatch;
				$progress->advance();
			}

			if (0 === $removedInBatch)
			{
				break;
			}
		}

		$progress->finish();
		$progress->clear();

		return $removed;
	}

	/**
	 * Signals all workers to restart after they finished their current task, by running `messenger:stop-workers`.
	 */
	private function restartWorkers (TorrStyle $io, OutputInterface $output) : int
	{
		$application = $this->getApplication();

		if (null === $application || !$application->has(self::STOP_WORKERS_COMMAND))
		{
			$io->warning(\sprintf("Could not restart the workers, as the '%s' command is not available.", self::STOP_WORKERS_COMMAND));

			return self::FAILURE;
		}

		return $application
			->find(self::STOP_WORKERS_COMMAND)
			->run(new ArrayInput([]), $output);
	}

	/**
	 * @return array{string, string, string}
	 */
	private function formatEnvelope (Envelope $envelope) : array
	{
		$message = $envelope->getMessage();
		$id = $envelope->last(TransportMessageIdStamp::class)?->getId();

		$label = $message instanceof Task
			? \sprintf("%s <fg=gray>(%s)</>", $message->getMetaData()->label, $message::class)
			: $message::class;

		return [
			\is_scalar($id) ? (string) $id : "—",
			$label,
			$envelope->last(TaskIdStamp::class)->taskId ?? "—",
		];
	}
}
