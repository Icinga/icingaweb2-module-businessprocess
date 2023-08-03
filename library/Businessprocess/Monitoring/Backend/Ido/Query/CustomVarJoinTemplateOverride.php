<?php

namespace Icinga\Module\Businessprocess\Monitoring\Backend\Ido\Query;

use Icinga\Module\Monitoring\Backend\Ido\Query\ServicecommenthistoryQuery;
use Icinga\Module\Monitoring\Backend\Ido\Query\ServicecommentQuery;
use Icinga\Module\Monitoring\Backend\Ido\Query\ServicedowntimeQuery;
use Icinga\Module\Monitoring\Backend\Ido\Query\ServicedowntimestarthistoryQuery;
use Icinga\Module\Monitoring\Backend\Ido\Query\ServiceflappingstarthistoryQuery;
use Icinga\Module\Monitoring\Backend\Ido\Query\ServicegroupQuery;
use Icinga\Module\Monitoring\Backend\Ido\Query\ServicenotificationQuery;
use Icinga\Module\Monitoring\Backend\Ido\Query\ServicestatehistoryQuery;
use Zend_Db_Select;

trait CustomVarJoinTemplateOverride
{
    private $customVarsJoinTemplate = '%1$s = %2$s.object_id AND %2$s.varname LIKE %3$s';

    /**
     * This is a 1:1 copy of {@see IdoQuery::joinCustomvar()} to be able to
     * adjust {@see IdoQuery::$customVarsJoinTemplate} as it's private
     */
    protected function joinCustomvar($customvar)
    {
        // TODO: This is not generic enough yet
        list($type, $name) = $this->customvarNameToTypeName($customvar);
        $alias = ($type === 'host' ? 'hcv_' : 'scv_') . preg_replace('~[^a-zA-Z0-9_]~', '_', $name);

        // We're replacing any problematic char with an underscore, which will lead to duplicates, this avoids them
        $from = $this->select->getPart(Zend_Db_Select::FROM);
        for ($i = 2; array_key_exists($alias, $from); $i++) {
            $alias = $alias . '_' . $i;
        }

        $this->customVars[strtolower($customvar)] = $alias;

        if ($type === 'host') {
            if ($this instanceof ServicecommentQuery
                || $this instanceof ServicedowntimeQuery
                || $this instanceof ServicecommenthistoryQuery
                || $this instanceof ServicedowntimestarthistoryQuery
                || $this instanceof ServiceflappingstarthistoryQuery
                || $this instanceof ServicegroupQuery
                || $this instanceof ServicenotificationQuery
                || $this instanceof ServicestatehistoryQuery
                || $this instanceof \Icinga\Module\Monitoring\Backend\Ido\Query\ServicestatusQuery
            ) {
                $this->requireVirtualTable('services');
                $leftcol = 's.host_object_id';
            } else {
                $leftcol = 'ho.object_id';
                if (! $this->hasJoinedTable('ho')) {
                    $this->requireVirtualTable('hosts');
                }
            }
        } else { // $type === 'service'
            $leftcol = 'so.object_id';
            if (! $this->hasJoinedTable('so')) {
                $this->requireVirtualTable('services');
            }
        }

        $mapped = $this->getMappedField($leftcol);
        if ($mapped !== null) {
            $this->requireColumn($leftcol);
            $leftcol = $mapped;
        }

        $joinOn = sprintf(
            $this->customVarsJoinTemplate,
            $leftcol,
            $alias,
            $this->db->quote($name)
        );

        $this->select->joinLeft(
            array($alias => $this->prefix . 'customvariablestatus'),
            $joinOn,
            array()
        );

        return $this;
    }
}
