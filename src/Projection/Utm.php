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
 * Initialize Transverse Mercator projection
 *
 * CHECKME: does UTM allow a negative y value? Does it just get a hemisphere indicator
 * when it is turned into a map reference?
 *
 * This is one of the few conversion tools that correctly distriguishes between "standard" UTM
 * and NATO UTM: https://www.uwgb.edu/dutchs/UsefulData/ConvertUTMNoOZ.HTM
 * It ALSO makes exactly the same round-trip error converting from geodetic in the southern
 * hemispherte to utm and back again, as this script does. So something is screwy there perhaps
 * in the source libraries.
 */

use proj4php\Datum;

class Utm extends Tmerc
{
    // Indicates that coordinates are in the Southern hemisphere.
    protected $utmSouth = false;

    protected $zone;

    protected $k_0 = 0.9996;
    protected $lat_0 = 0.0;

    // False Easting to make all Eastings positive within a zone, with
    // a little extra for overlappig another zone slightly to the West.
    protected $x_0 = 500000.0;

    // Derived.
    protected $e0, $e1, $e2, $e3;
    protected $ml0;

    // Derived from the zone, or supplied directly.
    protected $lon_0;

    // False Northing derived from the hemisphere.
    protected $y_0;

    // Maximun number of iterations for the inverse mapping.
    protected $max_iterations = 6;

    protected $southern_false_northing = 10000000.0;

    public function __construct(array $options = [])
    {
        $this->parseOptions($options);

        // This, like lon_0, can be derived from latitude when converting forward.
        // This should also only apply to inverse transforms, but it affects forward too.
        // It could also vary per coordinate when transforming forward.
        $this->y_0 = $this->utmSouth ? 10000000.0 : 0.0;

        $datum = $this->getDatum();

        // Only needed for non-spherical ellisoids.
        if (! $datum->isSphere()) {
            $es = $datum->getEs();
            $lat_0 = $this->lat_0;
            $a = $datum->getA();

            list($this->e0, $this->e1, $this->e2, $this->e3, $this->ml0) = $this->emlfn($es, $lat_0, $a);
        }
    }

    /**
     * Transverse Mercator Forward  - long/lat to x/y
     * long/lat in radians
     */
    public function forward($lat, $long)
    {
        $zone = $this->zone;
        $lon_0 = $this->lon_0;

        // Ellipsoid parameters and derivations.
        $datum = $this->getDatum();

        $a = $datum->getA();
        $ep2 = $datum->getEp2();
        $es = $datum->getEs();
        $sphere = $datum->isSphere();

        // If no zone supplied, then derive it from the longitude and latitude.
        if ($zone === null) {
            $zone = $this->longToZone($long, $lat);
        }

        if ($lon_0 === null) {
            $lon_0 = $this->zoneToLon0($zone);
        }

        // Determine the hemisphere.
        $utmSouth = ($lat < 0);
        $y_0 = $utmSouth ? $this->southern_false_northing : 0.0;

        // Delta longitude
        $delta_lon = $this->adjust_lon($long - $lon_0);

        $sin_phi = sin($lat);
        $cos_phi = cos($lat);

        if ($sphere) {
            // Spherical form
            // CHECKME: does not seem to take the hemisphere into account.

            $b = $cos_phi * sin($delta_lon);

            if ((abs(abs($b) - 1.0)) < static::EPSLN) {
                throw new \Exception('tmerc:forward: Point projects into infinity');
            } else {
                $x = 0.5 * $a * $this->k_0 * log((1.0 + $b) / (1.0 - $b));
                $con = acos($cos_phi * cos($delta_lon) / sqrt(1.0 - $b * $b));

                if ($lat < 0) {
                    $con = -$con;
                }

                $y = $a * $this->k_0 * ($con - $this->lat_0);
            }
        } else {
            $al = $cos_phi * $delta_lon;
            $als = pow($al, 2);

            $c = $ep2 * pow($cos_phi, 2);

            $tq = tan($lat);
            $t = pow($tq, 2);

            $con = 1.0 - $es * pow($sin_phi, 2);
            $n = $a / sqrt($con);

            $ml = $a * $this->mlfn($this->e0, $this->e1, $this->e2, $this->e3, $lat);

            $x = $this->k_0 * $n * $al * (1.0 + $als / 6.0 * (1.0 - $t + $c + $als / 20.0 * (5.0 - 18.0 * $t + pow($t, 2) + 72.0 * $c - 58.0 * $ep2))) + $this->x_0;

            $y = $this->k_0 * ($ml - $this->ml0 + $n * $tq * ($als * (0.5 + $als / 24.0 * (5.0 - $t + 9.0 * $c + 4.0 * pow($c, 2) + $als / 30.0 * (61.0 - 58.0 * $t + pow($t, 2) + 600.0 * $c - 330.0 * $ep2))))) + $y_0;
        }

        // The Utm point extends the Enu point with zone information.
        // FIXME: the south hemisphere flag.

        return ['x' => $x, 'y' => $y, 'zone' => $zone, 'south' => $utmSouth];
    }

    /**
     * Universal Transverse Mercator Inverse - x/y/zone/hemisohere to long/lat
     */
    public function inverse($x, $y, Datum $datum, array $context = [])
    {
        $lon_0 = $this->lon_0;

        // Ellipsoid parameters.
        $datum = $this->getDatum();
        $a = $datum->getA();
        $ep2 = $datum->getEp2();
        $es = $datum->getEs();
        // The ellipsoid can tell us whether it is a sphere.
        $sphere = $datum->isSphere();

        // The zone may be available in the $cartesian point, so use that over
        // whatever the projection is expecting.
        if (isset($context['zone']) && $zone = $context['zone']) {
            $lon_0 = $this->zoneToLon0($zone);
        }

        // Check the hemisphere option of the point, in case it needs to override
        // the hemisphere option set in this projection.
        if (! empty($context['south']) || ! empty($context['utmSouth'])) {
            $y_0 = $this->southern_false_northing;
        } else {
            $y_0 = $this->y_0;
        }

        if ($lon_0 === null) {
            throw new \Exception('Missing lon_0 or zone in inverse transform');
        }

        if ($sphere) {
            // Spherical form.

            $f = exp($x / ($a * $this->k_0));
            $g = 0.5 * ($f - 1 / $f);
            $temp = $this->lat_0 + $y / ($a * $this->k_0);
            $h = cos($temp);
            $con = sqrt((1.0 - $h * $h) / (1.0 + $g * $g));
            $lat = $this->asinz($con);

            if ($temp < 0) {
                $lat = -$lat;
            }

            if (($g == 0) && ($h == 0)) {
                $lon = $lon_0;
            } else {
                $lon = $this->adjust_lon(atan2($g, $h) + $lon_0);
            }
        } else {
            // Ellipsoidal form.

            $x = $x - $this->x_0;
            $y = $y - $y_0;

            // Do we need to recalculate the e* and ml0 values?

            if ($datum->isSame($this->getDatum())) {
                // Same datum, so use the projection's pre-calculated values.
                list($e0, $e1, $e2, $e3, $ml0) = [
                    $this->e0,
                    $this->e1,
                    $this->e2,
                    $this->e3,
                    $this->ml0,
                ];
            } else {
                // Recacalculate new valeus for the point's ellipsoid.
                $es = $datum->getEs();
                $a = $datum->getA();
                $lat_0 = $this->lat_0;

                list($e0, $e1, $e2, $e3, $ml0) = $this->emlfn($es, $lat_0, $a);
            }

            $con = ($ml0 + $y / $this->k_0) / $a;
            $phi = $con;

            // FIXME: the e0 to e3 values are pre-calculated from the projection datum,
            // but the inverse transform is done under the point datum, which may (or may not)
            // be different to the projection datum.
            for ($i = 0; true; $i++) {
                $delta_phi = ((
                    $con
                    + $e1 * sin(2.0 * $phi)
                    - $e2 * sin(4.0 * $phi)
                    + $e3 * sin(6.0 * $phi)
                ) / $e0) - $phi;

                $phi += $delta_phi;

                if (abs($delta_phi) <= static::EPSLN) {
                    break;
                }

                if ($i >= $this->max_iterations) {
                    throw new \Exception(sprintf(
                        'tmerc:inverse: Latitude failed to converge; exceeded %d itterations',
                        $this->max_iterations
                    ));
                }
            }

            if (abs($phi) < M_PI_2) {
                $sin_phi = sin($phi);
                $cos_phi = cos($phi);
                $tan_phi = tan($phi);

                $c = $ep2 * pow($cos_phi, 2);
                $cs = pow($c, 2);

                $t = pow($tan_phi, 2);
                $ts = pow($t, 2);

                $con = 1.0 - $es * pow($sin_phi, 2);

                $n = $a / sqrt($con);
                $r = $n * (1.0 - $es) / $con;

                $d = $x / ($n * $this->k_0);
                $ds = pow($d, 2);

                $lat = $phi - ($n * $tan_phi * $ds / $r) * (0.5 - $ds / 24.0 * (5.0 + 3.0 * $t + 10.0 * $c - 4.0 * $cs - 9.0 * $ep2 - $ds / 30.0 * (61.0 + 90.0 * $t + 298.0 * $c + 45.0 * $ts - 252.0 * $ep2 - 3.0 * $cs)));
                $lon = $this->adjust_lon($lon_0 + ($d * (1.0 - $ds / 6.0 * (1.0 + 2.0 * $t + $c - $ds / 20.0 * (5.0 - 2.0 * $c + 28.0 * $t - 3.0 * $cs + 8.0 * $ep2 + 24.0 * $ts))) / $cos_phi));
            } else {
                $lat = M_PI_2 * $this->sign($y);
                $lon = $lon_0;
            }
        }

        // TODO: the datum needs to be captured in here.
        return ['lat' => $lat, 'long' => $lon];
    }

    /**
     * Calculate lon_0 from the zone number.
     *
     * @param float $zone The zone number, 1 to 60.
     */
    protected function zoneToLon0($zone)
    {
        return deg2rad((6 * abs($zone)) - 183);
    }

    /**
     * Get the zone for a given longitude and latitude.
     * The latitude can be supplied for exceptions listed here:
     * https://stackoverflow.com/questions/9186496/determining-utm-zone-to-convert-from-longitude-latitude
     *
     * @param float $long The longitude in radians
     * @param float|null $lat The optional latitude in radians
     * @return int The zone number 1 to 60
     */
    protected function longToZone($long, $lat = null)
    {
        $zone = (floor((rad2deg($long) + 180) / 6) % 60) + 1;

        if ($lat !== null) {
            // TODO: exceptions
        }

        return $zone;
    }

    public function withZone($zone)
    {
        return $this->getClone()->setZone($zone);
    }

    protected function setZone($zone)
    {
        $this->zone = $zone;
        $this->lon_0 = $this->zoneToLon0($this->zone);
        return $this;
    }
}
