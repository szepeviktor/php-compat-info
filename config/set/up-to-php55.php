<?php declare(strict_types=1);
/**
 * Build the Container with PHP features detection up to version 5.5
 *
 * @author Laurent Laville
 * @since Release 6.5.0
 */

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->import(__DIR__ . '/up-to-php54.php');
    $containerConfigurator->import(__DIR__ . '/php55.php');
};
