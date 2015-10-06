<?php

class Zend_View_Helper_RenderStateBadges extends Zend_View_Helper_Abstract
{
    public function renderStateBadges($summary)
    {
        $html = '';

        foreach ($summary as $state => $cnt) {
            if ($cnt === 0) continue;
            if ($state === 'OK') continue;
            if ($state === 'UP') continue;
            $html .= '<span class="badge badge-' . strtolower($state)
                   . '" title="' . mt('monitoring', $state) . '">'
                   . $cnt . '</span>';
        }
    
        if ($html !== '') {
            $html = '<div class="badges">' . $html . '</div>';
        }

        return $html;
    }
}
