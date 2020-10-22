<?php declare(strict_types=1);

/**
 * Command Factory
 *
 * PHP version 7
 *
 * @category PHP
 * @package  PHP_CompatInfo
 * @author   Laurent Laville <pear@laurent-laville.org>
 * @license  https://opensource.org/licenses/BSD-3-Clause The 3-Clause BSD License
 */

namespace Bartlett\CompatInfo\Console;

use Bartlett\CompatInfo\Client;
use Bartlett\CompatInfo\Collection\SniffCollection;
use Bartlett\CompatInfo\Profiler\Profile;
use Bartlett\CompatInfo\Profiler\Profiler;
use Bartlett\Reflect\Event\ProgressEvent;

use Ramsey\Uuid\Uuid;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\EventDispatcher\GenericEvent;

use phpDocumentor\Reflection\DocBlockFactory;

use Closure;
use ReflectionException;
use ReflectionMethod;
use function json_encode;

/**
 * The Commands are autogenerated by introspecting the API Implementations.
 * Each public method is a command, each method parameter will be translated
 * into a InputArgument or InputOption.
 *
 * @category PHP
 * @package  PHP_CompatInfo
 * @author   Laurent Laville <pear@laurent-laville.org>
 * @license  https://opensource.org/licenses/BSD-3-Clause The 3-Clause BSD License
 * @since    Class available since Release 5.4.0
 */
class CommandFactory
{
    private $application;
    private $cmdExceptions;

    /**
     * Creates an instance of the factory that build all Api Methods.
     *
     * @param Application $app The Console Application
     * @param array|null $cmdExceptions (optional) Commands specific rules to apply
     */
    public function __construct(Application $app, array $cmdExceptions = null)
    {
        $this->application   = $app;
        $this->cmdExceptions = $cmdExceptions ? : array();
    }

    /**
     * Generates Commands from all Api Methods or a predefined set.
     *
     * @param array|null $classes (optional) Api classes to lookup for commands
     *
     * @return Command[]
     * @throws ReflectionException
     */
    public function generateCommands(array $classes = null)
    {
        if (!isset($classes)) {
            $classes = array();
        }
        $path = dirname(__DIR__) . '/Api';

        if (\Phar::running(false)) {
            $iterator = new \Phar($path);
        } else {
            $iterator = new \DirectoryIterator($path);
        }

        foreach ($iterator as $file) {
            if (fnmatch('*.php', $file->getPathName())) {
                $classes[] = Application::API_NAMESPACE . $file->getBasename('.php');
            }
        }

        $commands = array();

        foreach ($classes as $class) {
            $api = new \ReflectionClass($class);
            if ($api->isAbstract()) {
                // skip abtract classes
                continue;
            }

            foreach ($api->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if (strstr($method->getName(), '__')) {
                    // skip magics
                    continue;
                }
                $commands[] = $this->generateCommand(strtolower($api->getShortName()), $method);
            }
        }
        return $commands;
    }

    /**
     * Creates a Command based on an Api Method
     *
     * @param string $namespace Command namespace
     * @param ReflectionMethod $method
     *
     * @return Command
     * @throws ReflectionException
     */
    private function generateCommand(string $namespace, ReflectionMethod $method)
    {
        $factory = DocBlockFactory::createInstance();

        $docBlock = $factory->create($method->getDocComment());

        $methodShortName = $method->getShortName();

        $command = new Command($namespace . ':' . $this->dash($methodShortName));

        $cmdExceptions = array();

        if (isset($this->cmdExceptions[$namespace])) {
            if (isset($this->cmdExceptions[$namespace][$methodShortName])) {
                $cmdExceptions = $this->cmdExceptions[$namespace][$methodShortName];
            }
        }

        $params   = $docBlock->getTagsByName('param');
        $aliases  = $docBlock->getTagsByName('alias');
        $disabled = $docBlock->getTagsByName('disabled');

        if (!empty($disabled)) {
            // restrict some public Api methods
            $command->disable();
        } else {
            if (!empty($aliases)) {
                $names = array();
                foreach ($aliases as $aliasTag) {
                    $names[] = $namespace . ':' . (string) $aliasTag->getDescription();
                }
                $name = array_shift($names);
                // rename command with the first alias
                $command->setName($name);
                // others @alias are really some command aliases
                $command->setAliases($names);
            }

            $command->setDefinition(
                $this->buildDefinition($namespace, $method, $params, $cmdExceptions)
            );
            $command->setDescription($docBlock->getSummary());
            $command->setCode($this->createCode($namespace, $method));
        }
        return $command;
    }

    /**
     * Builds the Input Definition based upon Api Method Parameters
     *
     * @param string $namespace Command namespace
     * @param ReflectionMethod $method Api Method
     * @param array $params Command arguments and options
     * @param array $cmdExceptions Command specific values
     *
     * @return InputDefinition
     * @throws ReflectionException
     */
    private function buildDefinition(string $namespace, ReflectionMethod $method, array $params, array $cmdExceptions): InputDefinition
    {
        $definition = new InputDefinition();

        foreach ($method->getParameters() as $pos => $parameter) {
            $name        = $parameter->getName();
            $description = null;
            if (isset($params[$pos])) {
                if (ltrim($params[$pos]->getVariableName(), '$') == $name) {
                    $description = (string) $params[$pos]->getDescription();
                    // replace tokens if available
                    if (isset($cmdExceptions[$name]['replaceTokens'])) {
                        $description = strtr(
                            $description,
                            $cmdExceptions[$name]['replaceTokens']
                        );
                    }
                }
            }

            if ($parameter->isOptional()) {
                $shortcut = null;
                $default  = $parameter->isDefaultValueAvailable()
                    ? $parameter->getDefaultValue() : null;
                // option
                if ($default === null) {
                    $mode = InputOption::VALUE_NONE;
                } else {
                    $mode = InputOption::VALUE_OPTIONAL;
                }
                if (isset($params[$pos])
                    && strcasecmp((string) $params[$pos]->getType(), 'array') === 0
                ) {
                    $mode = InputOption::VALUE_IS_ARRAY | $mode;
                }
                $name = str_replace('_', '-', $name);
                $definition->addOption(
                    new InputOption($name, $shortcut, $mode, $description, $default)
                );
            } else {
                if (isset($cmdExceptions[$name]['default'])) {
                    $default = $cmdExceptions[$name]['default'];
                    $mode    = InputArgument::OPTIONAL;
                } else {
                    $default = null;
                    $mode    = InputArgument::REQUIRED;
                }
                if (isset($params[$pos])
                    && strcasecmp((string) $params[$pos]->getType(), 'array') === 0
                ) {
                    $mode = InputArgument::IS_ARRAY | $mode;
                }
                // argument
                $definition->addArgument(
                    new InputArgument($name, $mode, $description, $default)
                );
            }
        }

        return $definition;
    }

    /**
     * Creates the Command execution code
     *
     * @param string            $namespace Command namespace
     * @param ReflectionMethod $method
     *
     * @return Closure
     */
    private function createCode(string $namespace, ReflectionMethod $method): Closure
    {
        $app = $this->application;

        return function (InputInterface $input, OutputInterface $output) use ($namespace, $method, $app) {
            $methodName = $method->getName();

            $client = new Client();
            $api    = $client->api(strtolower($namespace));
            $api->setEventDispatcher($app->getDispatcher());

            if (true === $input->hasParameterOption('--progress')) {
                $formats = array(
                    'very_verbose' => ' %current%/%max% %percent:3s%% %elapsed:6s% %message%',
                    'very_verbose_nomax' => ' %current% %elapsed:6s% %message%',

                    'debug' => ' %current%/%max% %percent:3s%% %elapsed:6s% %memory:6s% %message%',
                    'debug_nomax' => ' %current% %elapsed:6s% %memory:6s% %message%',
                );
                foreach ($formats as $name => $format) {
                    ProgressBar::setFormatDefinition($name, $format);
                }
                $progress = new ProgressBar($output);
                $progress->setMessage('');

                $api->getEventDispatcher()->addListener(
                    ProgressEvent::class,
                    function (GenericEvent $event) use ($progress) {
                        if ($progress instanceof ProgressBar) {
                            $progress->setMessage(
                                sprintf(
                                    'File %s in progress...',
                                    $event['file']->getRelativePathname()
                                )
                            );
                            $progress->advance();
                        }
                    }
                );
            }

            $args = array();

            foreach ($method->getParameters() as $parameter) {
                if ($parameter->isOptional()) {
                    // option
                    $name = str_replace('_', '-', $parameter->getName());
                    $args[$name] = $input->getOption($name);
                } else {
                    // argument
                    $args[$parameter->getName()] = $input->getArgument($parameter->getName());
                }
            }

            if (isset($progress) && $progress instanceof ProgressBar) {
                $progress->start();
            }

            $args['sniffCollection'] = $app->getContainer()->get(SniffCollection::class);

            $profiler = new Profiler(Uuid::uuid4()->toString());

            if (true === $input->hasParameterOption('--profile')) {
                $args['profiler'] = $profiler;
            }

            // calls the Api method
            try {
                $response = call_user_func_array(
                    array($api, $methodName),
                    array_values($args)
                );
            } catch (\Exception $e) {
                $response = $e;
            }

            if (true === $input->hasParameterOption('--output')) {
                $profile = $profiler->collect();
                $data = $profile->getData();
                $token = key($data);
                $target = $input->getOption('output') ?? '/tmp/' . $token . '-compatinfo.json';
                @file_put_contents($target, json_encode($data[$token]));
            }

            if (isset($progress) && $progress instanceof ProgressBar) {
                $progress->finish();
                $progress->clear();
            }
            $output->writeln('');

            // prints response returned
            $classParts      = explode('\\', get_class($api));
            $class           = array_pop($classParts);
            $outputFormatter = str_replace("\\Api\\$class", "\\Output\\$class", get_class($api));

            // handle all Api exceptions when occured
            if ($response instanceof \Exception) {
                if (substr_count($response->getMessage(), "\n") > 0) {
                    // message on multiple lines
                    $fmt = new FormatterHelper;
                    $output->writeln(
                        $fmt->formatBlock(
                            explode("\n", $response->getMessage()),
                            'error'
                        )
                    );
                } else {
                    // message on single line
                    $output->writeln('<error>' . $response->getMessage() . '</error>');
                }
                return;
            }

            $result = new $outputFormatter();
            if (!method_exists($result, $methodName)
                || !is_callable(array($result, $methodName))
                || $output->isDebug()
            ) {
                $style = 'debug';
                $style = $output->getFormatter()->hasStyle($style) ? $style : 'comment';

                $output->writeln(sprintf('<%s>Raw response</%s>', $style, $style));
                $output->writeln(print_r($response->getData(), true), OutputInterface::OUTPUT_RAW);
                return;
            }


            if ($response instanceof Profile) {
                $data = $response->getData();
                $token = key($data);
                $collectors = $data[$token];
            } else {
                $collectors = $response;
            }

            $result->$methodName($output, $collectors);

            if (isset($target)) {
                $output->writeln(PHP_EOL . '<comment>Profile results are being logged as JSON format to</comment> '. $target);
            }
        };
    }

    /**
     * Dashifies a camelCase string
     *
     * @param string $name Name of an Api method
     *
     * @return string
     */
    private function dash(string $name): string
    {
        return strtolower(
            preg_replace(
                array('/([A-Z]+)([A-Z][a-z])/', '/([a-z\d])([A-Z])/'),
                array('\\1-\\2', '\\1-\\2'),
                strtr($name, '-', '.')
            )
        );
    }
}