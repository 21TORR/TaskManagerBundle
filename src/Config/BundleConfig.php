<?php declare(strict_types=1);

namespace Torr\TaskManager\Config;

final class BundleConfig
{
	/**
	 */
	public function __construct (
		/** @var string[] */
		public readonly array $sortedQueues,
		/** @var string[] */
		public readonly array $failureTransports = [],
	) {}
}
