<?php declare(strict_types=1);

namespace Torr\TaskManager\Identification;

use Symfony\Contracts\Service\ResetInterface;

/**
 * @final
 */
class TaskIdentifier implements ResetInterface
{
	/** @var \WeakMap<object, string> */
	private \WeakMap $uuidMap;

	/**
	 */
	public function __construct ()
	{
		$this->uuidMap = new \WeakMap();
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

	/**
	 *
	 */
	public function reset () : void
	{
		$this->uuidMap = new \WeakMap();
	}
}
