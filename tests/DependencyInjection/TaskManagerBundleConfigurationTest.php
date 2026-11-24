<?php declare(strict_types=1);

namespace Tests\Torr\TaskManager\DependencyInjection;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Torr\TaskManager\DependencyInjection\TaskManagerBundleConfiguration;

/**
 * @internal
 */
final class TaskManagerBundleConfigurationTest extends TestCase
{
	/**
	 *
	 */
	public static function provideAutoinstallOnBuses () : iterable
	{
		yield "default" => [[], ["default"]];
		yield "null" => [["autoinstall_on_buses" => null], []];
		yield "empty" => [["autoinstall_on_buses" => []], []];
		yield "explicit" => [["autoinstall_on_buses" => ["command.bus", "default"]], ["command.bus", "default"]];
	}

	/**
	 * @param array<string, mixed> $config
	 * @param list<string>         $expected
	 */
	#[DataProvider("provideAutoinstallOnBuses")]
	public function testAutoinstallOnBuses (array $config, array $expected) : void
	{
		$processed = (new Processor())->processConfiguration(new TaskManagerBundleConfiguration(), [$config]);

		self::assertSame($expected, $processed["autoinstall_on_buses"]);
	}
}
