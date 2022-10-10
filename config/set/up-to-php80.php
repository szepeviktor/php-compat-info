<?php declare(strict_types=1);
/**
 * Build the Container with PHP features detection up to version 8.0
 *
 * @author Laurent Laville
 * @since Release 6.5.0
 */

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->import(__DIR__ . '/up-to-php74.php');
    $containerConfigurator->import(__DIR__ . '/php80.php');
};
