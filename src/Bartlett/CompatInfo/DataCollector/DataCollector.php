<?php declare(strict_types=1);

namespace Bartlett\CompatInfo\DataCollector;

use PhpParser\Error;
use PhpParser\NodeTraverser;

use PhpParser\NodeVisitor;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Base class of each DataCollector.
 *
 * Children of this class must store the collected data in the data property.
 */
abstract class DataCollector implements DataCollectorInterface
{
    /** @var array */
    protected $data = [];

    /** @var string */
    protected $name;

    /** @var string[] */
    protected $files = [];

    /** @var Error[] */
    protected $errors = [];

    /** @var NodeVisitor */
    protected $nodeVisitor;

    use VersionUpdater;

    public function __construct(NodeVisitor $visitor)
    {
        $this->nodeVisitor = $visitor;
    }

    /**
     * {@inheritDoc}
     */
    public function reset(): void
    {
        $this->data = [];
        $this->files = [];
        $this->errors = [];
    }

    /**
     * {@inheritDoc}
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * {@inheritDoc}
     */
    public function collect(array $nodes): array
    {
        $traverser = new NodeTraverser();
        $traverser->addVisitor($this->nodeVisitor);
        $traverser->traverse($nodes);

        return $this->nodeVisitor->getCollection()->toArray();
    }

    /**
     * {@inheritDoc}
     */
    public function setName(string $name): DataCollectorInterface
    {
        $this->name = $name;
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * {@inheritDoc}
     */
    public function addFile(SplFileInfo $file): void
    {
        $this->files[] = $file->getPathname();
    }

    /**
     * {@inheritDoc}
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    /**
     * {@inheritDoc}
     */
    public function addErrors(array $errors): void
    {
        $currentFilename = $this->files[count($this->files) - 1];
        foreach ($errors as $error) {
            /** @var Error $error */
            $this->errors[] = $error->getMessage() . ' in file ' . $currentFilename;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
