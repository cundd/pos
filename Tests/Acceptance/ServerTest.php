<?php
declare(strict_types=1);

namespace Cundd\Stairtower\Tests\Acceptance;

use Cundd\Stairtower\Configuration\ConfigurationManager;
use Cundd\Stairtower\Tests\HttpRequestClient;
use Symfony\Component\Process\Process;

/**
 * Test for REST commands
 */
class ServerTest extends AbstractAcceptanceCase
{
    protected $numberOfDocumentsToCreate = 1000;
    protected $numberOfWelcomeTests = 1000;

    protected function configureServerProcess(int $autoShutdownTime = 7): Process
    {
        $configurationManager = ConfigurationManager::getSharedInstance();
        $serverBinPath = $configurationManager->getBinPath() . 'server';
        $phpBinPath = $configurationManager->getPhpBinaryPath();
        $documentRoot = $configurationManager->getBasePath();

        $arguments = [];
        $arguments[] = '--port=' . (int)$this->getPortForTestServer();
        if ($autoShutdownTime > -1) {
            $arguments[] = '--test=' . (int)$autoShutdownTime; // Run the server in test mode
        }

        return $this->getProcessBuilder()
            ->setPrefix(['exec', $phpBinPath, $serverBinPath])
            ->setArguments($arguments)
            ->setTimeout($autoShutdownTime + 1)
            ->setWorkingDirectory($documentRoot)
            ->getProcess();
    }

    /**
     * @test
     */
    public function fullServerTest()
    {
        parent::fullServerTest();
        usleep((int)floor($this->getServerShutdownWaitTime() * 1000 * 1000));

        $this->assertServerNotRunning();
        $httpClient = new HttpRequestClient($this->getUriForTestServer());

        // The server should not send the welcome message
        $this->debug('The server should not send the welcome message');
        $response = $httpClient->performRestRequest('');
        $this->assertFalse(
            $response->isSuccess(),
            sprintf('Server should not have sent a response (HTTP response "%s"', $response->getBody())
        );
        $this->assertSame('', $response->getBody());
        $this->assertSame(
            sprintf(
                'Failed to connect to %s port %s: Connection refused',
                $this->getIpForTestServer(),
                $this->getPortForTestServer()
            ),
            $response->getError()->getMessage()
        );

        $this->assertFileNotExists($this->expectedPath);
    }
}
