<?php

namespace Icinga\Module\Businessprocess\Storage;

use DirectoryIterator;
use Icinga\Application\Benchmark;
use Icinga\Application\Icinga;
use Icinga\Authentication\Auth;
use Icinga\Exception\ConfigurationError;
use Icinga\Module\Businessprocess\BpNode;
use Icinga\Module\Businessprocess\BusinessProcess;
use Icinga\Exception\SystemPermissionException;

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
     * @return array
     */
    public function listProcesses()
    {
        $files = array();

        foreach (new DirectoryIterator($this->getConfigDir()) as $file) {
            if ($file->isDot()) {
                continue;
            }

            $filename = $file->getFilename();
            if (substr($filename, -5) === '.conf') {
                $name = substr($filename, 0, -5);
                $header = $this->readHeader($file->getPathname());
                if (! $this->headerPermissionsAreSatisfied($header)) {
                    continue;
                }

                if ($header['Title'] === null) {
                    $files[$name] = $name;
                } else {
                    $files[$name] = sprintf('%s (%s)', $header['Title'], $name);
                }
            }
        }

        natsort($files);
        return $files;
    }

    protected function headerPermissionsAreSatisfied($header)
    {
        if (Icinga::app()->isCli()) {
            return true;
        }

        if ($header['Allowed users'] === null
            && $header['Allowed groups'] === null
            && $header['Allowed roles'] === null
        ) {
            return true;
        }

        $auth = Auth::getInstance();
        if (! $auth->isAuthenticated()) {
            return false;
        }

        $user = $auth->getUser();
        $username = $user->getUsername();

        if ($header['Owner'] === $username) {
            return true;
        }

        if ($header['Allowed users'] !== null) {
            $users = $this->splitCommaSeparated($header['Allowed users']);
            foreach ($users as $allowedUser) {
                if ($username === $allowedUser) {
                    return true;
                }
            }
        }

        if ($header['Allowed groups'] !== null) {
            $groups = $this->splitCommaSeparated($header['Allowed groups']);
            foreach ($groups as $group) {
                if ($user->isMemberOf($group)) {
                    return true;
                }
            }
        }

        if ($header['Allowed roles'] !== null) {
            // TODO: not implemented yet
            return false;
        }

        return false;
    }

    protected function splitCommaSeparated($string)
    {
        return preg_split('/\s*,\s*/', $string, -1, PREG_SPLIT_NO_EMPTY);
    }

    protected function readHeader($file)
    {
        $fh = fopen($file, 'r');
        $cnt = 0;
        $header = $this->emptyHeader();
        while ($cnt < 15 && false !== ($line = fgets($fh))) {
            $cnt++;
            $this->parseHeaderLine($line, $header);
        }

        fclose($fh);
        return $header;
    }

    protected function readHeaderString($string)
    {
        $header = $this->emptyHeader();
        foreach (preg_split('/\n/', $string) as $line) {
            $this->parseHeaderLine($line, $header);
        }

        return $header;
    }

    protected function emptyHeader()
    {
        return array(
            'Title'          => null,
            'Owner'          => null,
            'Allowed users'  => null,
            'Allowed groups' => null,
            'Allowed roles'  => null,
            'Backend'        => null,
            'Statetype'      => 'soft',
            'SLA Hosts'      => null
        );
    }

    protected function parseHeaderLine($line, & $header)
    {
        if (preg_match('/^\s*#\s+(.+?)\s*:\s*(.+)$/', $line, $m)) {
            if (array_key_exists($m[1], $header)) {
                $header[$m[1]] = $m[2];
            }
        }
    }

    /**
     * @param BusinessProcess $process
     */
    public function storeProcess(BusinessProcess $process)
    {
        $filename = $this->getFilename($process->getName());
        $content = $process->toLegacyConfigString();
        file_put_contents(
            $filename,
            $content
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

    public function loadFromString($name, $string)
    {
        $bp = new BusinessProcess();
        $bp->setName($name);
        $this->parseString($string, $bp);
        $this->readHeaderString($string);
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

        $header = $this->readHeader($file);
        $bp = new BusinessProcess();
        $this->loadHeader($name, $bp);
        return $this->headerPermissionsAreSatisfied($header);
    }

    /**
     * @param string $name
     * @param BusinessProcess $bp
     */
    protected function loadHeader($name, $bp)
    {
        // TODO: do not open twice, this is quick and dirty based on existing code
        $file = $this->currentFilename = $this->getFilename($name);
        $header = $this->readHeader($file);
        $this->applyHeader($header, $bp);
    }

    /**
     * @param array $header
     * @param BusinessProcess $bp
     */
    protected function applyHeader($header, $bp)
    {
        $bp->setTitle($header['Title']);
        if ($header['Backend']) {
            $bp->setBackendName($header['Backend']);
        }
        if ($header['Statetype'] === 'soft') {
            $bp->useSoftStates();
        }
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

    protected function parseString($string, $bp)
    {
        foreach (preg_split('/\n/', $string) as $line) {
            $this->parseLine($line, $bp);
        }
    }

    protected function parseLine(& $line, $bp)
    {
        $line = trim($line);

        $this->parsing_line_number++;

        if (empty($line)) {
            return;
        }
        if ($line[0] === '#') {
            return;
        }

        // TODO: substr?
        if (preg_match('~^display~', $line)) {
            list($display, $name, $desc) = preg_split('~\s*;\s*~', substr($line, 8), 3);
            $node = $bp->getNode($name)->setAlias($desc)->setDisplay($display);
            if ($display > 0) {
                $bp->addRootNode($name);
            }
            return;
        }

        if (preg_match('~^external_info~', $line)) {
            list($name, $script) = preg_split('~\s*;\s*~', substr($line, 14), 2);
            $node = $bp->getNode($name)->setInfoCommand($script);
            return;
        }

        // New feature:
        // if (preg_match('~^extra_info~', $line)) {
        //     list($name, $script) = preg_split('~\s*;\s*~', substr($line, 14), 2);
        //     $node = $this->getNode($name)->setExtraInfo($script);
        // }

        if (preg_match('~^info_url~', $line)) {
            list($name, $url) = preg_split('~\s*;\s*~', substr($line, 9), 2);
            $node = $bp->getNode($name)->setInfoUrl($url);
            return;
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
            if ($val[0] === '@' && strpos($val, ':') !== false) {
                list($config, $nodeName) = preg_split('~:\s*~', substr($val, 1), 2);
                $bp->createImportedNode($config, $nodeName);
                $val = $nodeName;
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
