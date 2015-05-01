<?php

namespace Salavert\Tests;

use Salavert\OpenLocationCode;

class OpenLocationCodeTest extends \PHPUnit_Framework_TestCase {

    /**
     * @var OpenLocationCode $olc
     */
    private $olc;

    public function setUp() {
        $this->olc = new OpenLocationCode();
    }

    /**
     * @dataProvider validShortCodes
     */
    public function testIsValidMethodWithValidShortCodes($code)
    {
        $this->assertTrue($this->olc->isValid($code));
    }

    /**
     * @dataProvider validFullCodes
     */
    public function testIsValidMethodWithValidFullCodes($code)
    {
        $this->assertTrue($this->olc->isValid($code));
    }

    /**
     * @dataProvider invalidCodes
     */
    public function testIsValidMethodWithInvalidCodes($code)
    {
        $this->assertFalse($this->olc->isValid($code));
    }

    /**
     * @dataProvider validShortCodes
     */
    public function testIsShortMethodWithValidShortCodes($code)
    {
        $this->assertTrue($this->olc->isShort($code));
    }

    /**
     * @dataProvider validFullCodes
     */
    public function testIsShortMethodWithValidFullCodes($code)
    {
        $this->assertFalse($this->olc->isShort($code));
    }

    /**
     * @dataProvider invalidCodes
     */
    public function testIsShortMethodWithInvalidCodes($code)
    {
        $this->assertFalse($this->olc->isShort($code));
    }

    /**
     * @dataProvider validShortCodes
     */
    public function testIsFullMethodWithValidShortCodes($code)
    {
        $this->assertFalse($this->olc->isFull($code));
    }

    /**
     * @dataProvider validFullCodes
     */
    public function testIsFullMethodWithValidFullCodes($code)
    {
        $this->assertTrue($this->olc->isFull($code));
    }

    /**
     * @dataProvider invalidCodes
     */
    public function testIsFullMethodWithInvalidCodes($code)
    {
        $this->assertFalse($this->olc->isFull($code));
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

    public function invalidCodes()
    {
        return array(
            array('8FWC2345+G'),
            array('8FWC2_45+G6'),
            array('8FWC2η45+G6'),
            array('8FWC2345+G6+'),
            array('8FWC2300+G6'),
            array('WC2300+G6g'),
            array('WC2345+G'),
        );
    }

}
