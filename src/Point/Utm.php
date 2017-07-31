<?php

namespace proj4php\Point;

/**
 * A UTM point, with x, y, zone and north/south indicator.
 */

class Utm // implements XxxxInterface
{
    protected $x;
    protected $y;
    protected $zone;
    protected $south = false;

    protected $projection;
    protected $datum;

    public function __construct()
    {
    }

    public function fromGeodetic(Geodetic $geodetic)
    {
    }
}
