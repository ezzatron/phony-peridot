<?php

declare(strict_types=1);

namespace Eloquent\Phony\Peridot;

use Eloquent\Phony\Phony;
use Evenement\EventEmitterInterface;
use Peridot\Core\Suite;
use ReflectionException;
use ReflectionFunction;

/**
 * A Peridot plugin for Phony integration.
 */
class PeridotPhony
{
    /**
     * Install a new Peridot Phony plugin to the supplied emitter.
     *
     * @return self The newly created plugin.
     */
    public static function install(EventEmitterInterface $emitter)
    {
        $instance = new self();
        $instance->attach($emitter);

        return $instance;
    }

    /**
     * Create a new Peridot Phony plugin.
     *
     * @return self The newly created plugin.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Attach the plugin.
     */
    public function attach(EventEmitterInterface $emitter)
    {
        $emitter->on('suite.define', [$this, 'onSuiteDefine']);
        $emitter->on('suite.start', [$this, 'onSuiteStart']);
    }

    /**
     * Detach the plugin.
     */
    public function detach(EventEmitterInterface $emitter)
    {
        $emitter->removeListener('suite.define', [$this, 'onSuiteDefine']);
        $emitter->removeListener('suite.start', [$this, 'onSuiteStart']);
    }

    /**
     * Handle the definition of a suite.
     *
     * @access private
     */
    public function onSuiteDefine(Suite $suite)
    {
        $definition = new ReflectionFunction($suite->getDefinition());
        $parameters = $definition->getParameters();

        if ($parameters) {
            $suite->setDefinitionArguments(
                $this->parameterArguments($parameters)
            );
        }
    }

    /**
     * Handle the start of a suite.
     *
     * @access private
     */
    public function onSuiteStart(Suite $suite)
    {
        foreach ($suite->getTests() as $test) {
            $definition = new ReflectionFunction($test->getDefinition());
            $parameters = $definition->getParameters();

            if ($parameters) {
                $test->setDefinitionArguments(
                    $this->parameterArguments($parameters)
                );
            }
        }
    }

    private function __construct()
    {
        try {
            $function = new ReflectionFunction(function (object $a) {});
            $parameters = $function->getParameters();
            $this->isObjectTypeSupported = null === $parameters[0]->getClass();
            // @codeCoverageIgnoreStart
        } catch (ReflectionException $e) {
            $this->isObjectTypeSupported = false;
        }
        // @codeCoverageIgnoreEnd
    }

    private function parameterArguments(array $parameters)
    {
        $arguments = [];

        foreach ($parameters as $parameter) {
            if ($parameter->allowsNull()) {
                $arguments[] = null;

                continue;
            }

            $typeName = strval($parameter->getType());

            switch (strtolower($typeName)) {
                case 'bool':
                    $argument = false;

                    break;

                case 'int':
                    $argument = 0;

                    break;

                case 'float':
                    $argument = .0;

                    break;

                case 'string':
                    $argument = '';

                    break;

                case 'array':
                case 'iterable':
                    $argument = [];

                    break;

                case 'object':
                    if ($this->isObjectTypeSupported) {
                        $argument = (object) [];

                        break;
                    }

                    // @codeCoverageIgnoreStart
                    $argument = Phony::mock('object')->get();

                    break;
                    // @codeCoverageIgnoreEnd

                case 'stdclass':
                    $argument = (object) [];

                    break;

                case 'callable':
                    $argument = Phony::stub();

                    break;

                case 'closure':
                    $argument = function () {};

                    break;

                case 'generator':
                    $fn = function () { return; yield; };
                    $argument = $fn();

                    break;

                default:
                    $argument = Phony::mock($typeName)->get();
            }

            $arguments[] = $argument;
        }

        return $arguments;
    }

    private $isObjectTypeSupported;
}
