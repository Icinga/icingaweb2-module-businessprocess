<?php

namespace Icinga\Module\Businessprocess\Storage;

use Icinga\Application\Benchmark;
use Icinga\Exception\ConfigurationError;
use Icinga\Exception\SystemPermissionException;
use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\BpNode;
use Icinga\Module\Businessprocess\Metadata;

class LegacyConfigParser
{
    /** @var int */
    protected $currentLineNumber;

    /** @var string */
    protected $currentFilename;

    protected $name;

    /** @var BpConfig */
    protected $config;

    /**
     * LegacyConfigParser constructor
     *
     * @param $name
     */
    private function __construct($name)
    {
        $this->name = $name;
        $this->config = new BpConfig();
        $this->config->setName($name);
    }

    /**
     * @return BpConfig
     */
    public function getParsedConfig()
    {
        return $this->config;
    }

    /**
     * @param $name
     * @param $filename
     *
     * @return BpConfig
     */
    public static function parseFile($name, $filename)
    {
        Benchmark::measure('Loading business process ' . $name);
        $parser = new static($name);
        $parser->reallyParseFile($filename);
        Benchmark::measure('Business process ' . $name . ' loaded');
        return $parser->getParsedConfig();
    }

    /**
     * @param $name
     * @param $string
     *
     * @return BpConfig
     */
    public static function parseString($name, $string)
    {
        Benchmark::measure('Loading BP config from file: ' . $name);
        $parser = new static($name);

        $config = $parser->getParsedConfig();
        $config->setMetadata(
            static::readMetadataFromString($name, $string)
        );

        foreach (preg_split('/\r?\n/', $string) as $line) {
            $parser->parseLine($line);
        }

        Benchmark::measure('Business process ' . $name . ' loaded');
        return $config;
    }

    protected function reallyParseFile($filename)
    {
        $file = $this->currentFilename = $filename;
        $fh = @fopen($file, 'r');
        if (! $fh) {
            throw new SystemPermissionException('Could not open "%s"', $filename);
        }

        $config = $this->config;
        $config->setMetadata(
            $this::readMetadataFromFileHeader($config->getName(), $filename)
        );

        $this->currentLineNumber = 0;
        while ($line = fgets($fh)) {
            $this->parseLine($line);
        }

        fclose($fh);
        unset($this->currentLineNumber);
        unset($this->currentFilename);
    }

    public static function readMetadataFromFileHeader($name, $filename)
    {
        $metadata = new Metadata($name);
        $fh = fopen($filename, 'r');
        $cnt = 0;
        while ($cnt < 15 && false !== ($line = fgets($fh))) {
            $cnt++;
            static::parseHeaderLine($line, $metadata);
        }

        fclose($fh);
        return $metadata;
    }

    public static function readMetadataFromString($name, & $string)
    {
        $metadata = new Metadata($name);

        $lines = preg_split('/\r?\n/', substr($string, 0, 8092));
        foreach ($lines as $line) {
            static::parseHeaderLine($line, $metadata);
        }

        return $metadata;
    }

    protected function splitCommaSeparated($string)
    {
        return preg_split('/\s*,\s*/', $string, -1, PREG_SPLIT_NO_EMPTY);
    }

    protected function readHeaderString($string, Metadata $metadata)
    {
        foreach (preg_split('/\r?\n/', $string) as $line) {
            $this->parseHeaderLine($line, $metadata);
        }

        return $metadata;
    }

    /**
     * @return array
     */
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

    /**
     * @param $line
     * @param Metadata $metadata
     */
    protected static function parseHeaderLine($line, Metadata $metadata)
    {
        if (preg_match('/^\s*#\s+(.+?)\s*:\s*(.+)$/', trim($line), $m)) {
            if ($metadata->hasKey($m[1])) {
                $metadata->set($m[1], $m[2]);
            }
        }
    }

    /**
     * @param $line
     * @param BpConfig $bp
     */
    protected function parseDisplay(& $line, BpConfig $bp)
    {
        list($display, $name, $desc) = preg_split('~\s*;\s*~', substr($line, 8), 3);
        $bp->getBpNode($name)->setAlias($desc)->setDisplay($display);
        if ($display > 0) {
            $bp->addRootNode($name);
        }
    }

    /**
     * @param $line
     * @param BpConfig $bp
     */
    protected function parseExternalInfo(& $line, BpConfig $bp)
    {
        list($name, $script) = preg_split('~\s*;\s*~', substr($line, 14), 2);
        $bp->getBpNode($name)->setInfoCommand($script);
    }

    protected function parseExtraInfo(& $line, BpConfig $bp)
    {
        // TODO: Not yet
        // list($name, $script) = preg_split('~\s*;\s*~', substr($line, 14), 2);
        // $this->getNode($name)->setExtraInfo($script);
    }

    protected function parseInfoUrl(& $line, BpConfig $bp)
    {
        list($name, $url) = preg_split('~\s*;\s*~', substr($line, 9), 2);
        $bp->getBpNode($name)->setInfoUrl($url);
    }

    protected function parseExtraLine(& $line, $typeLength, BpConfig $bp)
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
            case 'template':
                // compat, ignoring for now
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
     *
     * @throws ConfigurationError
     */
    protected function parseLine(& $line)
    {
        $bp = $this->config;
        $line = trim($line);

        $this->currentLineNumber++;

        // Skip empty or comment-only lines
        if (empty($line) || $line[0] === '#') {
            return;
        }

        // Space found in the first 14 cols? Might be a line with extra information
        $pos = strpos($line, ' ');
        if ($pos !== false && $pos < 14) {
            if ($this->parseExtraLine($line, $pos, $bp)) {
                return;
            }
        }

        if (strpos($line, '=') === false) {
            $this->parseError('Got invalid line');
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
                    $childNames[] = $val;
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

    /**
     * @return string
     */
    public function getFilename()
    {
        return $this->currentFilename ?: '[given string]';
    }

    /**
     * @param $msg
     * @throws ConfigurationError
     */
    protected function parseError($msg)
    {
        throw new ConfigurationError(
            sprintf(
                'Parse error on %s:%s: %s',
                $this->getFilename(),
                $this->currentLineNumber,
                $msg
            )
        );
    }
}
