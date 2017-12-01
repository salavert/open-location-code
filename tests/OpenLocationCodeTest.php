<?php

namespace Salavert\Tests;

use Salavert\OpenLocationCode;
use Salavert\CodeArea;

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
     * @dataProvider openLocationCodesToEncode
     */
    public function testEncodeOLC($code, $lat, $lng, $length = null)
    {
        $this->assertEquals($code, $this->olc->encode($lat, $lng, $length));
    }

    public function openLocationCodesToEncode()
    {
        return array(
            array('9C3W9QCJ+2V', 51.3701125, -1.217765625),
            array('9C3W9QCJ+8V', 51.3708675, -1.217765625),
            array('9C3W9Q9J+PV', 51.3693575, -1.217765625),
            array('9C3W9QCJ+2H', 51.3701125, -1.218520625),
            array('9C3W9QCM+25', 51.3701125, -1.217010625),
            array('9C3W9QPJ+3V', 51.3852125, -1.217765625),
            array('9C3W9Q4J+2V', 51.3550125, -1.217765625),
            array('9C3W9QC8+2V', 51.3701125, -1.232865625),
            array('9C3W9QCW+2W', 51.3701125, -1.202665625),
            // picked from https://github.com/google/open-location-code/blob/master/test_data/encodingTests.csv
            array('4VCPPQGP+Q9', -41.2730625, 174.7859375),
            array('62G20000+', 0.5, -179.5, 4),
        );
    }


    /**
     * @dataProvider openLocationCodesToDecode
     */
    public function testDecodeOLC($code, $lat, $lng, $latLo, $lngLo, $latHi, $lngHi)
    {
        $codeArea = $this->olc->decode($code);
        $this->assertEquals($latLo, $codeArea->latitudeLo);
        $this->assertEquals($lngLo, $codeArea->longitudeLo);
        $this->assertEquals($latHi, $codeArea->latitudeHi);
        $this->assertEquals($lngHi, $codeArea->longitudeHi);
        $this->assertEquals($lat, $codeArea->latitudeCenter);
        $this->assertEquals($lng, $codeArea->longitudeCenter);
    }

    public function openLocationCodesToDecode()
    {
        # Format: code,lat,lng,latLo,lngLo,latHi,lngHi
        return array(
            array('7FG49Q00+', 20.375,2.775,20.35,2.75,20.4,2.8),
            array('7FG49QCJ+2V', 20.3700625,2.7821875,20.37,2.782125,20.370125,2.78225),
            array('7FG49QCJ+2VX', 20.3701125,2.782234375,20.3701,2.78221875,20.370125,2.78225),
            array('7FG49QCJ+2VXGJ', 20.3701135,2.78223535156,20.370113,2.782234375,20.370114,2.78223632813),
            array('8FVC2222+22', 47.0000625,8.0000625,47.0,8.0,47.000125,8.000125),
            array('4VCPPQGP+Q9', -41.2730625,174.7859375,-41.273125,174.785875,-41.273,174.786),
            array('62G20000+', 0.5,-179.5,0.0,-180.0,1,-179),
            array('22220000+', -89.5,-179.5,-90,-180,-89,-179),
            array('7FG40000+', 20.5,2.5,20.0,2.0,21.0,3.0),
            array('22222222+22', -89.9999375,-179.9999375,-90.0,-180.0,-89.999875,-179.999875),
            array('6VGX0000+', 0.5,179.5,0,179,1,180),

            # @todo Check following cases
            # Special cases over 90 latitude and 180 longitude
            #array('CFX30000+', 90,1,89,1,90,2),
            #array('CFX30000+', 92,1,89,1,90,2),
            #array('62H20000+', 1,180,1,-180,2,-179),
            #array('62H30000+', 1,181,1,-179,2,-178),
        );
    }

    /**
     * @dataProvider shortCodes
     */
    public function testShortenCodes($fullCode, $latitude, $longitude, $shortCode)
    {
        $this->assertEquals($shortCode, $this->olc->shorten($fullCode, $latitude, $longitude));
    }

    public function shortCodes()
    {
        # Format: full code, lat, lng
        return array(
            array('9C3W9QCJ+2VX', 51.3701125, -1.217765625, '+2VX'),

            # Adjust so we can't trim by 8 (+/- .000755)
            array('9C3W9QCJ+2VX', 51.3708675, -1.217765625, 'CJ+2VX'),
            array('9C3W9QCJ+2VX', 51.3693575, -1.217765625, 'CJ+2VX'),
            array('9C3W9QCJ+2VX', 51.3701125, -1.218520625, 'CJ+2VX'),
            array('9C3W9QCJ+2VX', 51.3701125, -1.217010625, 'CJ+2VX'),
            # Adjust so we can't trim by 6 (+/- .0151)
            array('9C3W9QCJ+2VX', 51.3852125, -1.217765625, '9QCJ+2VX'),
            array('9C3W9QCJ+2VX', 51.3550125, -1.217765625, '9QCJ+2VX'),
            array('9C3W9QCJ+2VX', 51.3701125, -1.232865625, '9QCJ+2VX'),
            array('9C3W9QCJ+2VX', 51.3701125, -1.202665625, '9QCJ+2VX'),
        );
    }
}
