<?php

class Zend_View_Helper_RenderStateBadges extends Zend_View_Helper_Abstract
{
    protected $stateNames = array(
        0 => 'OK',
        1 => 'WARNING',
        2 => 'CRITICAL',
        3 => 'UNKNOWN',
        99 => 'PENDING',
    );

    public function renderStateBadges($summary)
    {
        $html = '';

        foreach ($summary as $state => $cnt) {
            if ($cnt === 0) continue;
            if ($state === 0) continue;
            $stateName =  $this->stateNames[$state];
            $html .= '<span class="badge badge-' . strtolower($stateName)
                   . '" title="' . mt('monitoring', $stateName) . '">'
                   . $cnt . '</span>';
        }
    
        if ($html !== '') {
            $html = '<div class="badges">' . $html . '</div>';
        }

        return $html;
    }
}
