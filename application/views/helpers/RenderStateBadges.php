<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

/**
 * @deprecated
 */
class Zend_View_Helper_RenderStateBadges extends Zend_View_Helper_Abstract // phpcs:ignore
{
    public function renderStateBadges($summary)
    {
        $html = '';

        foreach ($summary as $state => $cnt) {
            if (
                $cnt === 0
                || $state === 'OK'
                || $state === 'UP'
            ) {
                continue;
            }

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
