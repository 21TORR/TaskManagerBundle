<?php declare(strict_types=1);

namespace Torr\TaskManager\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;
use Torr\TaskManager\Identification\TaskIdentificationMiddleware;

/**
 * Automatically adds the {@see TaskIdentificationMiddleware} to the configured messenger buses and gives every bus
 * its own instance, also if the middleware was added manually.
 *
 * Must run before the `MessengerPass`, as that one consumes the middleware parameters of the buses.
 */
final class RegisterTaskIdentificationMiddlewareCompilerPass implements CompilerPassInterface
{
	public const string BUSES_PARAMETER = ".task_manager.autoinstall_on_buses";
	private const string DEFAULT_BUS_KEYWORD = "default";
	private const string DEFAULT_BUS_ALIAS = "messenger.default_bus";

	/**
	 * @inheritDoc
	 */
	public function process (ContainerBuilder $container) : void
	{
		foreach (array_keys($container->findTaggedServiceIds("messenger.bus")) as $busId)
		{
			$this->replaceManuallyAddedMiddleware($container, $busId);
		}

		if (!$container->hasParameter(self::BUSES_PARAMETER))
		{
			return;
		}

		/** @var list<string> $buses */
		$buses = $container->getParameter(self::BUSES_PARAMETER);
		$container->getParameterBag()->remove(self::BUSES_PARAMETER);

		foreach (array_unique($buses) as $bus)
		{
			$this->registerOnBus($container, $this->resolveBusId($container, $bus));
		}
	}

	/**
	 * Replaces the shared middleware service in the manually configured middleware with the instance of the bus,
	 * as otherwise it would check the handlers of the wrong bus.
	 */
	private function replaceManuallyAddedMiddleware (ContainerBuilder $container, string $busId) : void
	{
		$parameter = "{$busId}.middleware";

		if (!$container->hasParameter($parameter))
		{
			return;
		}

		/** @var list<array{id: string, arguments?: list<mixed>}> $middleware */
		$middleware = $container->getParameter($parameter);
		$changed = false;

		foreach ($middleware as $index => $item)
		{
			if (TaskIdentificationMiddleware::class === $item["id"])
			{
				$middleware[$index]["id"] = $this->registerBusMiddlewareService($container, $busId);
				$changed = true;
			}
		}

		if ($changed)
		{
			$container->setParameter($parameter, $middleware);
		}
	}

	/**
	 * Registers the middleware instance for the given bus, as it needs to check the handlers of this bus.
	 *
	 * @return string the service id
	 */
	private function registerBusMiddlewareService (ContainerBuilder $container, string $busId) : string
	{
		$serviceId = self::getServiceId($busId);

		if (!$container->hasDefinition($serviceId))
		{
			$container->setDefinition(
				$serviceId,
				(new Definition(TaskIdentificationMiddleware::class))
					->setAutowired(true)
					->setArgument('$handlersLocator', new Reference("{$busId}.messenger.handlers_locator")),
			);
		}

		return $serviceId;
	}

	/**
	 */
	private static function getServiceId (string $busId) : string
	{
		return "task_manager.middleware.task_identification.{$busId}";
	}

	/**
	 */
	private function registerOnBus (ContainerBuilder $container, string $busId) : void
	{
		$parameter = "{$busId}.middleware";

		// the parameter is only missing if the bus was not configured via the framework bundle
		if (!$container->hasParameter($parameter))
		{
			throw new InvalidArgumentException(\sprintf(
				"Can't automatically add the task manager middleware to the bus '%s', as its middleware is not configured via `framework.messenger.buses`.",
				$busId,
			));
		}

		/** @var list<array{id: string, arguments?: list<mixed>}> $middleware */
		$middleware = $container->getParameter($parameter);
		$ids = array_column($middleware, "id");

		// skip if it was already added manually
		if (\in_array(self::getServiceId($busId), $ids, true))
		{
			return;
		}

		$serviceId = $this->registerBusMiddlewareService($container, $busId);

		// add it right before the message is sent / handled
		$position = array_search("send_message", $ids, true);

		if (false === $position)
		{
			$position = array_search("handle_message", $ids, true);
		}

		if (false === $position)
		{
			$position = \count($middleware);
		}

		array_splice($middleware, $position, 0, [["id" => $serviceId]]);
		$container->setParameter($parameter, $middleware);
	}

	/**
	 */
	private function resolveBusId (ContainerBuilder $container, string $bus) : string
	{
		if (self::DEFAULT_BUS_KEYWORD === $bus && $container->hasAlias(self::DEFAULT_BUS_ALIAS))
		{
			return (string) $container->getAlias(self::DEFAULT_BUS_ALIAS);
		}

		$busIds = array_keys($container->findTaggedServiceIds("messenger.bus"));

		if (!\in_array($bus, $busIds, true))
		{
			throw new InvalidArgumentException(\sprintf(
				"Can't automatically add the task manager middleware to the unknown bus '%s'. Available buses: '%s'.",
				$bus,
				implode("', '", [self::DEFAULT_BUS_KEYWORD, ...$busIds]),
			));
		}

		return $bus;
	}
}
