<?php
/**
 *
 * ThinkUp/tests/classes/class.ThinkUpBasicWebTestCase.php
 *
 * Copyright (c) 2009-2010 Gina Trapani
 *
 * LICENSE:
 *
 * This file is part of ThinkUp (http://thinkupapp.com).
 *
 * ThinkUp is free software: you can redistribute it and/or modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation, either version 2 of the License, or (at your option) any
 * later version.
 *
 * ThinkUp is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
 * details.
 *
 * You should have received a copy of the GNU General Public License along with ThinkUp.  If not, see
 * <http://www.gnu.org/licenses/>.
 *
 *
 * @author Gina Trapani <ginatrapani[at]gmail[dot]com>
 * @license http://www.gnu.org/licenses/gpl.html
 * @copyright 2009-2010 Gina Trapani
 */
class ThinkUpBasicWebTestCase extends WebTestCase {
    /**
     *
     * @var str The test web server URL, ie, http://dev.thinkup.com
     */
    var $url;

    public function setUp() {
        global $TEST_SERVER_DOMAIN;

        $this->url = $TEST_SERVER_DOMAIN;
        $this->DEBUG = isset($_ENV['TEST_DEBUG']) ? true : false;
    }

    public function tearDown() {
    }

    public function debug($message) {
        if($this->DEBUG) {
            $bt = debug_backtrace();
            print get_class($this) . ": line " . $bt[0]['line'] . " - " . $message . "\n";
        }
    }
}
