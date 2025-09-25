<?php declare(strict_types=1);

namespace Torr\TaskManager\App\AppValidator;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Sender\SendersLocatorInterface;
use Torr\Hosting\Event\ValidateAppEvent;
use Torr\TaskManager\Config\BundleConfig;

/**
 * @final
 */
readonly class TaskManagerAppValidator
{
	/**
	 */
	public function __construct (
		private BundleConfig $bundleConfig,
		#[Autowire(service: "messenger.senders_locator")]
		private SendersLocatorInterface $sendersLocator,
	) {}

	/**
	 */
	#[AsEventListener]
	public function onValidateApp (ValidateAppEvent $event) : void
	{
		$io = $event->io;
		$io->section("Task Manager: Check that all Tasks have Transports");

		$taskClasses = $this->bundleConfig->taskClasses;
		$io->comment(\sprintf(
			"Found <fg=blue>%d %s</>:",
			\count($taskClasses),
			1 !== \count($taskClasses) ? "tasks" : "task",
		));

		$allHaveSenders = true;

		foreach ($taskClasses as $taskClass)
		{
			$task = new \ReflectionClass($taskClass)->newInstanceWithoutConstructor();
			$envelope = Envelope::wrap($task);

			$senders = iterator_to_array($this->sendersLocator->getSenders($envelope));
			$taskHasSenders = [] !== $senders;

			$io->writeln(\sprintf(
				"• %s ... %s",
				$taskClass,
				$taskHasSenders ? "<fg=green>ok</>" : "<fg=red>not mapped</>",
			));

			$allHaveSenders = $allHaveSenders && $taskHasSenders;
		}

		if ($allHaveSenders)
		{
			$io->success("All tasks configured correctly.");

			return;
		}

		$io->error("Not all tasks are configured correctly.");
		$event->markAppAsInvalid("Task Manager: not all tasks have routing");
	}
}
