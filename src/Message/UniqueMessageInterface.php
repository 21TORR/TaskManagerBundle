<?php declare(strict_types=1);

namespace Torr\TaskManager\Message;

/**
 * If your message implements this interface, you can return the unique job id in your message directly
 * instead of passing it manually.
 */
interface UniqueMessageInterface
{
	/**
	 * Returns the job id for this message
	 */
	public function getJobId () : ?string;
}
