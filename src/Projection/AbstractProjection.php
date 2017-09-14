<?php

namespace proj4php\Projection;

use proj4php\Point\Cartesian;
use proj4php\Point\Geodetic;
use proj4php\IsCloneable;
use proj4php\Ellipsoid;
use proj4php\Datum;

abstract class AbstractProjection
{
    use IsCloneable;

    const EPSLN = 1.0e-10;

    /**
     * Datum (with ellipsoid) parameters are held here.
     * A projection will have its own datum, or the datum can be taken
     * from the points supplied when transforming projections.
     */

    protected $datum;

    /**
     * Function to compute the constant small t for use in the forward
     * computations in the Lambert Conformal Conic and the Polar
     * Stereographic projections.
     *
     * TODO: If the parsed parameters are put into an array rather than
     * properties, then it will be easier to list them, serialise them etc.
     * Or maybe we just treat "null" as a non-set property?
     *
     * @param float $eccent
     * @param float $phi
     * @param float $sinphi
     * @return float
     */
    public function tsfnz($eccent, $phi, $sinphi)
    {
        $con = $eccent * $sinphi;
        $com = 0.5 * $eccent;
        $con = pow(((1.0 - $con) / (1.0 + $con)), $com);

        return (tan(0.5 * (M_PI_2 - $phi) ) / $con);
    }

    /**
     * Adjust longitude to -180 to 180; input in radians
     *
     * @param float $lon Angle in radians
     * @return float
     */
    public function adjust_lon($lon)
    {
        return (abs($lon) < M_PI)
            ? $lon
            : ($lon - (static::sign($lon) * (M_PI + M_PI)));
    }

    /**
     * Return the sign of an argument.
     * This differs from PHP's core sign() function in that zero returns as postive.
     *
     * @param int|float $x The numeric valid to test.
     * @return int -1 for negative; +1 for positive or zero
     */
    public function sign($x)
    {
        return ($x < 0.0 ? -1 : 1);
    }

    /**
     * Function to compute the constant small m which is the radius of
     * a parallel of latitude, phi, divided by the semimajor axis.
     *
     * @param float $eccent
     * @param float $sinphi
     * @param float $cosphi
     * @return float
     */
    public function msfnz($eccent, $sinphi, $cosphi)
    {
        $con = $eccent * $sinphi;
        return $cosphi / (sqrt(1.0 - $con * $con));
    }

    /**
     * Latitude Isometrique - close to tsfnz ...
     *
     * @param float $eccent
     * @param float $phi
     * @param float $sinphi
     * @return float
     */
    public function latiso($eccent, $phi, $sinphi)
    {
        if (abs($phi) > M_PI_2) {
            return +NaN;
        }

        if ($phi == M_PI_2) {
            return INF;
        }

        if ($phi == -1.0 * M_PI_2) {
            return -1.0 * INF;
        }

        $con = $eccent * $sinphi;

        return log(tan((M_PI_2 + $phi) / 2.0))
            + $eccent * log((1.0 - $con) / (1.0 + $con)) / 2.0;
    }

    /**
     * Function to eliminate roundoff errors in asin
     *
     * @param float $x
     * @return float
     */
    public function asinz($x)
    {
        return asin(
            abs($x) > 1.0 ? ($x > 1.0 ? 1.0 : -1.0) : $x 
        );
    }

    /**
     * Set a class property, of the proprty exists.
     * Discard the value if the property does not exist.
     */
    protected function setProperty($name, $value)
    {
        // If there is a setter, then use that.
        $setterName = 'set' . ucfirst(str_replace('_', '', strtolower($name)));

        if (method_exists($this, $setterName)) {
            $this->$setterName($value);
        } elseif (property_exists($this, $name)) {
            $this->$name = $value;
        }
    }

    /**
     * Get a property.
     */
    protected function getProperty($name, $default = null)
    {
        $getterName = 'get' . ucfirst(str_replace('_', '', strtolower($name)));

        if (method_exists($this, $getterName)) {
            $this->gsetterName($value, $default);
        } elseif (property_exists($this, $name)) {
            return $this->$name;
        } else {
            return $default;
        }
    }

    public function getDatum()
    {
        return $this->datum;
    }

    protected function setDatum($value)
    {
        $this->datum = $value;
        return $this;
    }

    public function withDatum(Datum $value)
    {
        return $this->getClone()->setDatum($datum);
    }

    /**
     * Take the supplied options and store them in relevant properties.
     * TODO: support datum and ellipsoid. Also default both of these when asked for it
     * and not supplied. a, b, rf, es, ep2 will all come from the ellipsoid if requested
     * and not overridden. Set the datum, and then use these ellipsoid parameters, and
     * towgs84 if supplied, to configure the datum and ellipsoid. One possible downside
     * will be too many calculations, e.g. setting a and b will derive rf, then setting
     * rf must be done in the context of A or B again, deriving the other.
     */
    protected function parseOptions(array $options = [])
    {
        $ellipsoid = null;
        $towgs84 = null;

        foreach ($options as $name => $value) {
            $lname = strtolower($name);

            switch ($lname) {
                case 'title':
                    $this->setProperty($lname, $value);
                    break;

                // Keep the ellipsoid parameters if we have any.
                case 'a':
                case 'b':
                case 'rf':
                    $$lname = floatval($value);
                    break;

                case 'datum':
                    // TODO: This will be a Datum object, not just a name.
                    // Or maybe we can set up a datum from data alone, an array of parameters?
                    $this->setDatum($value);
                    break;

                case 'ellipsoid':
                case 'ellips':
                    // TODO: This will be an Ellipsoid object, not just a name.
                    // It needs to go into the datum, with a datum created if it does not
                    // already exist.
                    // Or create a new ellipsoid from data alone.
                    $ellipsoid = $value;
                    break;

                case 'towgs84':
                    // TODO: These parameters to go into the datum.
                    // It may be a CSV string or an array.
                    $towgs84 = $value;
                    break;

                case 'lat0':
                case 'lat_0':
                    $this->setProperty('lat_0', deg2rad($value));
                    break;
                case 'lat1':
                case 'lat_1':
                    $this->setProperty('lat_1', deg2rad($value));
                    break;
                case 'lat2':
                case 'lat_2':
                    $this->setProperty('lat_2', deg2rad($value));
                    break;
                case 'lat_ts':
                case 'alpha':
                    $this->setProperty($lname, deg2rad($value));
                    break;
                case 'lon0':
                case 'lon_0':
                    $this->setProperty('lon_0', deg2rad($value));
                    break;
                case 'lonc':
                case 'lon_c':
                    $this->setProperty('longc', deg2rad($value));
                    break;
                case 'x0':
                case 'x_0':
                    $this->setProperty('x_0', floatval($value));
                    break;
                case 'x0':
                case 'y_0':
                    $this->setProperty('y_0', floatval($value));
                    break;
                case 'k':
                case 'k0':
                case 'k_0':
                    $this->setProperty('k_0', floatval($value));
                    break;
                case 'r_a':
                    $this->setProperty('R_A', true);
                    break;
                case 'sphere':
                    $this->setProperty($lname, (bool)$value);
                    break;
                case 'zone':
                    $this->setProperty($lname, intval($value, 10));
                    break;
                case 'south':
                case 'utmsouth':
                    $this->setProperty('utmSouth', true);
                    break;
                case 'north':
                case 'utmnorth':
                    $this->setProperty('utmSouth', false);
                    break;
            }

            // If we have some ellipsoid parameters, then create an ellipsoid from them.
            // These parameters completely override any ellipsoid that has been supplied.
            if (isset($a) && isset($b)) {
                $ellipsoid = Ellipsoid::fromAB($a, $b);
            } elseif (isset($a) && isset($rf)) {
                $ellipsoid = Ellipsoid::fromARf($a, $rf);
            }

            // If we have an ellipsoid but no datum, then create a datum.
            if ($this->getDatum() === null) {
                $this->setDatum(new Datum($ellipsoid, $towgs84));

                // We have used these two up now.
                $ellipsoid = null;
                $towgs84 = null;
            }

            // Additional parameters have been provided to suplement the datum.
            if ($towgs84 !== null) {
                $this->setDatum($this->getDatum()->withShiftParameters($towgs84));
            }

            // An alternate ellipsoid has also been supplied with the datum, so put
            // this ellipsoid into the datum.
            if ($ellipsoid !== null) {
                $this->setDatum($this->getDatum()->withEllipsoid($ellipsoid));
            }
        }
    }

    /**
     * The forward transform, from Geodetic Lat/Long to a Cartesian projection.
     */
    public function geodeticToCartesian(Geodetic $geodetic)
    {
        // Shift the datum of the geodetic point if it's not the same as the projecion.
        // The projection datum is here we cant the final projected point to end up.

        if ($geodetic->getDatum() && ! $geodetic->getDatum()->isSame($this->datum)) {
            $geodetic = $geodetic->shiftDatum($this->datum);
        }

        // Get the lat/long as radians.
        $lat = $geodetic->getLat(Geodetic::RADIANS);
        $long = $geodetic->getLong(Geodetic::RADIANS);

        // TODO: should we pass in the datum here? The point has been shifted to the
        // projection datum (if needed) so $this->getDatum() will provide the datum.
        $coords = $this->forward($lat, $long);

        return new Cartesian($coords, $this);
    }

    /**
     * The inserse transform, from a Cartesian projection to Geodetic Lat/Long.
     */
    public function cartesianToGeodetic(Cartesian $cartesian)
    {
        $x = $cartesian->getX();
        $y = $cartesian->getY();
        $context = $cartesian->getContext();

        // Get the datum, allowing the point datum to override the projection datum.

        $datum = $cartesian->getDatum();

        if (! $datum) {
            $datum = $this->getDatum();
        }

        list($lat, $long) = array_values($this->inverse($x, $y, $datum, $context));

        $geodetic = new Geodetic(
            ['lat' => rad2deg($lat), 'long' => rad2deg($long)],
            $datum
        );

        // Shift the datum of the point if it's not the same as the projection.

        if (! $datum->isSame($this->datum)) {
            $geodetic = $geodetic->shiftDatum($this->datum);
        }

        return $geodetic;
    }

    /**
     * FIXME: The datum will be returned as an object.
     */
    public function toArray()
    {
        return get_object_vars($this);
    }
}
