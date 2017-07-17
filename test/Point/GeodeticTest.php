<?php

namespace proj4php\Point;

include(__DIR__ . "/../../vendor/autoload.php");

use proj4php\Datum;
use proj4php\Ellipsoid;

class GeodeticTest extends \PHPUnit_Framework_TestCase
{
    protected $accuracy = 0.0001;

    /**
     * Shift a point from one datum to another.
     */
    public function testDatumShift()
    {
        // WGS84 datum.
        $datum1 = new Datum();

        // OSGB datum.
        $mod_airy = Ellipsoid::fromAB(6377340.189, 6356034.446, 'mod_airy', 'Modified Airy');
        $datum2 = new Datum($mod_airy, '446.448,-125.157,542.060,0.1502,0.2470,0.8421,-20.4894');

        // Edinburgh WGS84.
        $point1 = new Geodetic([55.953251, -3.188267, 70], $datum1);

        // Edinburgh OSGB.
        $point2 = $point1->shiftDatum($datum2);

        // Assert original WGS84 coordinates.

        list($lat, $long, $height) = array_values($point1->toArray());

        $this->assertEquals(55.953251, $lat, '', $this->accuracy);
        $this->assertEquals(-3.188267, $long, '', $this->accuracy);
        $this->assertEquals(70, $height, '', $this->accuracy);

        // Assert shifted OSGB coordinates.
        // These still need to be checked against a third-party tool for correctness.

        list($lat, $long, $height) = array_values($point2->toArray());

        $this->assertEquals(55.957471156063, $lat, '', $this->accuracy);
        $this->assertEquals(-3.1973634975242, $long, '', $this->accuracy);
        $this->assertEquals(241.96584514156, $height, '', $this->accuracy);

        // The datum for the point can be shifted back to the original datum,
        // and the point coordinates will be back to roughly where they started at.
        $point3 = $point2->shiftDatum($datum1);
    }
}
