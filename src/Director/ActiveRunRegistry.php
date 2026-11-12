<?php declare(strict_types=1);

namespace Torr\TaskManager\Director;

use Symfony\Contracts\Service\ResetInterface;

/**
 * Keeps track of the runs of the messages that are currently being handled.
 *
 * @internal
 */
final class ActiveRunRegistry implements ResetInterface
{
	/** @var \WeakMap<object, ActiveRun> */
	private \WeakMap $activeRuns;

	/**
	 */
	public function __construct ()
	{
		$this->activeRuns = new \WeakMap();
	}

	/**
	 *
	 */
	public function get (object $message) : ?ActiveRun
	{
		return $this->activeRuns[$message]
			?? null;
	}

	/**
	 * Sets (or removes, if null is passed) the active run of the message.
	 */
	public function set (object $message, ?ActiveRun $activeRun) : void
	{
		if (null === $activeRun)
		{
			unset($this->activeRuns[$message]);

			return;
		}

		$this->activeRuns[$message] = $activeRun;
	}

	/**
	 *
	 */
	#[\Override]
	public function reset () : void
	{
		$this->activeRuns = new \WeakMap();
	}
}
