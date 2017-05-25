<?php

namespace exface\Core\CommonLogic;

use exface\Core\Interfaces\DebuggerInterface;
use exface\Core\Interfaces\Log\LoggerInterface;
use Symfony\Component\Debug\Debug;
use Symfony\Component\Debug\ExceptionHandler;
use Symfony\Component\Debug\Exception\FlattenException;
use Symfony\Component\Debug\ErrorHandler;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;

class Debugger implements DebuggerInterface
{

    private $prettify_errors = false;

    private $logger = null;

    function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DebuggerInterface::printException()
     */
    public function printException(\Throwable $exception, $use_html = true)
    {
        $handler = new ExceptionHandler();
        $flattened_exception = FlattenException::create($exception);
        if ($use_html) {
            $output = "<style>" . $handler->getStylesheet($flattened_exception) . "</style>" . $handler->getContent($flattened_exception);
        } else {
            $output = strip_tags($handler->getContent($flattened_exception));
        }
        return $output;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DebuggerInterface::getPrettifyErrors()
     */
    public function getPrettifyErrors()
    {
        return $this->prettify_errors;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DebuggerInterface::setPrettifyErrors()
     */
    public function setPrettifyErrors($value)
    {
        $this->prettify_errors = \exface\Core\DataTypes\BooleanDataType::parse($value);
        if ($this->prettify_errors) {
            $this->registerHandler();
        }
        return $this;
    }

    protected function registerHandler()
    {
        // Debug::enable(E_ALL & ~E_NOTICE);
        ExceptionHandler::register();
        ErrorHandler::register();
        
        // register logger
        $handler = new \Monolog\ErrorHandler($this->logger);
        $handler->registerErrorHandler([], false);
        $handler->registerExceptionHandler();
        $handler->registerFatalHandler();
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DebuggerInterface::printVariable()
     */
    public function printVariable($anything, $use_html = true)
    {
        $cloner = new VarCloner();
        if ($use_html) {
            $dumper = new HtmlDumper();
            $dumper->setDisplayOptions(array(
                'maxDepth' => 5
            ));
        } else {
            $dumper = new CliDumper();
        }
        return $dumper->dump($cloner->cloneVar($anything), true);
    }
}
