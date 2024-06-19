<?php declare(strict_types=1);

namespace Torr\TaskManager\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Torr\TaskManager\Transport\TransportsHelper;

#[AsCommand(
	"task-manager:messenger:queue-names",
	description: "Helper command to list the (ordered) list of all queue names",
)]
final class MessengerQueueNamesCommand extends Command
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
	 *
	 */
	#[\Override]
	protected function configure () : void
	{
		$this
			->setHelp(
				<<<'HELP'
					This command lists the by priority ordered list of all messenger queue names, you 
					can pass this directly into the messenger queue command:

					<info>bin/console messenger:consume $(bin/console task-manager:messenger:queue-names)</>
					HELP,
			);
	}

	/**
	 *
	 */
	#[\Override]
	protected function execute (InputInterface $input, OutputInterface $output) : int
	{
		$output->writeln(implode(
			" ",
			$this->transportsHelper->getOrderedQueueNames(),
		));

		return self::SUCCESS;
	}
}
