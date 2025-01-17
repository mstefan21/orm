<?php declare(strict_types = 1);

namespace Nettrine\ORM\DI;

use Doctrine\Common\Proxy\AbstractProxyFactory;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager as DoctrineEntityManager;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Nette\DI\Definitions\Statement;
use Nette\DI\Helpers;
use Nette\InvalidArgumentException;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Nettrine\ORM\EntityManagerDecorator;
use Nettrine\ORM\Exception\Logical\InvalidStateException;
use Nettrine\ORM\ManagerRegistry;
use Nettrine\ORM\Mapping\ContainerEntityListenerResolver;
use stdClass;

/**
 * @property-read stdClass $config
 */
final class OrmExtension extends AbstractExtension
{

	public function getConfigSchema(): Schema
	{
		$parameters = $this->getContainerBuilder()->parameters;
		$proxyDir = isset($parameters['tempDir']) ? $parameters['tempDir'] . '/proxies' : null;

		return Expect::structure([
			'entityManagerDecoratorClass' => Expect::string(EntityManagerDecorator::class),
			'configurationClass' => Expect::string(Configuration::class),
			'configuration' => Expect::structure([
				'proxyDir' => Expect::string($proxyDir)->nullable(),
				'autoGenerateProxyClasses' => Expect::anyOf(Expect::int(), Expect::bool(), Expect::type(Statement::class))->default(AbstractProxyFactory::AUTOGENERATE_FILE_NOT_EXISTS),
				'proxyNamespace' => Expect::string('Nettrine\Proxy')->nullable(),
				'metadataDriverImpl' => Expect::string(),
				'entityNamespaces' => Expect::listOf('string'),
				'customStringFunctions' => Expect::array(),
				'customNumericFunctions' => Expect::array(),
				'customDatetimeFunctions' => Expect::array(),
				'customHydrationModes' => Expect::array(),
				'classMetadataFactoryName' => Expect::string(),
				'defaultRepositoryClassName' => Expect::string(),
				'namingStrategy' => Expect::string(UnderscoreNamingStrategy::class)->nullable(),
				'quoteStrategy' => Expect::type(Statement::class),
				'entityListenerResolver' => Expect::string(),
				'repositoryFactory' => Expect::string(),
				'defaultQueryHints' => Expect::array(),
			]),
		]);
	}

	public function loadConfiguration(): void
	{
		$this->loadDoctrineConfiguration();
		$this->loadEntityManagerConfiguration();
	}

	public function loadDoctrineConfiguration(): void
	{
		$builder = $this->getContainerBuilder();
		$globalConfig = $this->config;
		$config = $globalConfig->configuration;

		// @validate configuration class is subclass of origin one
		$configurationClass = $globalConfig->configurationClass;
		if (!is_a($configurationClass, Configuration::class, true)) {
			throw new InvalidArgumentException('Configuration class must be subclass of ' . Configuration::class . ', ' . $configurationClass . ' given.');
		}

		$configuration = $builder->addDefinition($this->prefix('configuration'))
			->setType($configurationClass);

		if ($config->proxyDir !== null) {
			$configuration->addSetup('setProxyDir', [Helpers::expand($config->proxyDir, $builder->parameters)]);
		}

		if (is_bool($config->autoGenerateProxyClasses)) {
			$configuration->addSetup('setAutoGenerateProxyClasses', [
				$config->autoGenerateProxyClasses === true ? AbstractProxyFactory::AUTOGENERATE_FILE_NOT_EXISTS : AbstractProxyFactory::AUTOGENERATE_NEVER,
			]);
		} elseif (is_int($config->autoGenerateProxyClasses)) {
			$configuration->addSetup('setAutoGenerateProxyClasses', [$config->autoGenerateProxyClasses]);
		}

		if ($config->proxyNamespace !== null) {
			$configuration->addSetup('setProxyNamespace', [$config->proxyNamespace]);
		}

		if ($config->metadataDriverImpl !== null) {
			$configuration->addSetup('setMetadataDriverImpl', [$config->metadataDriverImpl]);
		}

		if ($config->entityNamespaces !== []) {
			$configuration->addSetup('setEntityNamespaces', [$config->entityNamespaces]);
		}

		// Custom functions
		$configuration
			->addSetup('setCustomStringFunctions', [$config->customStringFunctions])
			->addSetup('setCustomNumericFunctions', [$config->customNumericFunctions])
			->addSetup('setCustomDatetimeFunctions', [$config->customDatetimeFunctions])
			->addSetup('setCustomHydrationModes', [$config->customHydrationModes]);

		if ($config->classMetadataFactoryName !== null) {
			$configuration->addSetup('setClassMetadataFactoryName', [$config->classMetadataFactoryName]);
		}

		if ($config->defaultRepositoryClassName !== null) {
			$configuration->addSetup('setDefaultRepositoryClassName', [$config->defaultRepositoryClassName]);
		}

		if ($config->namingStrategy !== null) {
			$configuration->addSetup('setNamingStrategy', [new Statement($config->namingStrategy)]);
		}

		if ($config->quoteStrategy !== null) {
			$configuration->addSetup('setQuoteStrategy', [$config->quoteStrategy]);
		}

		if ($config->entityListenerResolver !== null) {
			$configuration->addSetup('setEntityListenerResolver', [$config->entityListenerResolver]);
		} else {
			$builder->addDefinition($this->prefix('entityListenerResolver'))
				->setType(ContainerEntityListenerResolver::class);
			$configuration->addSetup('setEntityListenerResolver', [$this->prefix('@entityListenerResolver')]);
		}

		if ($config->repositoryFactory !== null) {
			$configuration->addSetup('setRepositoryFactory', [$config->repositoryFactory]);
		}

		if ($config->defaultQueryHints !== []) {
			$configuration->addSetup('setDefaultQueryHints', [$config->defaultQueryHints]);
		}
	}

	public function loadEntityManagerConfiguration(): void
	{
		$builder = $this->getContainerBuilder();
		$config = $this->config;

		// @validate entity manager decorator has a real class
		$entityManagerDecoratorClass = $config->entityManagerDecoratorClass;
		if (!class_exists($entityManagerDecoratorClass)) {
			throw new InvalidStateException(sprintf('EntityManagerDecorator class "%s" not found', $entityManagerDecoratorClass));
		}

		// Entity Manager
		$original = $builder->addDefinition($this->prefix('entityManager'))
			->setType(DoctrineEntityManager::class)
			->setFactory(DoctrineEntityManager::class . '::create', [
				$builder->getDefinitionByType(Connection::class), // Nettrine/DBAL
				$this->prefix('@configuration'),
			])
			->setAutowired(false);

		// Entity Manager Decorator
		$builder->addDefinition($this->prefix('entityManagerDecorator'))
			->setFactory($entityManagerDecoratorClass, [$original]);

		// ManagerRegistry
		$builder->addDefinition($this->prefix('managerRegistry'))
			->setType(ManagerRegistry::class)
			->setArguments([
				$builder->getDefinitionByType(Connection::class),
				$this->prefix('@entityManagerDecorator'),
			]);
	}

}
