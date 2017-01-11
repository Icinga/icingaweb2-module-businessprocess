<?php

namespace Icinga\Module\Businessprocess\Storage;

use DirectoryIterator;
use Icinga\Application\Icinga;
use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Exception\SystemPermissionException;

class LegacyStorage extends Storage
{
    /** @var string */
    protected $configDir;

    public function getConfigDir()
    {
        if ($this->configDir === null) {
            $this->prepareDefaultConfigDir();
        }

        return $this->configDir;
    }

    protected function prepareDefaultConfigDir()
    {
        $dir = Icinga::app()
            ->getModuleManager()
            ->getModule('businessprocess')
            ->getConfigDir();

        // TODO: This is silly. We need Config::requireDirectory().
        if (! is_dir($dir)) {
            if (! is_dir(dirname($dir))) {
                if (! @mkdir(dirname($dir))) {
                    throw new SystemPermissionException('Could not create config directory "%s"', dirname($dir));
                }
            }
            if (! mkdir($dir)) {
                throw new SystemPermissionException('Could not create config directory "%s"', $dir);
            }
        }
        $dir = $dir . '/processes';
        if (! is_dir($dir)) {
            if (! mkdir($dir)) {
                throw new SystemPermissionException('Could not create config directory "%s"', $dir);
            }
        }

        $this->configDir = $dir;
    }

    /**
     * @inheritdoc
     */
    public function listProcesses()
    {
        $files = array();

        foreach ($this->listAllProcessNames() as $name) {
            $meta = $this->loadMetadata($name);
            if (! $meta->canRead()) {
                continue;
            }

            $files[$name] = $meta->getExtendedTitle();
        }

        natcasesort($files);
        return $files;
    }

    /**
     * @inheritdoc
     */
    public function listProcessNames()
    {
        $files = array();

        foreach ($this->listAllProcessNames() as $name) {
            $meta = $this->loadMetadata($name);
            if (! $meta->canRead()) {
                continue;
            }

            $files[$name] = $name;
        }

        natcasesort($files);
        return $files;
    }

    /**
     * @inheritdoc
     */
    public function listAllProcessNames()
    {
        $files = array();

        foreach (new DirectoryIterator($this->getConfigDir()) as $file) {
            if ($file->isDot()) {
                continue;
            }

            $filename = $file->getFilename();
            if (substr($filename, -5) === '.conf') {
                $files[] = substr($filename, 0, -5);
            }
        }

        natcasesort($files);
        return $files;
    }

    /**
     * @inheritdoc
     */
    public function loadProcess($name)
    {
        return LegacyConfigParser::parseFile(
            $name,
            $this->getFilename($name)
        );
    }

    /**
     * @inheritdoc
     */
    public function storeProcess(BpConfig $process)
    {
        file_put_contents(
            $this->getFilename($process->getName()),
            LegacyConfigRenderer::renderConfig($process)
        );
    }

    /**
     * @inheritdoc
     */
    public function deleteProcess($name)
    {
        return @unlink($this->getFilename($name));
    }

    /**
     * @inheritdoc
     */
    public function loadMetadata($name)
    {
        return LegacyConfigParser::readMetadataFromFileHeader(
            $name,
            $this->getFilename($name)
        );
    }

    public function getSource($name)
    {
        return file_get_contents($this->getFilename($name));
    }

    public function getFilename($name)
    {
        return $this->getConfigDir() . '/' . $name . '.conf';
    }

    /**
     * @param $name
     * @param $string
     *
     * @return BpConfig
     */
    public function loadFromString($name, $string)
    {
        return LegacyConfigParser::parseString($name, $string);
    }

    /**
     * @param $name
     * @return bool
     */
    public function hasProcess($name)
    {
        $file = $this->getFilename($name);
        if (! is_file($file)) {
            return false;
        }

        return $this->loadMetadata($name)->canRead();
    }
}
