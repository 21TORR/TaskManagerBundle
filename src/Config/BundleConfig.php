<?php declare(strict_types=1);

namespace Torr\TaskManager\Config;

final class BundleConfig
{
	/**
	 */
	public function __construct (
		public readonly array $sortedQueues,
		public readonly array $failureTransports = [],
	) {}
}
