<?php

namespace proj4php\Projection;

/**
 * Author : Julien Moquet
 * 
 * Inspired by Proj4JS from Mike Adair madairATdmsolutions.ca
 * and Richard Greenwood rich@greenwoodma$p->com
 * License: LGPL as per: http://www.gnu.org/copyleft/lesser.html
 */
/*******************************************************************************
  NAME LAMBERT CONFORMAL CONIC

  PURPOSE:	Transforms input longitude and latitude to Easting and
  Northing for the Lambert Conformal Conic projection.  The
  longitude and latitude must be in radians.  The Easting
  and Northing values will be returned in meters.


  ALGORITHM REFERENCES

  1.  Snyder, John P., "Map Projections--A Working Manual", U.S. Geological
  Survey Professional Paper 1395 (Supersedes USGS Bulletin 1532), United
  State Government Printing Office, Washington D.C., 1987.

  2.  Snyder, John P. and Voxland, Philip M., "An Album of Map Projections",
  U.S. Geological Survey Professional Paper 1453 , United State Government
 *******************************************************************************/

//<2104> +proj=lcc +lat_1=10.16666666666667 +lat_0=10.16666666666667 +lon_0=-71.60561777777777 +k_0=1 +x0=-17044 +x0=-23139.97 +ellps=intl +units=m +no_defs  no_defs
// Initialize the Lambert Conformal conic projection
// -----------------------------------------------------------------

use proj4php\Point\Geodetic;
use proj4php\Point\Enu;

class Lcc extends AbstractProjection
{
    protected $title = 'Lambert Conformal Conic';

    // Parameters:

    protected $x_0;
    protected $y_0;

    // central latitude
    protected $lat_0 = 0.0;

    // first standard parallel
    protected $lat_1;

    // second standard parallel
    protected $lat_2;

    // Projection scale factor
    protected $k_0 = 1.0;

    // central longitude
    protected $lon_0;

    // Derived locally:

    protected $ns;
    protected $f0;
    protected $rh;

    /**
     * Set up the projection with the specific options.
     * This will eventually be generic across alll projections
     * and moved to the abstract. The projection then just needs
     * to indicate which parameters it needs, and perhaps offer
     * some additional validation on them, and then set up any
     * derived values.
     */
    public function __construct(array $options = [])
    {
        $this->parseOptions($options);

        // If lat2 is not defined
        if (! isset($this->lat_2)) {
            $this->lat_2 = $this->lat_0;
        }

        // SR-ORG:113
        if (! isset($this->lat_1)) {
            $this->lat_1 = $this->lat_0;
        }

        // Standard Parallels cannot be equal and on opposite sides of the equator
        if (abs($this->lat_1 + $this->lat_2) < static::EPSLN) {
            throw new \Exception(sprintf(
                'lcc: Equal Latitudes lat_1=%d and lat_2=%d',
                $this->lat_1,
                $this->lat_2
            ));
        }

        // TODO: the ellipsoid in the datum set for this projection.
        // Is $flat actually $sphere->getF()? Check out alternative derivations from a and rf.
        // Get these from the ellipsoid, if there is one. The ellipsoid
        // could in turn be in a datum.
        // To do this, accessors will be needed for properties, so they can be calculated
        // on-the-fly when needed.
        // TODO: these parameters could be supplied in the datum of the point being converted;
        // they may or may not be known in advance.

        $a = $this->getA();
        $e = $this->getE();

        $sin1 = sin($this->lat_1);
        $cos1 = cos($this->lat_1);

        $ms1 = $this->msfnz($e, $sin1, $cos1);
        $ts1 = $this->tsfnz($e, $this->lat_1, $sin1);

        $sin2 = sin($this->lat_2);
        $cos2 = cos($this->lat_2);

        $ms2 = $this->msfnz($e, $sin2, $cos2);
        $ts2 = $this->tsfnz($e, $this->lat_2, $sin2);

        $ts0 = $this->tsfnz($e, $this->lat_0, sin($this->lat_0));

        if (abs($this->lat_1 - $this->lat_2) > static::EPSLN) {
            $this->ns = log($ms1 / $ms2) / log($ts1 / $ts2);
        } else {
            $this->ns = $sin1;
        }

        $this->f0 = $ms1 / ($this->ns * pow($ts1, $this->ns));
        $this->rh = ($a * $this->f0 * pow($ts0, $this->ns));
    }

    /**
     * Lambert Conformal conic forward equations.
     * Map Geodetic to Enu.
     *
     * TODO: Get the ellipsoid parameters from the datum. The point may have a datum,
     * and the projection may have a datum. At least one must be supplied. If both are
     * set but the two do not match, then do a datum shift on the Geodetic point first.
     */
    public function forward(Geodetic $geodetic)
    {
        // Shift the datum of the point if it's not the same as the projecion.
        if ($geodetic->getDatum() && ! $geodetic->getDatum()->isSame($this->datum)) {
            $geodetic = $geodetic->shiftDatum($this->datum);
        }

        // Get the lat/long as radians.
        $lat = $geodetic->getLat(Geodetic::RADIANS);
        $long = $geodetic->getLong(Geodetic::RADIANS);

        // Ellipsoid parameters.
        $a = $this->getA();
        $e = $this->getE();

        // M_PI_2 is PI/2 or 90 degrees
        $con = abs(abs($lat) - M_PI_2);

        // CHECKME: presumably the projection will have a datum that the geodetic coordinate
        // must be aligned to. If they are not, then do we shift the coordinate datum first,
        // or perhaps raise an exception? Does this projection itself even get a datum?
        //$a = $this->a;

        if ($con > static::EPSLN) {
            $ts = $this->tsfnz($e, $lat, sin($lat));
            $rh1 = ($a * $this->f0 * pow($ts, $this->ns));
        } else {
            $con = $lat * $this->ns;

            if ($con <= 0) {
                Proj4php::reportError('lcc:forward: No Projection');
                return;
            }

            $rh1 = 0;
        }

        $theta = $this->ns * $this->adjust_lon($long - $this->lon_0);
        $x = $this->k_0 * ($rh1 * sin($theta)) + $this->x_0;
        $y = $this->k_0 * ($this->rh - $rh1 * cos($theta)) + $this->y_0;

        return new Enu(['easting' => $x, 'northing' => $y], $this->datum);
    }

    /**
     * Lambert Conformal Conic inverse equations--mapping x,y to lat/long
     * 
     * @param Enu $enu
     * @return Geodetic
     */
    public function inverse(Enu $enu)
    {
        // Shift the datum of the point if it's not the same as the projecion.
        if ($enu->getDatum() && ! $enu->getDatum()->isSame($this->datum)) {
            $enu = $enu->shiftDatum($this->datum);
        }

        $x = $enu->getX();
        $y = $enu->getY();

        // Ellipsoid parameters.
        $a = $this->getA();
        $e = $this->getE();

        $x = ($x - $this->x_0) / $this->k_0;
        $y = ($this->rh - ($y - $this->y_0) / $this->k_0);

        if ($this->ns > 0) {
            $rh1 = sqrt(($x * $x) + ($y * $y));
            $con = 1.0;
        } else {
            $rh1 = -sqrt(($x * $x) + ($y * $y));
            $con = -1.0;
        }

        $theta = 0.0;

        if ($rh1 != 0) {
            $theta = atan2($con * $x, $con * $y);
        }

        if ($rh1 != 0 || $this->ns > 0.0) {
            $con = 1.0 / $this->ns;
            $ts = pow(($rh1 / ($a * $this->f0)), $con);
            $lat = $this->phi2z($e, $ts);

            if ($lat == -9999) {
                return null;
            }
        } else {
            $lat = -M_PI_2;
        }

        $long = $this->adjust_lon($theta / $this->ns + $this->lon_0);

        return new Geodetic(
            ['lat' => rad2deg($lat), 'long' => rad2deg($long)],
            $this->datum
        );
    }
}
