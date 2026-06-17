<?php declare(strict_types=1);

namespace Torr\TaskManager\Identification;

use Symfony\Contracts\Service\ResetInterface;
use Torr\TaskManager\Entity\TaskRun;

/**
 * @final
 */
class TaskIdentifier implements ResetInterface
{
	/** @var \WeakMap<object, string> */
	private \WeakMap $uuidMap;

	/** @var \WeakMap<object, TaskRun> */
	private \WeakMap $latestRuns;

	/**
	 */
	public function __construct ()
	{
		$this->uuidMap = new \WeakMap();
		$this->latestRuns = new \WeakMap();
	}

	/**
	 *
	 */
	public function setUuid (object $message, string $uuid) : void
	{
		$this->uuidMap[$message] = $uuid;
	}

	/**
	 *
	 */
	public function getUuid (object $message) : ?string
	{
		return $this->uuidMap[$message]
			?? null;
	}

	public function setLatestRun (object $message, TaskRun $run) : void
	{
		$this->latestRuns[$message] = $run;
	}

	public function getLatestRun (object $message) : ?TaskRun
	{
		return $this->latestRuns[$message]
			?? null;
	}

	/**
	 *
	 */
	public function reset () : void
	{
		$this->uuidMap = new \WeakMap();
		$this->latestRuns = new \WeakMap();
	}
}
