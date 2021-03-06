<?php
declare(strict_types=1);

namespace Cundd\Stairtower\DataAccess;


use Cundd\Stairtower\Configuration\ConfigurationManager;
use Cundd\Stairtower\DataAccess\Exception\WriterException;
use Cundd\Stairtower\Domain\Model\DatabaseInterface;
use Cundd\Stairtower\Domain\Model\DocumentInterface;
use Cundd\Stairtower\Serializer\JsonSerializer;
use Cundd\Stairtower\System\Lock\Factory;


class Writer implements WriterInterface
{
    /**
     * Used data encoding
     */
    const DATA_ENCODING = 'json';

    /**
     * @var \Psr\Log\LoggerInterface
     * @inject
     */
    protected $logger;

    public function writeDatabase(DatabaseInterface $database)
    {
        $this->prepareWriteDirectory();
        $databaseIdentifier = $database->getIdentifier();
        $path = $this->getWriteDirectory() . $databaseIdentifier . '.json';

        $result = $this->writeData($this->getDataToWrite($database), $path, $databaseIdentifier);
        if ($result === false) {
            throw new WriterException(
                sprintf(
                    'Could not write data from database %s to file "%s"',
                    $database->getIdentifier(),
                    $path
                ),
                1410291420
            );
        }
        $this->logger->debug(sprintf('Did write database %s', $databaseIdentifier));
    }

    /**
     * Prepares the directory for writing
     *
     * @throws Exception\WriterException if the folder exists but is not writable
     */
    protected function prepareWriteDirectory()
    {
        $writeFolder = $this->getWriteDirectory();
        if (!file_exists($writeFolder)) {
            mkdir($writeFolder, 0774, true);
        } elseif (file_exists($writeFolder) && !is_writable($writeFolder)) {
            throw new WriterException('Document folder exists but is not writable', 1410188161);
        }
    }

    /**
     * Returns the directory path to write data in
     *
     * @return string
     */
    protected function getWriteDirectory()
    {
        return ConfigurationManager::getSharedInstance()->getConfigurationForKeyPath('writeDataPath');
    }

    /**
     * Writes the data string to the file system
     *
     * @param string $data
     * @param string $file
     * @param string $databaseIdentifier
     * @throws Exception\WriterException if the lock could not be acquired
     * @return int Returns the number of bytes that were written to the file, or FALSE on failure
     */
    protected function writeData($data, $file, $databaseIdentifier)
    {
        $fileHandle = @fopen($file, 'w+');
        if (!$fileHandle) {
            $error = error_get_last();
            throw new WriterException($error['message'], 1410290532);
        }

        $lock = $this->getLockForDatabase($databaseIdentifier);
        if ($lock->tryLock()) { // acquire an exclusive lock
            ftruncate($fileHandle, 0); // truncate file
            $bytesWritten = fwrite($fileHandle, $data);
            fflush($fileHandle); // flush output before releasing the lock
            $lock->unlock(); // release the lock
        } else {
            throw new WriterException(
                sprintf(
                    'Unable to acquire an exclusive lock for file "%s"',
                    $file
                ),
                1410290540
            );
        }
        fclose($fileHandle);

        return $bytesWritten;
    }

    /**
     * Return a lock for the given database
     *
     * @param string $databaseIdentifier
     * @return \Cundd\Stairtower\System\Lock\LockInterface
     */
    protected function getLockForDatabase(string $databaseIdentifier)
    {
        return Factory::createLock($databaseIdentifier);
    }

    /**
     * Returns the string that will be written to the file system
     *
     * @param DatabaseInterface $database
     * @return string
     */
    protected function getDataToWrite(DatabaseInterface $database): string
    {
        $objectsToWrite = $this->getObjectsWrite($database);
        $serializer = new JsonSerializer();

        return $serializer->serialize($objectsToWrite);
    }

    /**
     * Returns the Document objects that will be written to the file system
     *
     * @param DatabaseInterface $database
     * @return array
     */
    protected function getObjectsWrite(DatabaseInterface $database)
    {
        $objectsToWrite = [];
        $database->rewind();
        while ($database->valid()) {
            /** @var DocumentInterface $item */
            $item = $database->current();
            if ($item) {
                $objectsToWrite[] = $item->getData();
            }
            $database->next();
        }

        return $objectsToWrite;
    }

    public function createDatabase(string $databaseIdentifier, array $options = [])
    {
        $this->prepareWriteDirectory();
        $path = $this->getWriteDirectory() . $databaseIdentifier . '.json';

        if (file_exists($path)) {
            throw new WriterException(
                sprintf('Database with identifier %s already exists', $databaseIdentifier),
                1412509808
            );
        }
        if (!is_writable($path) && !is_writable(dirname($path))) {
            throw new WriterException(
                sprintf('No access to write the database with identifier %s to %s', $databaseIdentifier, $path),
                1412509809
            );
        }

        $result = $this->writeData('[]', $path, $databaseIdentifier);
        if ($result === false) {
            throw new WriterException(
                sprintf(
                    'Could not create database %s in file "%s"',
                    $databaseIdentifier,
                    $path
                ),
                1410291420
            );
        }
    }

    public function dropDatabase(string $databaseIdentifier)
    {
        $path = $this->getReadDirectory() . $databaseIdentifier . '.json';

        if (!file_exists($path)) {
            throw new WriterException(
                sprintf('Database with identifier %s does not exist', $databaseIdentifier),
                1412526598
            );
        }
        if (!is_writable($path) && !is_writable(dirname($path))) {
            throw new WriterException(
                sprintf('No access to write the database with identifier %s to %s', $databaseIdentifier, $path),
                1412526604
            );
        }

        $fileHandle = @fopen($path, 'w+');
        if (!$fileHandle) {
            $error = error_get_last();
            throw new WriterException($error['message'], 1412526609);
        }

        $lock = $this->getLockForDatabase($databaseIdentifier);
        if ($lock->tryLock()) { // acquire an exclusive lock
            ftruncate($fileHandle, 0); // truncate file
            fflush($fileHandle); // flush output before releasing the lock
            unlink($path);
            $lock->unlock(); // release the lock
        } else {
            throw new WriterException(
                sprintf(
                    'Unable to acquire an exclusive lock for file "%s"',
                    $path
                ),
                1412526613
            );
        }
    }

    /**
     * Returns the directory path to read data from
     *
     * @return string
     */
    protected function getReadDirectory()
    {
        return ConfigurationManager::getSharedInstance()->getConfigurationForKeyPath('dataPath');
    }
} 