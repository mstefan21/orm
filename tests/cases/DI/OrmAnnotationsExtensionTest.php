<?php declare(strict_types = 1);

namespace Tests\Nettrine\ORM\Cases\DI;

use Doctrine\Common\Cache\FilesystemCache;
use Nette\DI\Compiler;
use Nette\DI\Container;
use Nette\DI\ContainerLoader;
use Nettrine\DBAL\DI\DbalExtension;
use Nettrine\ORM\DI\OrmAnnotationsExtension;
use Nettrine\ORM\DI\OrmExtension;
use Tests\Nettrine\ORM\Cases\TestCase;

final class OrmAnnotationsExtensionTest extends TestCase
{

	public function testDefaultCache(): void
	{
		$loader = new ContainerLoader(TEMP_PATH, true);
		$class = $loader->load(function (Compiler $compiler): void {
			$compiler->addExtension('dbal', new DbalExtension());
			$compiler->addExtension('orm', new OrmExtension());
			$compiler->addExtension('orm.annotations', new OrmAnnotationsExtension());
			$compiler->addConfig([
				'parameters' => [
					'tempDir' => TEMP_PATH,
					'appDir' => __DIR__,
				],
			]);
		}, __CLASS__ . __METHOD__);

		/** @var Container $container */
		$container = new $class();

		self::assertInstanceOf(FilesystemCache::class, $container->getService('orm.annotations.annotationsCache'));
	}

	/**
	 * @expectedException \Nettrine\ORM\Exception\Logical\InvalidStateException
	 * @expectedExceptionMessage Cache or defaultCache must be provided
	 */
	public function testNoCache(): void
	{
		$loader = new ContainerLoader(TEMP_PATH, true);
		$class = $loader->load(function (Compiler $compiler): void {
			$compiler->addExtension('dbal', new DbalExtension());
			$compiler->addExtension('orm', new OrmExtension());
			$compiler->addExtension('orm.annotations', new OrmAnnotationsExtension());
			$compiler->addConfig([
				'parameters' => [
					'tempDir' => TEMP_PATH,
					'appDir' => __DIR__,
				],
				'orm.annotations' => [
					'cache' => null,
					'defaultCache' => null,
				],
			]);
		}, __CLASS__ . __METHOD__);

		/** @var Container $container */
		$container = new $class();
	}

}
