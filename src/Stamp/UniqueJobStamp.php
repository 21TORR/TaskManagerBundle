<?php declare(strict_types=1);

namespace Torr\TaskManager\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

final class UniqueJobStamp implements StampInterface
{
	public function __construct (
		public readonly string $jobId,
	) {}
}
