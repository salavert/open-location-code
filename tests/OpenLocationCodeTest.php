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
     * @dataProvider validFullCodes
     */
    public function testValidFullCodes($code)
    {
        $this->assertTrue($this->olc->isValid($code));
        $this->assertFalse($this->olc->isShort($code));
        $this->assertTrue($this->olc->isFull($code));
    }

    /**
     * @dataProvider validShortCodes
     */
    public function testValidShortCodes($code)
    {
        $this->assertTrue($this->olc->isValid($code));
        $this->assertTrue($this->olc->isShort($code));
        $this->assertFalse($this->olc->isFull($code));
    }

    /**
     * @dataProvider invalidCodes
     */
    public function testInvalidCodes($code)
    {
        $this->assertFalse($this->olc->isValid($code));
        $this->assertFalse($this->olc->isShort($code));
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

    /**
     * @dataProvider shortCodes
     */
    public function testEncodeShortCodes($fullCode, $latitude, $longitude)
    {
        $this->assertEquals($fullCode, $this->olc->encode($latitude,$longitude));
    }

    public function shortCodes()
    {
        # Format: full code, lat, lng
        return array(
            array('9C3W9QCJ+2V', 51.3701125, -1.217765625, '+2V'),

            # Adjust so we can't trim by 8 (+/- .000755)
            array('9C3W9QCJ+8V', 51.3708675, -1.217765625),
            array('9C3W9Q9J+PV', 51.3693575, -1.217765625),
            array('9C3W9QCJ+2H', 51.3701125, -1.218520625),
            array('9C3W9QCM+25', 51.3701125, -1.217010625),

            ## Adjust so we can't trim by 6 (+/- .0151)
            array('9C3W9QPJ+3V', 51.3852125, -1.217765625),
            array('9C3W9Q4J+2V', 51.3550125, -1.217765625),
            array('9C3W9QC8+2V', 51.3701125, -1.232865625),
            array('9C3W9QCW+2W', 51.3701125, -1.202665625),
        );
    }
}
