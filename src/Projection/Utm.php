<?php

namespace proj4php\Projection;

/**
 * Author : Julien Moquet
 * 
 * Inspired by Proj4JS from Mike Adair madairATdmsolutions.ca
 *                      and Richard Greenwood rich@greenwoodma$p->com 
 * License: LGPL as per: http://www.gnu.org/copyleft/lesser.html 
 */
/*******************************************************************************
  NAME                            TRANSVERSE MERCATOR

  PURPOSE:	Transforms input longitude and latitude to Easting and
  Northing for the Transverse Mercator projection.  The
  longitude and latitude must be in radians.  The Easting
  and Northing values will be returned in meters.

  ALGORITHM REFERENCES

  1.  Snyder, John P., "Map Projections--A Working Manual", U.S. Geological
  Survey Professional Paper 1395 (Supersedes USGS Bulletin 1532), United
  State Government Printing Office, Washington D.C., 1987.

  2.  Snyder, John P. and Voxland, Philip M., "An Album of Map Projections",
  U.S. Geological Survey Professional Paper 1453 , United State Government
  Printing Office, Washington D.C., 1989.
*******************************************************************************/

/**
  Initialize Transverse Mercator projection
 */

use proj4php\Point\Geodetic;

class Utm extends AbstractProjection
{
    protected $utmSouth = false;

    protected $a;
    protected $ep2;
    protected $es;
    protected $zone;

    protected $k_0 = 0.9996;
    protected $lat_0 = 0.0;
    protected $sphere = false;
    protected $x_0 = 500000.0;

    // Derived.
    protected $e0, $e1, $e2, $e3;
    protected $ml0;
    protected $lon_0;
    protected $y_0;

    public function __construct(array $options = [])
    {
        $this->parseOptions($options);

        if (! isset($this->zone)) {
            throw new \Exception('zone must be specified for UTM');
        }

        // TODO: where do we convert zone letters to numbers?
        $this->lon_0 = deg2rad((6 * abs($this->zone)) - 183);
        $this->y_0 = $this->utmSouth ? 10000000.0 : 0.0;

        $this->e0 = $this->e0fn($this->es);
        $this->e1 = $this->e1fn($this->es);
        $this->e2 = $this->e2fn($this->es);
        $this->e3 = $this->e3fn($this->es);

        $this->ml0 = $this->a * $this->mlfn($this->e0, $this->e1, $this->e2, $this->e3, $this->lat_0);
    }

    /**
     * Transverse Mercator Forward  - long/lat to x/y
     * long/lat in radians
     */
    public function forward(Geodetic $geodetic)
    {
        list($lat, $long) = array_values($geodetic->toArray(Geodetic::RADIANS));

        // Delta longitude
        $delta_lon = $this->adjust_lon($long - $this->lon_0 );

        $sin_phi = sin($lat);
        $cos_phi = cos($lat);

        if (isset($this->sphere) && $this->sphere === true) {
            // spherical form

            $b = $cos_phi * sin($delta_lon);

            if ((abs(abs($b) - 1.0)) < .0000000001) {
                throw new \Exception('tmerc:forward: Point projects into infinity');
            } else {
                $x = 0.5 * $this->a * $this->k_0 * log((1.0 + $b) / (1.0 - $b));
                $con = acos($cos_phi * cos($delta_lon) / sqrt(1.0 - $b * $b));

                if ($lat < 0) {
                    $con = -$con;
                }

                $y = $this->a * $this->k_0 * ($con - $this->lat_0);
            }
        } else {
            $al = $cos_phi * $delta_lon;
            $als = pow($al, 2);
            $c = $this->ep2 * pow($cos_phi, 2);
            $tq = tan($lat);
            $t = pow($tq, 2);
            $con = 1.0 - $this->es * pow($sin_phi, 2);
            $n = $this->a / sqrt($con);

            $ml = $this->a * $this->mlfn($this->e0, $this->e1, $this->e2, $this->e3, $lat);

            $x = $this->k_0 * $n * $al * (1.0 + $als / 6.0 * (1.0 - $t + $c + $als / 20.0 * (5.0 - 18.0 * $t + pow($t, 2) + 72.0 * $c - 58.0 * $this->ep2))) + $this->x_0;

            $y = $this->k_0 * ($ml - $this->ml0 + $n * $tq * ($als * (0.5 + $als / 24.0 * (5.0 - $t + 9.0 * $c + 4.0 * pow($c, 2) + $als / 30.0 * (61.0 - 58.0 * $t + pow($t, 2) + 600.0 * $c - 330.0 * $this->ep2))))) + $this->y_0;
        }

        return ['x' => $x, 'y' => $y];
    }

    /**
     * Transverse Mercator Inverse  -  x/y to long/lat
     */
    public function inverse($p)
    {
        // TODO: this will be a point object of some sort.
        list($x, $y) = array_values($p);

        // maximun number of iterations
        $max_iter = 6;

        if (isset($this->sphere) && $this->sphere === true) {
            // spherical form

            $f = exp($x / ($this->a * $this->k_0));
            $g = 0.5 * ($f - 1 / $f);
            $temp = $this->lat_0 + $y / ($this->a * $this->k_0);
            $h = cos($temp);
            $con = sqrt((1.0 - $h * $h) / (1.0 + $g * $g));
            $lat = $this->asinz( $con );

            if ($temp < 0) {
                $lat = -$lat;
            }

            if (($g == 0) && ($h == 0)) {
                $lon = $this->lon_0;
            } else {
                $lon = $this->adjust_lon(atan2($g, $h) + $this->lon_0);
            }
        } else {
            // ellipsoidal form

            $x = $x - $this->x_0;
            $y = $y - $this->y_0;

            $con = ($this->ml0 + $y / $this->k_0) / $this->a;
            $phi = $con;

            for ($i = 0; true; $i++) {
                $delta_phi = ((
                    $con
                    + $this->e1 * sin(2.0 * $phi)
                    - $this->e2 * sin(4.0 * $phi)
                    + $this->e3 * sin(6.0 * $phi)
                ) / $this->e0) - $phi;

                $phi += $delta_phi;

                if (abs($delta_phi) <= static::EPSLN) {
                    break;
                }

                if ($i >= $max_iter) {
                    throw new \Exception('tmerc:inverse: Latitude failed to converge');
                }
            }

            if (abs($phi) < M_PI_2) {
                $sin_phi = sin($phi);
                $cos_phi = cos($phi);
                $tan_phi = tan($phi);

                $c = $this->ep2 * pow($cos_phi, 2);
                $cs = pow($c, 2);
                $t = pow($tan_phi, 2);
                $ts = pow($t, 2);
                $con = 1.0 - $this->es * pow($sin_phi, 2);
                $n = $this->a / sqrt($con);
                $r = $n * (1.0 - $this->es) / $con;
                $d = $x / ($n * $this->k_0);
                $ds = pow($d, 2);
                $lat = $phi - ($n * $tan_phi * $ds / $r) * (0.5 - $ds / 24.0 * (5.0 + 3.0 * $t + 10.0 * $c - 4.0 * $cs - 9.0 * $this->ep2 - $ds / 30.0 * (61.0 + 90.0 * $t + 298.0 * $c + 45.0 * $ts - 252.0 * $this->ep2 - 3.0 * $cs)));
                $lon = $this->adjust_lon($this->lon_0 + ($d * (1.0 - $ds / 6.0 * (1.0 + 2.0 * $t + $c - $ds / 20.0 * (5.0 - 2.0 * $c + 28.0 * $t - 3.0 * $cs + 8.0 * $this->ep2 + 24.0 * $ts))) / $cos_phi));
            } else {
                $lat = M_PI_2 * $this->sign($y);
                $lon = $this->lon_0;
            }
        }


        // TODO: the datum needs to be captured in here.
        return new Geodetic(['lat' => rad2deg($lat), 'long' => rad2deg($lon)]);
    }
}
