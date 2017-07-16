<?php

namespace proj4php;

include(__DIR__ . "/../vendor/autoload.php");

use proj4php\Ellipsoid;
use proj4php\Datum;

class DatumTest extends \PHPUnit_Framework_TestCase
{
    /**
     *
     */
    public function testOne()
    {
        $e = new Ellipsoid();
        $d = new Datum($e, '10,20,30');
        $d = new Datum($e, '10,20,30,0.01,0.02,0.03,1');
    }
}
