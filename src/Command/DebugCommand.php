<?php declare(strict_types=1);

namespace Torr\TaskManager\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Torr\Cli\Console\Style\TorrStyle;
use Torr\TaskManager\Transport\TransportsHelper;

#[AsCommand("task-manager:debug")]
final class DebugCommand extends Command
{
	/**
	 */
	public function __construct (
		private readonly TransportsHelper $transportsHelper,
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

		$io->section("Detected Queues");
		$io->listing($this->transportsHelper->getOrderedQueueNames());

		return self::SUCCESS;
	}
}
