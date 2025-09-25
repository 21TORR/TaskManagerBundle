<?php declare(strict_types=1);

namespace Torr\TaskManager\Config;

final readonly class BundleConfig
{
	/**
	 */
	public function __construct (
		/** @var string[] */
		public array $sortedQueues,
		/** @var string[] */
		public array $failureTransports = [],
		/** @var class-string[] */
		public array $taskClasses = [],
	) {}
}
