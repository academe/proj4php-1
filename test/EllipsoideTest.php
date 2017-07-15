<?php

include(__DIR__ . "/../vendor/autoload.php");

use proj4php\Ellipsoid;

class EllipsoidTest extends PHPUnit_Framework_TestCase
{
    /**
     * Default settings for an ellipsoid.
     */
    public function testDefaultEllipsoid()
    {
        $e = new Ellipsoid();
        $p = $e->getParameters();

        $this->assertEquals($p, [
            "a" => 6378137,
            "rf" => 298.257223563,
            "code" => "WGS84",
            "name" => "WGS84",
        ]);

        $this->assertFalse($e->isSphere());
    }

    public function testIsSphere()
    {
        $e = Ellipsoid::fromAB(6356752, 6356752);

        $this->assertTrue($e->isSphere());
    }
}
