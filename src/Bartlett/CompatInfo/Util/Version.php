<?php declare(strict_types=1);

/**
 * Helper class to format version string.
 *
 * PHP version 7
 *
 * @category PHP
 * @package  PHP_CompatInfo
 * @author   Laurent Laville <pear@laurent-laville.org>
 * @license  https://opensource.org/licenses/BSD-3-Clause The 3-Clause BSD License
 */

namespace Bartlett\CompatInfo\Util;

/**
 * Helper class to format version string.
 *
 * @category PHP
 * @package  PHP_CompatInfo
 * @author   Laurent Laville <pear@laurent-laville.org>
 * @license  https://opensource.org/licenses/BSD-3-Clause The 3-Clause BSD License
 * @since    Class available since Release 4.0.0-alpha3+1
 */
class Version
{
    public static function ext(array $versions): string
    {
        return empty($versions['ext.max'])
            ? $versions['ext.min']
            : $versions['ext.min'] . ' => ' . $versions['ext.max'];
    }

    public static function php(array $versions): string
    {
        return empty($versions['php.max'])
            ? $versions['php.min']
            : $versions['php.min'] . ' => ' . $versions['php.max'];
    }

    public static function all(array $versions): string
    {
        if (!empty($versions['php.all'])) {
            if (version_compare($versions['php.all'], $versions['php.min'], '>')) {
                return $versions['php.all'];
            }
        }
        return '';
    }

    public static function deprecated(array $versions): string
    {
        if (isset($versions['deprecated'])) {
            return $versions['deprecated'];
        }
        return '';
    }
}
