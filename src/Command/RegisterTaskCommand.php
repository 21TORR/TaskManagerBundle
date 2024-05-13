<?php declare(strict_types=1);

namespace Torr\TaskManager\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Torr\Cli\Console\Style\TorrStyle;

#[AsCommand("task-manager:register")]
final class RegisterTaskCommand extends Command
{
	/**
	 */
	public function __construct ()
	{
		parent::__construct();
	}

	/**
	 * @inheritDoc
	 */
	protected function configure ()
	{
		$this
			->setDescription("Registers a given task.")
			->addArgument("task", InputArgument::IS_ARRAY | InputArgument::OPTIONAL, "The tasks to register");
	}

	/**
	 * @inheritDoc
	 */
	protected function execute (InputInterface $input, OutputInterface $output)
	{
		$io = new TorrStyle($input, $output);
		$io->title("Task Manager: Register Task");

		return 0;
	}
}
