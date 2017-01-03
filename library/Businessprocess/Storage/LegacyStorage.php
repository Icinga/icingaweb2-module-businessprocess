<?php

namespace Icinga\Module\Businessprocess\Storage;

use DirectoryIterator;
use Icinga\Application\Benchmark;
use Icinga\Application\Icinga;
use Icinga\Exception\ConfigurationError;
use Icinga\Module\Businessprocess\BpNode;
use Icinga\Module\Businessprocess\BusinessProcess;
use Icinga\Exception\SystemPermissionException;
use Icinga\Module\Businessprocess\Metadata;

class LegacyStorage extends Storage
{
    /** @var string */
    protected $configDir;

    /** @var int */
    protected $parsing_line_number;

    /** @var string */
    protected $currentFilename;

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
     * All processes readable by the current user
     *
     * The returned array has the form <process name> => <nice title>, sorted
     * by title
     *
     * @return array
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
     * All process names readable by the current user
     *
     * The returned array has the form <process name> => <process name> and is
     * sorted
     *
     * @return array
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
     * All available process names, regardless of eventual restrictions
     *
     * @return array
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

    protected function splitCommaSeparated($string)
    {
        return preg_split('/\s*,\s*/', $string, -1, PREG_SPLIT_NO_EMPTY);
    }

    protected function readHeader($file, Metadata $metadata)
    {
        $fh = fopen($file, 'r');
        $cnt = 0;
        while ($cnt < 15 && false !== ($line = fgets($fh))) {
            $cnt++;
            $this->parseHeaderLine($line, $metadata);
        }

        fclose($fh);
        return $metadata;
    }

    protected function readHeaderString($string, Metadata $metadata)
    {
        foreach (preg_split('/\n/', $string) as $line) {
            $this->parseHeaderLine($line, $metadata);
        }

        return $metadata;
    }

    protected function emptyHeader()
    {
        return array(
            'Title'         => null,
            'Description'   => null,
            'Owner'         => null,
            'AllowedUsers'  => null,
            'AllowedGroups' => null,
            'AllowedRoles'  => null,
            'Backend'       => null,
            'Statetype'     => 'soft',
            'SLAHosts'      => null
        );
    }

    protected function parseHeaderLine($line, Metadata $metadata)
    {
        if (preg_match('/^\s*#\s+(.+?)\s*:\s*(.+)$/', $line, $m)) {
            if ($metadata->hasKey($m[1])) {
                $metadata->set($m[1], $m[2]);
            }
        }
    }

    /**
     * @param BusinessProcess $process
     *
     * @return void
     */
    public function storeProcess(BusinessProcess $process)
    {
        file_put_contents(
            $this->getFilename($process->getName()),
            $this->render($process)
        );
    }

    public function render(BusinessProcess $process)
    {
        return $this->renderHeader($process)
            . $this->renderNodes($process);
    }

    public function renderHeader(BusinessProcess $process)
    {
        $conf = "### Business Process Config File ###\n#\n";

        $meta = $process->getMetadata();
        foreach ($meta->getProperties() as $key => $value) {
            if ($value === null) {
                continue;
            }

            $conf .= sprintf("# %-11s : %s\n", $key, $value);
        }

        $conf .= "#\n###################################\n\n";

        return $conf;
    }

    public function renderNodes(BusinessProcess $bp)
    {
        $rendered = array();
        $conf = '';

        foreach ($bp->getRootNodes() as $child) {
            $conf .= $child->toLegacyConfigString($rendered);
            $rendered[$child->getName()] = true;
        }

        foreach ($bp->getUnboundNodes() as $name => $node) {
            $conf .= $node->toLegacyConfigString($rendered);
            $rendered[$name] = true;
        }

        return $conf . "\n";
    }

    public function getSource($name)
    {
        return file_get_contents($this->getFilename($name));
    }

    public function getFilename($name)
    {
        return $this->getConfigDir() . '/' . $name . '.conf';
    }

    public function loadFromString($name, $string)
    {
        $bp = new BusinessProcess();
        $bp->setName($name);
        $this->parseString($string, $bp);
        $this->readHeaderString($string, $bp->getMetadata());
        return $bp;
    }

    /**
     * @inheritdoc
     */
    public function deleteProcess($name)
    {
        return @unlink($this->getFilename($name));
    }

    /**
     * @return BusinessProcess
     */
    public function loadProcess($name)
    {
        Benchmark::measure('Loading business process ' . $name);
        $bp = new BusinessProcess();
        $bp->setName($name);
        $this->parseFile($name, $bp);
        $this->loadHeader($name, $bp);
        Benchmark::measure('Business process ' . $name . ' loaded');
        return $bp;
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

    public function loadMetadata($name)
    {
        $metadata = new Metadata($name);
        return $this->readHeader($this->getFilename($name), $metadata);
    }

    /**
     * @param string $name
     * @param BusinessProcess $bp
     */
    protected function loadHeader($name, $bp)
    {
        // TODO: do not open twice, this is quick and dirty based on existing code
        $file = $this->currentFilename = $this->getFilename($name);
        $this->readHeader($file, $bp->getMetadata());
    }

    protected function parseFile($name, $bp)
    {
        $file = $this->currentFilename = $this->getFilename($name);
        $fh = @fopen($file, 'r');
        if (! $fh) {
            throw new SystemPermissionException('Could not open "%s"', $file);
        }

        $this->parsing_line_number = 0;
        while ($line = fgets($fh)) {
            $this->parseLine($line, $bp);
        }

        fclose($fh);
        unset($this->parsing_line_number);
        unset($this->currentFilename);
    }

    protected function parseString($string, BusinessProcess $bp)
    {
        foreach (preg_split('/\n/', $string) as $line) {
            $this->parseLine($line, $bp);
        }
    }

    /**
     * @param $line
     * @param BusinessProcess $bp
     */
    protected function parseDisplay(& $line, BusinessProcess $bp)
    {
        list($display, $name, $desc) = preg_split('~\s*;\s*~', substr($line, 8), 3);
        $bp->getNode($name)->setAlias($desc)->setDisplay($display);
        if ($display > 0) {
            $bp->addRootNode($name);
        }
    }

    protected function parseExternalInfo(& $line, BusinessProcess $bp)
    {
        list($name, $script) = preg_split('~\s*;\s*~', substr($line, 14), 2);
        $bp->getNode($name)->setInfoCommand($script);
    }

    protected function parseExtraInfo(& $line, BusinessProcess $bp)
    {
        // TODO: Not yet
        // list($name, $script) = preg_split('~\s*;\s*~', substr($line, 14), 2);
        // $this->getNode($name)->setExtraInfo($script);
    }

    protected function parseInfoUrl(& $line, BusinessProcess $bp)
    {
        list($name, $url) = preg_split('~\s*;\s*~', substr($line, 9), 2);
        $bp->getNode($name)->setInfoUrl($url);
    }

    protected function parseExtraLine(& $line, $typeLength, BusinessProcess $bp)
    {
        $type = substr($line, 0, $typeLength);
        if (substr($type, 0, 7) === 'display') {
            $this->parseDisplay($line, $bp);
            return true;
        }

        switch ($type) {
            case 'external_info':
                $this->parseExternalInfo($line, $bp);
                break;
            case 'extra_info':
                $this->parseExtraInfo($line, $bp);
                break;
            case 'info_url':
                $this->parseInfoUrl($line, $bp);
                break;
            default:
                return false;
        }

        return true;
    }

    /**
     * Parses a single line
     *
     * Adds eventual new knowledge to the given Business Process config
     *
     * @param $line
     * @param $bp

     */
    protected function parseLine(& $line, BusinessProcess $bp)
    {
        $line = trim($line);

        $this->parsing_line_number++;

        // Skip empty or comment-only lines
        if (empty($line) || $line[0] === '#') {
            return;
        }

        // Semicolon found in the first 14 cols? Might be a line with extra information
        $pos = strpos($line, ';');
        if ($pos !== false && $pos < 14) {
            if ($this->parseExtraLine($line, $pos, $bp)) {
                return;
            }
        }

        list($name, $value) = preg_split('~\s*=\s*~', $line, 2);

        if (strpos($name, ';') !== false) {
            $this->parseError('No semicolon allowed in varname');
        }

        $op = '&';
        if (preg_match_all('~([\|\+&\!])~', $value, $m)) {
            $op = implode('', $m[1]);
            for ($i = 1; $i < strlen($op); $i++) {
                if ($op[$i] !== $op[$i - 1]) {
                    $this->parseError('Mixing operators is not allowed');
                }
            }
        }
        $op = $op[0];
        $op_name = $op;

        if ($op === '+') {
            if (! preg_match('~^(\d+)(?::(\d+))?\s*of:\s*(.+?)$~', $value, $m)) {
                $this->parseError('syntax: <var> = <num> of: <var1> + <var2> [+ <varn>]*');
            }
            $op_name = $m[1];
            // New feature: $minWarn = $m[2];
            $value   = $m[3];
        }
        $cmps = preg_split('~\s*\\' . $op . '\s*~', $value, -1, PREG_SPLIT_NO_EMPTY);
        $childNames = array();

        foreach ($cmps as $val) {
            if (strpos($val, ';') !== false) {
                if ($bp->hasNode($val)) {
                    continue;
                }

                list($host, $service) = preg_split('~;~', $val, 2);
                if ($service === 'Hoststatus') {
                    $bp->createHost($host);
                } else {
                    $bp->createService($host, $service);
                }
            }
            if ($val[0] === '@') {
                if (strpos($val, ':') === false) {
                    throw new ConfigurationError(
                        "I'm unable to import full external configs, a node needs to be provided for '%s'",
                        $val
                    );
                    // TODO: this might work:
                    // $node = $bp->createImportedNode(substr($val, 1));
                } else {
                    list($config, $nodeName) = preg_split('~:\s*~', substr($val, 1), 2);
                    $node = $bp->createImportedNode($config, $nodeName);
                }
                $val = $node->getName();
            }

            $childNames[] = $val;
        }

        $node = new BpNode($bp, (object) array(
            'name'        => $name,
            'operator'    => $op_name,
            'child_names' => $childNames
        ));

        $bp->addNode($name, $node);
    }

    protected function parseError($msg)
    {
        throw new ConfigurationError(
            sprintf(
                'Parse error on %s:%s: %s',
                $this->currentFilename,
                $this->parsing_line_number,
                $msg
            )
        );
    }
}
