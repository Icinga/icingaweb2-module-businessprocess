<?php

namespace Icinga\Module\Businessprocess\Web\Form;

use Icinga\Application\Icinga;
use Icinga\Application\Modules\Module;
use Icinga\Exception\ProgrammingError;

class FormLoader
{
    public static function load($name, Module $module = null)
    {
        if ($module === null) {
            $basedir = Icinga::app()->getApplicationDir('forms');
            $ns = '\\Icinga\\Web\\Forms\\';
        } else {
            $basedir = $module->getFormDir();
            $ns = '\\Icinga\\Module\\' . ucfirst($module->getName()) . '\\Forms\\';
        }

        $file = null;
        if (preg_match('~^[a-z0-9/]+$~i', $name)) {
            $parts = preg_split('~/~', $name);
            $class = ucfirst(array_pop($parts)) . 'Form';
            $file = sprintf('%s/%s/%s.php', rtrim($basedir, '/'), implode('/', $parts), $class);
            if (file_exists($file)) {
                require_once($file);
                $class = $ns . $class;
                $options = array();
                if ($module !== null) {
                    $options['icingaModule'] = $module;
                }

                return new $class($options);
            }
        }
        throw new ProgrammingError(sprintf('Cannot load %s (%s), no such form', $name, $file));
    }
}
