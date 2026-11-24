<?php declare(strict_types=1);

namespace Tests\Torr\TaskManager\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;
use Torr\TaskManager\DependencyInjection\RegisterTaskIdentificationMiddlewareCompilerPass;
use Torr\TaskManager\Identification\TaskIdentificationMiddleware;

/**
 * @internal
 */
final class RegisterTaskIdentificationMiddlewareCompilerPassTest extends TestCase
{
	private const array DEFAULT_MIDDLEWARE = [
		["id" => "add_bus_name_stamp_middleware"],
		["id" => "custom"],
		["id" => "send_message"],
		["id" => "handle_message"],
	];

	// region Helpers

	/**
	 * @param list<string>|null $buses
	 */
	private function process (?array $buses) : ContainerBuilder
	{
		$container = new ContainerBuilder();

		foreach (["command.bus", "query.bus"] as $busId)
		{
			$container->register($busId)->addTag("messenger.bus");
			$container->setParameter("{$busId}.middleware", self::DEFAULT_MIDDLEWARE);
		}

		$container->setAlias("messenger.default_bus", "command.bus");

		if (null !== $buses)
		{
			$container->setParameter(RegisterTaskIdentificationMiddlewareCompilerPass::BUSES_PARAMETER, $buses);
		}

		(new RegisterTaskIdentificationMiddlewareCompilerPass())->process($container);

		return $container;
	}

	/**
	 * @return list<string>
	 */
	private function getMiddlewareIds (ContainerBuilder $container, string $busId) : array
	{
		/** @var list<array{id: string}> $middleware */
		$middleware = $container->getParameter("{$busId}.middleware");

		return array_column($middleware, "id");
	}

	// endregion

	public function testDefaultBus () : void
	{
		$container = $this->process(["default"]);

		self::assertSame([
			"add_bus_name_stamp_middleware",
			"custom",
			"task_manager.middleware.task_identification.command.bus",
			"send_message",
			"handle_message",
		], $this->getMiddlewareIds($container, "command.bus"));
		self::assertSame(array_column(self::DEFAULT_MIDDLEWARE, "id"), $this->getMiddlewareIds($container, "query.bus"));
		self::assertFalse($container->hasParameter(RegisterTaskIdentificationMiddlewareCompilerPass::BUSES_PARAMETER));

		$definition = $container->getDefinition("task_manager.middleware.task_identification.command.bus");
		self::assertSame(TaskIdentificationMiddleware::class, $definition->getClass());
		self::assertEquals(new Reference("command.bus.messenger.handlers_locator"), $definition->getArgument('$handlersLocator'));
	}

	public function testExplicitBuses () : void
	{
		$container = $this->process(["query.bus", "default", "command.bus"]);

		self::assertContains("task_manager.middleware.task_identification.query.bus", $this->getMiddlewareIds($container, "query.bus"));
		self::assertSame(1, array_count_values($this->getMiddlewareIds($container, "command.bus"))["task_manager.middleware.task_identification.command.bus"]);
	}

	public function testDisabled () : void
	{
		$container = $this->process([]);

		self::assertSame(array_column(self::DEFAULT_MIDDLEWARE, "id"), $this->getMiddlewareIds($container, "command.bus"));
		self::assertSame(array_column(self::DEFAULT_MIDDLEWARE, "id"), $this->getMiddlewareIds($container, "query.bus"));
	}

	public function testWithoutDefaultMiddleware () : void
	{
		$container = new ContainerBuilder();
		$container->register("command.bus")->addTag("messenger.bus");
		$container->setParameter("command.bus.middleware", [["id" => "custom"]]);
		$container->setParameter(RegisterTaskIdentificationMiddlewareCompilerPass::BUSES_PARAMETER, ["command.bus"]);

		(new RegisterTaskIdentificationMiddlewareCompilerPass())->process($container);

		self::assertSame(
			["custom", "task_manager.middleware.task_identification.command.bus"],
			$this->getMiddlewareIds($container, "command.bus"),
		);
	}

	public function testSkipsManuallyAddedMiddleware () : void
	{
		$container = new ContainerBuilder();
		$container->register("command.bus")->addTag("messenger.bus");
		$container->setParameter("command.bus.middleware", [["id" => TaskIdentificationMiddleware::class], ["id" => "handle_message"]]);
		$container->setParameter(RegisterTaskIdentificationMiddlewareCompilerPass::BUSES_PARAMETER, ["command.bus"]);

		(new RegisterTaskIdentificationMiddlewareCompilerPass())->process($container);

		self::assertSame([TaskIdentificationMiddleware::class, "handle_message"], $this->getMiddlewareIds($container, "command.bus"));
	}

	public function testUnknownBus () : void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage("unknown bus 'missing.bus'");

		$this->process(["missing.bus"]);
	}
}
