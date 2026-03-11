<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

// Avoid complaints about missing namespace and invalid class name
class Zend_View_Helper_FormSimpleNote extends Zend_View_Helper_FormElement // phpcs:ignore
{
    public function formSimpleNote($name, $value = null)
    {
        $info = $this->_getInfo($name, $value);
        extract($info); // name, value, attribs, options, listsep, disable
        return $value;
    }
}
