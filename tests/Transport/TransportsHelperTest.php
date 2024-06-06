<?php declare(strict_types=1);

namespace Tests\Torr\TaskManager\Transport;

use Symfony\Component\DependencyInjection\ServiceLocator;
use Torr\TaskManager\Config\BundleConfig;
use PHPUnit\Framework\TestCase;
use Torr\TaskManager\Transport\TransportsHelper;

class TransportsHelperTest extends TestCase
{
	public function testOrderedTransports () : void
	{
		// these should be ordered like this at the top
		$config = new BundleConfig([
			"very_urgent",
			// urgent should be filtered out as it is not defined
			"urgent",
			"app",
		]);

		// the ones not from the config should keep the order
		$locator = new ServiceLocator([
			"test" => fn () => null,
			"app" => fn () => null,
			"very_urgent" => fn () => null,
			"test2" => fn () => null,
		]);

		$helper = new TransportsHelper($locator, $config);

		self::assertEquals([
			"very_urgent",
			"app",
			"test",
			"test2",
		], $helper->getOrderedQueueNames());
	}
}
