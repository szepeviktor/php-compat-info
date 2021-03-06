<?php declare(strict_types=1);

namespace Bartlett\CompatInfo\Sniffs\Classes;

use Bartlett\CompatInfo\Sniffs\SniffAbstract;

use PhpParser\Node;

/**
 * Class method declarations
 *
 * @link https://www.php.net/manual/en/language.oop5.visibility.php#language.oop5.visiblity-methods
 *
 * @see tests/Sniffs/MethodDeclarationSniffTest
 * @since Class available since Release 5.4.0
 */
final class MethodDeclarationSniff extends SniffAbstract
{
    /**
     * {@inheritdoc}
     */
    public function enterNode(Node $node)
    {
        if (!$node instanceof Node\Stmt\ClassMethod) {
            return null;
        }

        if ($node->flags === 0) {
            // Checks if a method is implicitly public (PHP 4 syntax)
            $min = '4.0.0';
        } else {
            $min = '5.0.0';
        }
        $this->updateNodeElementVersion($node, $this->attributeKeyStore, ['php.min' => $min]);
    }
}
