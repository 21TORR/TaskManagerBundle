<?php declare(strict_types=1);

namespace Torr\TaskManager\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;
use Torr\TaskManager\Identification\TaskIdentificationMiddleware;

/**
 * Automatically adds the {@see TaskIdentificationMiddleware} to the configured messenger buses.
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
		$serviceId = "task_manager.middleware.task_identification.{$busId}";
		$ids = array_column($middleware, "id");

		// skip if it was already added manually
		if (\in_array(TaskIdentificationMiddleware::class, $ids, true) || \in_array($serviceId, $ids, true))
		{
			return;
		}

		// every bus needs its own instance, as it needs to check the handlers of this bus
		$container->setDefinition(
			$serviceId,
			(new Definition(TaskIdentificationMiddleware::class))
				->setAutowired(true)
				->setArgument('$handlersLocator', new Reference("{$busId}.messenger.handlers_locator")),
		);

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
