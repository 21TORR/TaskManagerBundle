<?php declare(strict_types=1);

namespace Torr\TaskManager\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Torr\Cli\Console\Style\TorrStyle;
use Torr\TaskManager\Transport\TransportsHelper;

/**
 * @final
 */
#[AsCommand("task-manager:run-worker")]
class RunWorkerCommand extends Command
{
	/**
	 */
	public function __construct (
		private readonly TransportsHelper $transportsHelper,
	) {
		parent::__construct();
	}

	/**
	 *
	 */
	#[\Override]
	protected function configure () : void
	{
		$this
			->addOption(
				'limit',
				'l',
				InputOption::VALUE_REQUIRED,
				'Limit the number of received messages',
			)
			->addOption(
				'time-limit',
				't',
				InputOption::VALUE_REQUIRED,
				'The time limit in seconds the worker can handle new messages',
			)
			->addOption(
				'failure-limit',
				'f',
				InputOption::VALUE_REQUIRED,
				'The number of failed messages the worker can consume',
			)
			->addOption(
				'memory-limit',
				'm',
				InputOption::VALUE_REQUIRED,
				'The memory limit the worker can consume',
			);
	}

	/**
	 */
	public function execute (InputInterface $input, OutputInterface $output) : int
	{
		$io = new TorrStyle($input, $output);
		$io->title("Task Manager: Run Worker");
		$io->comment("Starting task workers for all registered queues.");

		if ($this->transportsHelper->hasSyncTransport())
		{
			$io->caution("The app is using sync transports: that means that registered tasks are directly worked on and not in this worker. These queues are automatically filtered in this worker by Symfony.");
		}

		$limits = array_filter([
			'--limit' => $input->getOption("limit"),
			'--time-limit' => $input->getOption("time-limit"),
			'--failure-limit' => $input->getOption("failure-limit"),
			'--memory-limit' => $input->getOption("memory-limit"),
		]);

		// if no limits are set, default to 10 messages
		if (empty($limits))
		{
			$limits["--limit"] = 10;
		}

		$messengerConsumeArguments = new ArrayInput(array_filter([
			'command' => 'messenger:consume',
			'receivers' => $this->transportsHelper->getOrderedQueueNames(),
			...$limits,
		]));

		// disable interactive behavior for the greet command
		$messengerConsumeArguments->setInteractive(false);

		$application = $this->getApplication();
		\assert(null !== $application);

		return $application->doRun($messengerConsumeArguments, $output);
	}
}
