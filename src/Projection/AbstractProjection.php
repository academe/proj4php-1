<?php

namespace proj4php\Projection;

use proj4php\IsCloneable;
use proj4php\Ellipsoid;
use proj4php\Datum;

abstract class AbstractProjection
{
    use IsCloneable;

    const EPSLN = 1.0e-10;

    /**
     * Datum (with ellipsoid) parameters are held here.
     * A projection may have its own datum, or the datum can be taken
     * from the points supplied.
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

        return log(tan((M_PI_2 + $phi) / 2.0 ) )
            + $eccent * log( (1.0 - $con) / (1.0 + $con)) / 2.0;
    }

    /**
     * Function to compute the latitude angle, phi2, for the inverse of the
     * Lambert Conformal Conic and Polar Stereographic projections.
     * 
     * rise up an assertion if there is no convergence.
     * 
     * @param float $eccent
     * @param float $ts
     * @return float|int
     */
    public function phi2z($eccent, $ts)
    {
        $eccnth = 0.5 * $eccent;
        $phi = M_PI_2 - 2 * atan($ts);

        for ($i = 0; $i <= 15; $i++) {
            $con = $eccent * sin($phi);
            $dphi = M_PI_2
                - 2 * atan($ts * (pow(((1.0 - $con) / (1.0 + $con)), $eccnth )))
                - $phi;
            $phi += $dphi;

            // Is this self::EPSLN? I think it is.
            if (abs($dphi) <= 0.0000000001) {
                return $phi;
            }
        }

        assert("false; /* phi2z has NoConvergence */");

        // What does this return value mean? Maybe return null or raise an exeption.
        return (-9999);
    }

    /**
     * following functions from gctpc cproj.c for transverse mercator projections
     * 
     * @param float $x
     * @return float
     */
    public function e0fn($x)
    {
        return 1.0 - 0.25 * $x * (1.0 + $x / 16.0 * (3.0 + 1.25 * $x));
    }

    /**
     * @param float $x
     * @return float
     */
    public function e1fn($x)
    {
        return (0.375 * $x * (1.0 + 0.25 * $x * (1.0 + 0.46875 * $x)));
    }

    /**
     * @param float $x
     * @return float
     */
    public function e2fn($x)
    {
        return (0.05859375 * $x * $x * (1.0 + 0.75 * $x));
    }

    /**
     * @param float $x
     * @return float
     */
    public function e3fn($x)
    {
        return ($x * $x * $x * (35.0 / 3072.0));
    }

    /**
     * @param float $e0
     * @param float $e1
     * @param float $e2
     * @param float $e3
     * @param float $phi
     * @return float
     */
    public function mlfn($e0, $e1, $e2, $e3, $phi)
    {
        return (
            $e0 * $phi
            - $e1 * sin(2.0 * $phi)
            + $e2 * sin(4.0 * $phi)
            - $e3 * sin(6.0 * $phi)
        );
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

    /**
     *
     */
    public function getA()
    {
        return $this->datum->getA();
    }

    /**
     *
     */
    public function getB()
    {
        return $this->datum->getB();
    }

    /**
     *
     */
    public function getRf()
    {
        return $this->datum->getRf();
    }

    /**
     *
     */
    public function getE()
    {
        return $this->datum->getE();
    }

    /**
     *
     */
    public function getEs()
    {
        return $this->datum->getEs();
    }

    /**
     *
     */
    public function getEp2()
    {
        return $this->datum->getEp2();
    }

    /**
     *
     */
    public function isSphere()
    {
        return $this->datum->isSphere();
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
                    $this->datum = $value;
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
            if ($this->datum === null) {
                $this->datum = new Datum($ellipsoid, $towgs84);

                // We have used these two up now.
                $ellipsoid = null;
                $towgs84 = null;
            }

            // Additional parameters have been provided to suplement the datum.
            if ($towgs84 !== null) {
                $this->datum = $this->datum->withShiftParameters($towgs84);
            }

            // An alternate ellipsoid has also been supplied with the datum, so put
            // this ellipsoid into the datum.
            if ($ellipsoid !== null) {
                $this->datum = $this->datum->withEllipsoid($ellipsoid);
            }
        }
    }

    /**
     * FIXME: The datum will be returned as an object.
     */
    public function toArray()
    {
        return get_object_vars($this);
    }
}
