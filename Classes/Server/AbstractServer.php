<?php
declare(strict_types=1);

namespace Cundd\PersistentObjectStore\Server;

use Cundd\PersistentObjectStore\Configuration\ConfigurationManager;
use Cundd\PersistentObjectStore\Constants;
use Cundd\PersistentObjectStore\Exception\SecurityException;
use Cundd\PersistentObjectStore\Memory\Manager;
use Cundd\PersistentObjectStore\Server\Exception\InvalidEventLoopException;
use Cundd\PersistentObjectStore\Server\Exception\InvalidRequestActionException;
use Cundd\PersistentObjectStore\Server\Exception\InvalidServerChangeException;
use Cundd\PersistentObjectStore\Server\Exception\ServerException;
use Cundd\PersistentObjectStore\Server\ValueObject\HandlerResult;
use Cundd\PersistentObjectStore\Server\ValueObject\RawResult;
use Cundd\PersistentObjectStore\Server\ValueObject\Request as Request;
use Cundd\PersistentObjectStore\Server\ValueObject\RequestInterface;
use Cundd\PersistentObjectStore\Server\ValueObject\Statistics;
use Cundd\PersistentObjectStore\System\Lock\Factory;
use Cundd\PersistentObjectStore\System\Lock\TransientLock;
use DateTime;
use DateTimeInterface;
use Exception;
use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\TimerInterface;
use React\Http\Response;
use React\Stream\WritableStreamInterface;

/**
 * Abstract server
 */
abstract class AbstractServer implements ServerInterface
{
    /**
     * Port number to listen on
     *
     * @var int
     */
    protected $port = 1338;

    /**
     * IP to listen on
     *
     * @var string
     */
    protected $ip = '127.0.0.1';

    /**
     * Document Access Coordinator
     *
     * @var \Cundd\PersistentObjectStore\DataAccess\CoordinatorInterface
     * @Inject
     */
    protected $coordinator;

    /**
     * JSON serializer
     *
     * @var \Cundd\PersistentObjectStore\Serializer\JsonSerializer
     * @Inject
     */
    protected $serializer;

    /**
     * DI container
     *
     * @var \DI\Container
     * @Inject
     */
    protected $diContainer;

    /**
     * Event loop
     *
     * @var \React\EventLoop\LoopInterface
     * @Inject
     */
    protected $eventLoop;

    /**
     * @var \Psr\Log\LoggerInterface
     * @inject
     */
    protected $logger;

    /**
     * Indicates if the server is currently running
     *
     * @var bool
     */
    protected $isRunningFlag = false;

    /**
     * Servers start time
     *
     * @var DateTime
     */
    protected $startTime;

    /**
     * Timer to schedule maintenance tasks
     *
     * @var TimerInterface
     */
    protected $maintenanceTimer;

    /**
     * The number of minimum seconds between maintenance tasks
     *
     * @var float
     */
    protected $maintenanceInterval = 5.0;

    /**
     * If run in test mode the server will stop after this number of seconds
     *
     * @var int
     */
    protected $autoShutdownTime = 60;

    /**
     * Event Emitter
     *
     * @var \Cundd\PersistentObjectStore\Event\SharedEventEmitter
     * @Inject
     */
    protected $eventEmitter;

    /**
     * Mode in which the server is started
     *
     * @var int
     */
    protected $mode = ServerInterface::SERVER_MODE_NOT_RUNNING;

    /**
     * Collects and returns the current server statistics
     *
     * @param bool $detailed If detailed is TRUE more data will be collected and an array will be returned
     * @return array|Statistics
     */
    public function collectStatistics(bool $detailed = false)
    {
        $statistics = new Statistics(
            Constants::VERSION, $this->getGuid(), $this->getStartTime(),
            memory_get_usage(true), memory_get_peak_usage(true)
        );
        if (!$detailed) {
            return $statistics;
        }

        $detailedStatistics = $statistics->jsonSerialize() + [
                'eventLoopImplementation' => get_class($this->getEventLoop()),
                'os'                      => [
                    'vendor'  => php_uname('s'),
                    'version' => php_uname('r'),
                    'machine' => php_uname('m'),
                    'info'    => php_uname('v'),
                ],
            ];

        return $detailedStatistics;
    }

    /**
     * Returns the servers global unique identifier
     *
     * @return string
     */
    public function getGuid()
    {
        return sprintf(
            'stairtower_%s_%s_%s_%d',
            Constants::VERSION,
            getmypid(),
            $this->getIp(),
            $this->getPort()
        );
    }

    /**
     * Returns the IP to listen on
     *
     * @return string
     */
    public function getIp(): string
    {
        return $this->ip;
    }

    /**
     * Sets the IP to listen on
     *
     * @param string $ip
     * @return $this
     */
    public function setIp($ip)
    {
        if ($this->isRunningFlag) {
            throw new InvalidServerChangeException('Can not change IP when server is running', 1412956590);
        }
        $this->ip = $ip;

        return $this;
    }

    /**
     * Returns the port number to listen on
     *
     * @return int
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * Sets the port number to listen on
     *
     * @param int $port
     * @return $this
     */
    public function setPort(int $port)
    {
        if ($this->isRunningFlag) {
            throw new InvalidServerChangeException('Can not change port when server is running', 1412956591);
        }
        $this->port = $port;

        return $this;
    }

    /**
     * Returns the servers start time
     *
     * @return DateTimeInterface
     */
    public function getStartTime(): DateTimeInterface
    {
        return $this->startTime;
    }

    /**
     * Handles the given exception
     *
     * @param Exception                        $error
     * @param RequestInterface                 $request
     * @param WritableStreamInterface|Response $response
     */
    public function handleError(Exception $error, RequestInterface $request, WritableStreamInterface $response): void
    {
        $this->writeln('Caught exception #%d: %s', $error->getCode(), $error->getMessage());
        $this->writeln($error->getTraceAsString());

        if ($error instanceof SecurityException) {
            $response->end();
        } elseif ($error instanceof InvalidRequestActionException) {
            $this->handleResult(
                new RawResult($this->getStatusCodeForException($error), $error->getMessage()),
                $request,
                $response
            );
        } else {
            $this->handleResult(
                new HandlerResult($this->getStatusCodeForException($error), $error->getMessage()),
                $request,
                $response
            );
        }
    }

    /**
     * Returns the status code that best describes the given error
     *
     * @param Exception $error
     * @return int
     */
    public function getStatusCodeForException($error)
    {
        if (!$error || !($error instanceof Exception)) {
            return 500;
        }
        switch (get_class($error)) {
            case 'Cundd\\PersistentObjectStore\\DataAccess\\Exception\\ReaderException':
                $statusCode = ($error->getCode() === 1408127629 ? 400 : 500);
                break;

            case 'Cundd\\PersistentObjectStore\\Domain\\Model\\Exception\\InvalidDatabaseException':
                $statusCode = 400;
                break;
            case 'Cundd\\PersistentObjectStore\\Domain\\Model\\Exception\\InvalidDatabaseIdentifierException':
                $statusCode = 400;
                break;
            case 'Cundd\\PersistentObjectStore\\Domain\\Model\\Exception\\InvalidDataException':
                $statusCode = 500;
                break;
            case 'Cundd\\PersistentObjectStore\\Domain\\Model\\Exception\\InvalidDataIdentifierException':
                $statusCode = 400;
                break;

            case 'Cundd\\PersistentObjectStore\\Server\\Exception\\InvalidBodyException':
                $statusCode = 400;
                break;
            case 'Cundd\\PersistentObjectStore\\Server\\Exception\\InvalidEventLoopException':
                $statusCode = 500;
                break;
            case 'Cundd\\PersistentObjectStore\\Server\\Exception\\InvalidRequestException':
                $statusCode = 400;
                break;
            case 'Cundd\\PersistentObjectStore\\Server\\Exception\\InvalidRequestMethodException':
                $statusCode = 405;
                break;
            case 'Cundd\\PersistentObjectStore\\Server\\Exception\\InvalidRequestParameterException':
                $statusCode = 400;
                break;
            case 'Cundd\\PersistentObjectStore\\Server\\Exception\\InvalidServerChangeException':
                $statusCode = 500;
                break;
            case 'Cundd\\PersistentObjectStore\\Server\\Exception\\MissingLengthHeaderException':
                $statusCode = 411;
                break;
            case 'Cundd\\PersistentObjectStore\\Server\\Exception\\RequestMethodNotImplementedException':
                $statusCode = 501;
                break;
            case 'Cundd\\PersistentObjectStore\\Server\\Exception\\ServerException':
                $statusCode = 500;
                break;

            case 'Cundd\\PersistentObjectStore\\Filter\\Exception\\InvalidCollectionException':
                $statusCode = 500;
                break;
            case 'Cundd\\PersistentObjectStore\\Filter\\Exception\\InvalidComparisonException':
                $statusCode = 500;
                break;
            case 'Cundd\\PersistentObjectStore\\Filter\\Exception\\InvalidOperatorException':
                $statusCode = 500;
                break;
            default:
                $statusCode = 500;
        }

        return $statusCode;
    }

    /**
     * Handles the given server action
     *
     * @param string                  $serverAction
     * @param RequestInterface        $request
     * @param WritableStreamInterface $response
     */
    public function handleServerAction(
        string $serverAction,
        RequestInterface $request,
        WritableStreamInterface $response
    ) {
        switch ($serverAction) {
            case 'restart':
                if (!$this->isRunning()) {
                    throw new ServerException('Server is currently not running', 1413201286);
                }
                $this->handleResult(new HandlerResult(202, 'Server is going to restart'), $request, $response);
                $this->restart();
                break;

            case 'shutdown':
                $this->handleResult(new HandlerResult(202, 'Server is going to shut down'), $request, $response);
                $this->eventLoop->addTimer(0.5, [$this, 'shutdown']);
                break;

//			case 'stop':
//				$this->handleResult(new HandlerResult(200, 'Server is going to stop'), $request, $response);
//				$this->stop();
////				$this->eventLoop->addTimer(0.5, array($this, 'shutdown'));
//				break;
        }
    }

    /**
     * Returns if the server is running
     *
     * @return bool
     */
    public function isRunning(): bool
    {
        return $this->isRunningFlag;
    }

    /**
     * Restarts the server
     */
    public function restart()
    {
        if (!$this->isRunning()) {
            throw new ServerException('Server is currently not running', 1413201286);
        }
        $this->stop();
        $this->start();
    }

    /**
     * Starts the request loop
     */
    public function start()
    {
        $this->prepareEventLoop();
        $this->setupServer();
        $this->startTime = new DateTime();
        $this->isRunningFlag = true;
        $this->eventLoop->run();
        $this->isRunningFlag = false;
    }

    /**
     * Prepare the event loop
     */
    public function prepareEventLoop()
    {
        $this->postponeMaintenance();


        // If the server is run in test-mode shut it down after 1 minute
        if ($this->getMode() === ServerInterface::SERVER_MODE_TEST) {
            $this->writeln(
                'Server is started in test mode and will shut down after %d seconds',
                $this->getAutoShutdownTime()
            );
            $this->eventLoop->addTimer(
                $this->getAutoShutdownTime(),
                function () {
                    $this->writeln('Auto shutdown time reached');
                    $this->shutdown();
                }
            );
        }
    }

    /**
     * Postpone the maintenance run
     */
    public function postponeMaintenance()
    {
        if ($this->maintenanceTimer) {
            $this->eventLoop->cancelTimer($this->maintenanceTimer);
        }
        $this->maintenanceTimer = $this->eventLoop->addTimer(
            $this->maintenanceInterval,
            function () {
                $this->runMaintenance();
                $this->postponeMaintenance();
            }
        );
    }

    /**
     * Returns the mode of the server
     *
     * @return int
     */
    public function getMode(): int
    {
        return $this->mode;
    }

    /**
     * Sets the mode of the server
     *
     * @param int $mode
     * @return ServerInterface
     */
    public function setMode(int $mode): ServerInterface
    {
        if ($this->isRunningFlag) {
            throw new InvalidServerChangeException('Can not change the mode when server is running', 1414835788);
        }
        if (false === (
                $mode === self::SERVER_MODE_NORMAL
                || $mode === self::SERVER_MODE_NOT_RUNNING
                || $mode === self::SERVER_MODE_TEST
                || $mode === self::SERVER_MODE_DEVELOPMENT
            )
        ) {
            throw new ServerException(sprintf('Invalid server mode %s', $mode), 1421096002);
        }
        $this->mode = $mode;
        ConfigurationManager::getSharedInstance()->setConfigurationForKeyPath('serverMode', $mode);
        Factory::setLockImplementationClass(TransientLock::class);

        return $this;
    }

    /**
     * Returns the number of seconds after which to stop the server if run in test mode
     *
     * @return int
     */
    public function getAutoShutdownTime(): int
    {
        return $this->autoShutdownTime;
    }

    /**
     * Sets the number of seconds after which to stop the server if run in test mode
     *
     * @param int $autoShutdownTime
     * @return ServerInterface
     */
    public function setAutoShutdownTime(int $autoShutdownTime): ServerInterface
    {
        $this->autoShutdownTime = $autoShutdownTime;

        return $this;
    }

    /**
     * Total shutdown of the server
     *
     * Stops to listen for incoming connections, runs the maintenance task and terminates the program
     */
    public function shutdown()
    {
        $this->stop();
        $this->runMaintenance();
        $this->writeln('Server is going to shut down now');
    }

    /**
     * Stops to listen for connections
     */
    public function stop()
    {
        $this->getEventLoop()->stop();
        $this->isRunningFlag = false;
    }

    /**
     * Returns the event loop instance
     *
     * @throws InvalidEventLoopException if the event loop is not set
     * @return \React\EventLoop\LoopInterface
     */
    public function getEventLoop()
    {
        if (!$this->eventLoop) {
            throw new InvalidEventLoopException('Event loop not set', 1412942824);
        }

        return $this->eventLoop;
    }

    /**
     * Sets the event loop
     *
     * @param LoopInterface|\React\EventLoop\LoopInterface $eventLoop
     * @return ServerInterface
     */
    public function setEventLoop(LoopInterface $eventLoop): ServerInterface
    {
        if ($this->isRunningFlag) {
            throw new InvalidServerChangeException('Can not change the event loop when server is running', 1412956592);
        }
        $this->eventLoop = $eventLoop;

        return $this;
    }

    /**
     * A maintenance task that will be performed when the server becomes idle
     */
    public function runMaintenance()
    {
        $this->logger->debug('Run maintenance');
        $this->eventEmitter->emit(Event::MAINTENANCE);
        $this->coordinator->commitDatabases();
        Manager::cleanup();
        $this->logger->debug('Finished maintenance');
    }

    /**
     * Outputs the given value for information
     *
     * @param string $format
     * @param array  ...$vars
     */
    protected function writeln(string $format, ...$vars)
    {
        $this->formatAndWrite($format, ...$vars);
        $this->formatAndWrite(PHP_EOL);
    }

    /**
     * Outputs the given value for information
     *
     * @param string $format
     * @param array  ...$vars
     */
    protected function write(string $format, ...$vars)
    {
        $this->formatAndWrite($format, ...$vars);
    }

    /**
     * Outputs the given value for information
     *
     * @param string $format
     * @param array  ...$vars
     */
    protected function formatAndWrite(string $format, ...$vars)
    {
        if (!empty($vars)) {
            $format = vsprintf($format, $vars);
        }
        fwrite(STDOUT, $format);
    }

    /**
     * Prints the given log message very fast
     *
     * @experimental
     *
     * @param string $format
     * @param array  ...$vars
     */
    protected function log(string $format, ...$vars)
    {
        if (!empty($vars)) {
            $writeData = vsprintf($format, ...$vars);
        } else {
            $writeData = $format;
        }
        $this->logger->info($writeData);
    }

    /**
     * Create and configure the server objects
     */
    abstract protected function setupServer();

    /**
     * Returns if the given request should be ignored
     *
     * @param Request $request
     * @return bool
     */
    abstract protected function getIgnoreRequest($request);
}