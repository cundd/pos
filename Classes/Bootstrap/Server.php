<?php
declare(strict_types=1);

namespace Cundd\Stairtower\Bootstrap;

use Cundd\Stairtower\Configuration\ConfigurationManager;
use Cundd\Stairtower\Constants;
use Cundd\Stairtower\Server\RestServer;
use Cundd\Stairtower\Server\ServerInterface;
use DI\Container;
use Psr\Log\LogLevel;

/**
 * Server bootstrapping
 */
class Server extends AbstractBootstrap
{
    /**
     * Configure the server/router
     *
     * @param array $arguments
     * @throws \DI\NotFoundException
     */
    public function configure($arguments)
    {
        // Parse the arguments
        $longOptions = [
            "port::",
            "ip::",
            "data-path::",
            'test::',
            'dev::',
        ];
        $options = getopt('h::', $longOptions);

        // Print the help
        if (isset($options['h'])) {
            print(Constants::MESSAGE_CLI_WELCOME . PHP_EOL);
            printf(
                'Usage: %s [--port=port] [--ip=ip] [--data-path=path/to/data/folder/] [--dev]' . PHP_EOL,
                $arguments[0]
            );
            exit;
        }

        $dataPath = $this->checkArgument('data-path', $options);
        $port = $this->checkArgument('port', $options);
        $ip = $this->checkArgument('ip', $options);
        $serverMode = $this->getServerModeFromOptions($options);

        $configurationManager = ConfigurationManager::getSharedInstance();
        if ($serverMode === ServerInterface::SERVER_MODE_DEVELOPMENT) {
            $configurationManager->setConfigurationForKeyPath('logLevel', LogLevel::DEBUG);
        }

        if ($dataPath) {
            $configurationManager->setConfigurationForKeyPath('dataPath', $dataPath);
            $configurationManager->setConfigurationForKeyPath('writeDataPath', $dataPath);
        }

        // Instantiate the Core
        $bootstrap = new Core();

        /** @var Container $diContainer */
        $diContainer = $bootstrap->getDiContainer();

        $this->server = $diContainer->get(RestServer::class);
        $diContainer->set(ServerInterface::class, $this->server);

        if ($ip) {
            $this->server->setIp($ip);
        }
        if ($port) {
            $this->server->setPort((int)$port);
        }

        // Set the server mode
        $this->server->setMode($serverMode);
        if ($serverMode === ServerInterface::SERVER_MODE_TEST && is_numeric($options['test'])) {
            $this->server->setAutoShutdownTime(intval($options['test']));
        }
    }

    /**
     * Executes the routing or starts the server
     */
    protected function doExecute()
    {
        $this->server->start();
    }

    /**
     * Checks and returns an argument value
     *
     * @param $key
     * @param $options
     * @return null
     */
    protected function checkArgument($key, $options)
    {
        if (isset($options[$key])) {
            if ($options[$key] === false) {
                printf('The option --%s requires a value' . PHP_EOL, $key);
                exit(1);
            }

            return $options[$key];
        }

        return null;
    }

    /**
     * Returns the server mode according to the options
     *
     * @param $options
     * @return int
     */
    protected function getServerModeFromOptions($options)
    {
        if (isset($options['dev'])) {
            return ServerInterface::SERVER_MODE_DEVELOPMENT;
        } elseif (isset($options['test'])) {
            return ServerInterface::SERVER_MODE_TEST;
        }

        switch (strtolower((string)getenv(Constants::ENVIRONMENT_KEY_SERVER_MODE))) {
            case 'dev':
                return ServerInterface::SERVER_MODE_DEVELOPMENT;

            case 'test':
                return ServerInterface::SERVER_MODE_DEVELOPMENT;

            default:
                return ServerInterface::SERVER_MODE_NORMAL;
        }
    }
}
