<?php declare(strict_types=1);

namespace Tests\Torr\TaskManager\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Torr\TaskManager\Config\BundleConfig;
use Torr\TaskManager\Transport\TransportsHelper;

/**
 * @internal
 */
final class TransportsHelperTest extends TestCase
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
			"test" => static fn () => null,
			"app" => static fn () => null,
			"very_urgent" => static fn () => null,
			"test2" => static fn () => null,
		]);

		$helper = new TransportsHelper($locator, $config);

		self::assertSame([
			"very_urgent",
			"app",
			"test",
			"test2",
		], $helper->getOrderedQueueNames());
	}
}
