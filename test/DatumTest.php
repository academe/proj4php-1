<?php

namespace proj4php;

include(__DIR__ . "/../vendor/autoload.php");

use proj4php\Ellipsoid;
use proj4php\Datum;

class DatumTest extends \PHPUnit_Framework_TestCase
{
    protected $precision = 0.0001;

    /**
     *
     */
    public function testDefaults()
    {
        // Default.
        $d_wgs84_a = new Datum();

        // Default shift parameters.
        $e_wgs84 = new Ellipsoid();
        $d_wgs84_b = new Datum($e_wgs84);

        // The default without an ellisoid should be the same as the default
        // with a specified (but default) ellipsoid.
        $this->assertTrue($d_wgs84_a->isSame($d_wgs84_b));
    }

    /**
     *
     */
    public function testOSGB()
    {
        // OSGB36 from a and b.
        $mod_airy = Ellipsoid::fromAB(6377340.189, 6356034.446, 'mod_airy', 'Modified Airy');
        $d = new Datum($mod_airy, '446.448,-125.157,542.060,0.1502,0.2470,0.8421,-20.4894');

        $this->assertEquals(6377340.189, $d->getA(), '', $this->precision);
        $this->assertEquals(6356034.446, $d->getB(), '', $this->precision);
        $this->assertEquals(299.32493736548241, $d->getRf(), '', $this->precision);
        $this->assertEquals(0.0066705406058988111, $d->getEs(), '', $this->precision);
        $this->assertEquals(0.0067153355241951901, $d->getEs2(), '', $this->precision);
    }
}
