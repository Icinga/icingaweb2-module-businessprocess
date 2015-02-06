<?php

namespace Icinga\Module\Businessprocess\Storage;

use Icinga\Application\Icinga;
use Icinga\Data\ConfigObject;
use Icinga\Module\Businessprocess\BusinessProcess;
use Icinga\Module\Businessprocess\BpNode;
use Icinga\Module\Businessprocess\Storage\Storage;
use DirectoryIterator;
use Icinga\Exception\SystemPermissionException;

class LegacyStorage extends Storage
{
    protected $configDir;

    protected $parsing_line_number;

    public function getConfigDir()
    {
        if ($this->configDir === null) {
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
        return $this->configDir;
    }

    /**
     * @return array
     */
    public function listProcesses()
    {
        $files = array();
        foreach (new DirectoryIterator($this->getConfigDir()) as $file) {
            if($file->isDot()) continue;
            $filename = $file->getFilename();
            if (substr($filename, -5) === '.conf') {
                $name = substr($filename, 0, -5);
                $header = $this->readHeader($file->getPathname(), $name);
                $files[$name] = $header['Title'];
            }
        }
        return $files;
    }

    protected function readHeader($file, $name)
    {
        $fh = fopen($file, 'r');
        $cnt = 0;
        $header = array(
            'Title'     => $name,
            'Owner'     => null,
            'Backend'   => null,
            'Statetype' => 'soft',
            'SLA Hosts' => null
        );
        while ($cnt < 15 && false !== ($line = fgets($fh))) {
            $cnt++;
            if (preg_match('/^\s*#\s+(.+?)\s*:\s*(.+)$/', $line, $m)) {
                if (array_key_exists($m[1], $header)) {
                    $header[$m[1]] = $m[2];
                }
            }
        }
        return $header;
    }

    /**
     * @return BusinessProcess
     */
    public function loadProcess($name)
    {
        // Parse
        return $this->parse(
            $this->getConfigDir() . '/' . $name . '.conf',
            $name
        );
    }

    /**
     */
    public function storeProcess(BusinessProcess $name)
    {
    }


    protected function parse($file, $name)
    {
        $bp = new BusinessProcess();
        $bp->setName($name);
        $fh = @fopen($file, 'r');
        if (! $fh) {
            throw new SystemPermissionException('Could not open ' . $file);
        }

        $this->parsing_line_number = 0;
        while ($line = fgets($fh)) {
            $line = trim($line);

            $this->parsing_line_number++;

            if (empty($line)) {
                continue;
            }
            if ($line[0] === '#') {
                continue;
            }

            // TODO: substr?
            if (preg_match('~^display~', $line)) {
                list($display, $name, $desc) = preg_split('~\s*;\s*~', substr($line, 8), 3);
                $node = $bp->getNode($name)->setAlias($desc)->setDisplay($display);
                if ($display > 0) {
                    $bp->addRootNode($name);
                }
            }

            if (preg_match('~^external_info~', $line)) {
                list($name, $script) = preg_split('~\s*;\s*~', substr($line, 14), 2);
                $node = $bp->getNode($name)->setInfoCommand($script);
            }

            // New feature:
            // if (preg_match('~^extra_info~', $line)) {
            //     list($name, $script) = preg_split('~\s*;\s*~', substr($line, 14), 2);
            //     $node = $this->getNode($name)->setExtraInfo($script);
            // }

            if (preg_match('~^info_url~', $line)) {
                list($name, $url) = preg_split('~\s*;\s*~', substr($line, 9), 2);
                $node = $bp->getNode($name)->setInfoUrl($url);
            }

            if (strpos($line, '=') === false) {
                continue;
            }
            
            list($name, $value) = preg_split('~\s*=\s*~', $line, 2);

            if (strpos($name, ';') !== false) {
                $this->parseError('No semicolon allowed in varname');
            }

            $op = '&';
            if (preg_match_all('~([\|\+&])~', $value, $m)) {
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
                if (! preg_match('~^(\d+)\s*of:\s*(.+?)$~', $value, $m)) {
                    $this->parseError('syntax: <var> = <num> of: <var1> + <var2> [+ <varn>]*');
                }
                $op_name = $m[1];
                $value   = $m[2];
            }
            $cmps = preg_split('~\s*\\' . $op . '\s*~', $value);

            foreach ($cmps as & $val) {
                if (strpos($val, ';') !== false) {
                    if ($bp->hasNode($val)) continue;

                    list($host, $service) = preg_split('~;~', $val, 2);
                    if ($service === 'Hoststatus') {
                        $bp->createHost($host);
                    } else {
                        $bp->createService($host, $service);
                    }
                }
            }

            $node = new BpNode($bp, (object) array(
                'name'        => $name,
                'operator'    => $op_name,
                'child_names' => $cmps
            ));
            $bp->addNode($name, $node);
        }

        fclose($fh);
        unset($this->parsing_line_number);

        // TODO: do not open twice, this is quick and dirty based on existing code
        $header = $this->readHeader($file, $name);
        $bp->setTitle($header['Title']);
        if ($header['Backend']) {
            $bp->loadBackendByName($header['Backend']);
        }

        return $bp;
    }

    protected function parseError($msg)
    {
        throw new Exception(
            sprintf(
                'Parse error on %s:%s: %s',
                $this->filename,
                $this->parsing_line_number,
                $msg
            )
        );
    }
}
