<?php

namespace Salavert\Tests;

use Salavert\OpenLocationCode;

class OpenLocationCodeTest extends \PHPUnit_Framework_TestCase {

    public function setUp() {
        $this->olc = new OpenLocationCode();
    }

    /**
     * @dataProvider validShortCodes
     */
    public function testIsValidWithValidShortCodes($code)
    {
        $this->assertTrue($this->olc->isValid($code));
    }

    /**
     * @dataProvider validFullCodes
     */
    public function testIsValidWithValidFullCodes($code)
    {
        $this->assertTrue($this->olc->isValid($code));
    }

    public function validShortCodes()
    {
        return array(
            array('WC2345+G6g'),
            array('2345+G6'),
            array('45+G6'),
            array('+G6'),
        );
    }

    public function validFullCodes()
    {
        return array(
            array('8FWC2345+G6'),
            array('8FWC2345+G6G'),
            array('8fwc2345+'),
            array('8FWCX400+'),
        );
    }

}
