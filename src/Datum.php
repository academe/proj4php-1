<?php

namespace proj4php;

/**
 * Author : Julien Moquet
 * 
 * Inspired by Proj4js from Mike Adair madairATdmsolutions.ca
 * and Richard Greenwood rich@greenwoodma$p->com
 * License: LGPL as per: http://www.gnu.org/copyleft/lesser.html
 *
 * Geodetic Datum
 */

use Exception;

class Datum
{
    /**
     * Number of shift parameters we have.
     * WGS84 will return shift parameters of "none", as there is
     * no transform to do..
     */
    const SHIFT_PARAM_COUNT_NONE    = 0;
    const SHIFT_PARAM_COUNT_3       = 3;
    const SHIFT_PARAM_COUNT_7       = 7;

    /**
     * Defining various units.
     */
    const ARCSECONDS    = 'arcseconds';
    const RADIANS       = 'radians';

    const PPM           = 'ppm';
    const MULTIPLIER    = 'multiplier';

    const FORWARD = 1;
    const INVERSE = -1;

    /**
     * Short code used to find the datum.
     */
    protected $code;

    /**
     * Long name for the datum.
     */
    protected $name;

    /**
     * The datum ellipsoid.
     */
    protected $ellipsoid;

    /**
     * The datum centre-shifting parameters (to WGS84).
     * Shifts [Dx, Dym Dz] in metres.
     */
    protected $displacementParameters = [0, 0, 0];

    /**
     * The datum rotation parameters (to WGS84)
     * Shifts [Rx, Ry, Rz] in seconds of arc.
     */
    protected $rotationalParameters = [0, 0, 0];

    /**
     * The datum scale parameter.
     * Scale change in PPM.
     * M_BF
     */
    protected $scalerParameter = 1.0;

    /**
     * @param Ellipsoid $ellipsoid
     * @param string|array $shiftParams the 3 or 7 shift parameters (to WGS84).
     */
    public function __construct(Ellipsoid $ellipsoid = null, $shiftParams = null, $code = null, $name = null)
    {
        $this->setEllipsoid($ellipsoid);
        $this->setShiftParams($shiftParams);
    }

    public function getDisplacementParameters($direction = self::FORWARD)
    {
        $dir_factor = ($direction == self::FORWARD ? 1 : -1);

        return array_map(
            function ($m) use ($dir_factor) {return $dir_factor * $m;},
            $this->displacementParameters
        );
    }

    /**
     *
     */
    public function getRotationalParameters($unit = self::ARCSECONDS, $direction = self::FORWARD)
    {
        $dir_factor = ($direction == self::FORWARD ? 1 : -1);

        if ($unit == self::RADIANS) {
            // Convert units; seconds of arc to radians.
            return array_map(
                function ($m) use ($dir_factor) {return $dir_factor * deg2rad($m / 60);},
                $this->rotationalParameters
            );
        }

        if ($unit == self::ARCSECONDS) {
            return array_map(
                function ($m) use ($dir_factor) {return $dir_factor * $m;},
                $this->rotationalParameters
            );
        }

        throw new \Exception(sprintf('Unsupported units "%s"', $unit));
    }

    /**
     *
     */
    public function getScalarParameter($unit = self::PPM, $direction = self::FORWARD)
    {
        $dir_factor = ($direction == self::FORWARD ? 1 : -1);

        if ($unit == self::MULTIPLIER) {
            return 1.0 + (($dir_factor * $this->scalerParameter)  / 1e6);
        }

        if ($unit == self::PPM) {
            return $this->scalerParameter;
        }

        throw new \Exception(sprintf('Unsupported units "%s"', $unit));
    }

    protected function setShiftParams($shiftParams)
    {
        // If null, then default it to "no shift", i.e. WGS84.

        if (! isset($shiftParams)) {
            $shiftParams = [0.0, 0.0, 0.0];
        }

        // If a CSV or space-separated string, then explode it to an array.

        if (is_string($shiftParams)) {
            if (strpos($shiftParams, ',') !== false) {
                // Comma-separeted.
                // CHECKME: are European style decimals ever used, e.g. "12,3 4,56"
                $shiftParams = array_map('trim', explode(',', $shiftParams));
            } else {
                // Split by whitespace.
                $shiftParams = preg_split('/[\s]+/', trim($shiftParams));
            }
        }

        $type = gettype($shiftParams);

        if ($type != 'array') {
            // FIXME: more appropriate exception class.
            throw new \Exception(sprintf(
                'Type of shift parameters must be a CSV string or an array; %s given instead',
                $type
            ));
        }

        $count = count($shiftParams);

        if ($count != 3 && $count != 7) {
            // FIXME: more appropriate exception class.
            throw new \Exception(sprintf(
                'Either 3 or 7 shift parameters must be supplied; %d given',
                $count
            ));
        }

        // Make sure all shift parameters are floats.
        $shiftParams = array_map('floatval', $shiftParams);

        // Save them.
        list(
            $this->displacementParameters[0],
            $this->displacementParameters[1],
            $this->displacementParameters[2]
        ) = $shiftParams;

        if ($count == 7) {
            list(
                // Skip first three parameters we already have..
                ,,,
                $this->rotationalParameters[0],
                $this->rotationalParameters[1],
                $this->rotationalParameters[2],
                $this->scalerParameter
            ) = $shiftParams;
        }

        return $this;
    }

    /**
     * Get all shift parameters as a single array.
     * Returns either an array of 3 elements or 7 elements.
     */
    public function getShiftParameters()
    {
        $count = $this->getShiftParameterCount();

        if ($count == static::SHIFT_PARAM_COUNT_3) {
            return $this->displacementParameters;
        }

        if ($count == static::SHIFT_PARAM_COUNT_7) {
            return array_merge(
                $this->displacementParameters,
                $this->rotationalParameters,
                [$this->scalerParameter]
            );
        }

        return [];
    }

    /**
     * Determine whether we have 3, 7 or no shift parameters.
     */
    public function getShiftParameterCount()
    {
        $r = $this->rotationalParameters;

        if ($r[0] != 0 || $r[1] || $r[2] || $this->scalerParameter != 1.0) {
            return static::SHIFT_PARAM_COUNT_7;
        }

        $d = $this->displacementParameters;

        if ($d[0] != 0 || $d[1] != 0 || $d[2] != 0) {
            return static::SHIFT_PARAM_COUNT_3;
        }

        return static::SHIFT_PARAM_COUNT_NONE;
    }

    /**
     * Checks if this datum is the same as the supplied caparison datum.
     */
    public function isSame(Datum $datum)
    {
        // Quick check - do they have a different number of Bursa-Wolf parameters?
        if ($this->getShiftParameterCount() !== $datum->getShiftParameterCount()) {
            return false;
        }

        // If the parameters (3 or 7) are not identical then we will take the
        // datums to be different. We may want to check each parameter within
        // a tolerance, but we'll see how this goes.
        // There are some notes that WGS84 and GRS80 should be considered as the
        // same datum, even though their parameters are slightly different.
        if ($this->getShiftParameters() !== $datum->getShiftParameters()) {
            return false;
        }

        // Can't find any differences in the shifting Bursa-Wolf parameters, so assume
        // they are the same. We ignaore the names of the datums.
        return true;
    }

    public function getA()
    {
        return $this->getEllipsoid()->getA();
    }

    protected function setEllipsoid(Ellipsoid $ellipsoid = null)
    {
        // If no ellipsoid is give then provide a default.
        if (! isset($ellipsoid)) {
            // The default will be WGS84.
            $ellipsoid = new Ellipsoid();
        }

        $this->ellipsoid = $ellipsoid;
        return $this;
    }

    public function getEllipsoid()
    {
        return $this->ellipsoid;
    }

    public function getEs()
    {
        return $this->getEllipsoid()->getEs();
    }




// OLD stuff below.

    /**
     * The type of the datum, derived from the parameters supplied.
     * One of:
     * - Common::PJD_WGS84
     * - Common::PJD_NODATUM
     * - Common::PJD_3PARAM
     * - Common::PJD_7PARAM
     *
     * There are also these values, that are not used:
     *
     * - Common::PJD_UNKNOWN
     * - Common::PJD_GRIDSHIFT
     */
    public $datum_type;

    public $datum_params;

    /**
     * Semi-major axis.
     */
    public $a;

    /**
     * Semi-minor axis.
     */
    public $b;

    // Note that both es and ep2 can be derived from a and b, and indeed
    // are when only a and b are available in the Proj.
    // es, ep2 and b can also be derived from a an reverse-flattening (1/f)

    /**
     * First eccentricity squared.
     */
    public $es;

    /**
     * Second eccentricity squared
     */
    public $ep2;

    /**
     * This datum gets all its parameters from the parent projection (Proj)
     * that it will be a child of. This seems backwards. A Datum should be
     * able to stand alone after being set up with simple data, and not need
     * to have to reference public properties of a parent object to look for
     * that data.
     *
     * Initialisation of this object also converts the parent Proj datum params
     * from seconds to radians, *before* copying them across, which seems
     * very wrong (nasty side-effects just by instantiating a Datum).
     * At least it does not refeer to the parent Proj after the initialisation
     * in the constructor, though that does not mean something else isn't going
     * to poke about in here. Making this a value object should help with that.
     *
     * Properties that are read and used in initialisation:
     *
     * - datumCode string
     * - datum_params array of float, either 3 or 7 parameters
     * - a float
     * - b float
     * - es float
     * - ep2 float
     *
     * @param Proj $proj
     */
    public function XXX__construct(Proj $proj)
    {
        // default setting
        $this->datum_type = Common::PJD_WGS84;

        if (isset($proj->datumCode)) {
            $this->datum_code = $proj->datumCode;
        }

        if (isset($proj->datumCode) && $proj->datumCode == 'none') {
            $this->datum_type = Common::PJD_NODATUM;
        }

        if (isset($proj->datum_params)) {
            for ($i = 0; $i < sizeof($proj->datum_params); $i++) {
                // So instantiating a Datum object writes properties back to the
                // Proj class. That's a nasty side-effect! Every new Datum you create
                // will overwrite those properties.
                $proj->datum_params[$i] = floatval($proj->datum_params[$i]);
            }

            if ($proj->datum_params[0] != 0 || $proj->datum_params[1] != 0 || $proj->datum_params[2] != 0) {
                $this->datum_type = Common::PJD_3PARAM;
            }

            if (sizeof($proj->datum_params) > 3) {
                if ($proj->datum_params[3] != 0 || $proj->datum_params[4] != 0 ||
                    $proj->datum_params[5] != 0 || $proj->datum_params[6] != 0
                ) {
                    $this->datum_type = Common::PJD_7PARAM;

                    // The Datum messes around with more properties of the Proj directly - code smell.

                    $proj->datum_params[3] *= Common::SEC_TO_RAD;
                    $proj->datum_params[4] *= Common::SEC_TO_RAD;
                    $proj->datum_params[5] *= Common::SEC_TO_RAD;
                    $proj->datum_params[6] = ($proj->datum_params[6] / 1000000.0) + 1.0;
                }
            }

            // After messing with the Proj datum_params, we copy them back here.
            $this->datum_params = $proj->datum_params;
        }

        // datum object also uses these values
        $this->a = $proj->a;
        $this->b = $proj->b;
        $this->es = $proj->es;
        $this->ep2 = $proj->ep2;
    }

    /**
     * Why not call this class "equals()"? Compare functions tend to return more
     * than just a true/false. if ($datum1->equals($datum2)) ...
     *
     * @param Datum $dest
     * @return boolean TRUE if the two datums match, otherwise FALSE.
     * @throws Exception
     */
    public function compare_datums(Datum $dest)
    {
        if ($this->datum_type != $dest->datum_type) {
            // Datums are not equal
            return false;
        } elseif ($this->a != $dest->a || abs($this->es - $dest->es) > 0.000000000050) {
            // The tolerence for es is to ensure that GRS80 and WGS84
            // are considered identical.
            return false;
        } elseif ($this->datum_type == Common::PJD_3PARAM) {
            return (
                $this->datum_params[0] == $dest->datum_params[0]
                && $this->datum_params[1] == $dest->datum_params[1]
                && $this->datum_params[2] == $dest->datum_params[2]
            );
        } elseif ($this->datum_type == Common::PJD_7PARAM) {
            return (
                $this->datum_params[0] == $dest->datum_params[0]
                && $this->datum_params[1] == $dest->datum_params[1]
                && $this->datum_params[2] == $dest->datum_params[2]
                && $this->datum_params[3] == $dest->datum_params[3]
                && $this->datum_params[4] == $dest->datum_params[4]
                && $this->datum_params[5] == $dest->datum_params[5]
                && $this->datum_params[6] == $dest->datum_params[6]
            );
        } elseif ($this->datum_type == Common::PJD_GRIDSHIFT ||
            $dest->datum_type == Common::PJD_GRIDSHIFT) {
            throw new Exception('ERROR: Grid shift transformations are not implemented.');
        }

        // Datums are equal.
        return true;
    }

    public function reportDebug()
    {
        if (isset($this->datum_code)) {
            Proj4php::reportDebug(sprintf("Datum code=%s\n", $this->datum_code));
        }

        Proj4php::reportDebug(sprintf("Datum type:%s\n", $this->datum_type));

        if (isset($this->a)) {
            Proj4php::reportDebug(sprintf("a=%f\n", $this->a));
        }

        if (isset($this->b)) {
            Proj4php::reportDebug(sprintf("b=%f\n", $this->b));
        }

        if (isset($this->es)) {
            Proj4php::reportDebug(sprintf("es=%f\n", $this->es));
        }

        if (isset($this->es2)) {
            Proj4php::reportDebug(sprintf("es2=%s\n", $this->es2));
        }

        if (isset($this->datum_params)) {
            foreach($this->datum_params as $key => $value) {
                Proj4php::reportDebug(sprintf("Param %s=%f\n", $key, $value));
            }
        } else {
            Proj4php::reportDebug("no params\n");
        }
    }

    /**
     * The function Convert_Geodetic_To_Geocentric converts geodetic coordinates
     * (latitude, longitude, and height) to geocentric coordinates (X, Y, Z),
     * according to the current ellipsoid parameters.
     *
     *    Latitude  : Geodetic latitude in radians                     (input)
     *    Longitude : Geodetic longitude in radians                    (input)
     *    Height    : Geodetic height, in meters                       (input)
     *    X         : Calculated Geocentric X coordinate, in meters    (output)
     *    Y         : Calculated Geocentric Y coordinate, in meters    (output)
     *    Z         : Calculated Geocentric Z coordinate, in meters    (output)
     *
     * FIXME: this is misusing x, y and z by using the single Point object to represent
     * both geodetic and geocentric coordinates. Given an object, you have not way to
     * knwow what it contains. Instead, we should have separate classes for the two
     * coordinate systems so we can pass the right type of value objects around.
     * Each coordinate system can then probably convert in from the other system given
     * a Datum, e.g. $geodetic = LatLong::fromGeocentric->($geocentric, $datum)
     * Both coordinate types should be derived from a common Point interface, so they
     * can be used interchangeably, with appropriate conversions happening where
     * needed.
     */
    public function geodetic_to_geocentric($p)
    {
        Proj4php::reportDebug(sprintf("geodetic_to_geocentric(%f,%f)\n", $p->x, $p->y));
        $this->reportDebug();

        $Longitude = $p->x;
        $Latitude = $p->y;

        // Z value not always supplied
        $Height = (isset($p->z) ? $p->z : 0);

        // GEOCENT_NO_ERROR;
        $Error_Code = 0;

        /**
         * Don't blow up if Latitude is just a little out of the value
         * range as it may just be a rounding issue.  Also removed longitude
         * test, it should be wrapped by cos() and sin().  NFW for PROJ.4, Sep/2001.
         */

        if ($Latitude < -Common::HALF_PI && $Latitude > -1.001 * Common::HALF_PI) {
            $Latitude = -Common::HALF_PI;
        } elseif ($Latitude > Common::HALF_PI && $Latitude < 1.001 * Common::HALF_PI) {
            $Latitude = Common::HALF_PI;
        } elseif ($Latitude < -Common::HALF_PI || $Latitude > Common::HALF_PI) {
            // Latitude out of range.
            Proj4php::reportError(sprintf("geocent:lat out of range: %f\n", $Latitude));
            return null;
        }

        if ($Longitude > Common::PI) {
            $Longitude -= (2 * Common::PI);
        }

        // sin(Latitude)
        $Sin_Lat = sin($Latitude);

        // cos(Latitude)
        $Cos_Lat = cos($Latitude);

        // Square of sin(Latitude)
        $Sin2_Lat = $Sin_Lat * $Sin_Lat;

        // Earth radius at location
        $Rn = $this->a / (sqrt(1.0e0 - $this->es * $Sin2_Lat));

        $p->x = ($Rn + $Height) * $Cos_Lat * cos($Longitude);
        $p->y = ($Rn + $Height) * $Cos_Lat * sin($Longitude);
        $p->z = (($Rn * (1 - $this->es)) + $Height) * $Sin_Lat;

        return $Error_Code;
    }

    /**
     * @param Point $p TBC
     * @return Point 
     */
    public function geocentric_to_geodetic($p)
    {
        Proj4php::reportDebug(sprintf(
            "geocentric_to_geodetic(%s,%s)\n",
            $p->x,
            $p->y
        ));

        $this->reportDebug();

        // local defintions and variables
        // end-criterium of loop, accuracy of sin(Latitude)

        $genau = 1.E-12;
        $genau2 = ($genau * $genau);
        $maxiter = 30;
        $X = $p->x;
        $Y = $p->y;

        // Z value not always supplied
        $Z = ($p->z ? $p->z : 0.0);

        /*
        $P;        // distance between semi-minor axis and location 
        $RR;       // distance between center and location
        $CT;       // sin of geocentric latitude 
        $ST;       // cos of geocentric latitude 
        $RX;
        $RK;
        $RN;       // Earth radius at location 
        $CPHI0;    // cos of start or old geodetic latitude in iterations 
        $SPHI0;    // sin of start or old geodetic latitude in iterations 
        $CPHI;     // cos of searched geodetic latitude
        $SPHI;     // sin of searched geodetic latitude 
        $SDPHI;    // end-criterium: addition-theorem of sin(Latitude(iter)-Latitude(iter-1)) 
        $AtPole;     // indicates location is in polar region 
        $iter;        // of continous iteration, max. 30 is always enough (s.a.) 
        $Longitude;
        $Latitude;
        $Height;
        */

        $AtPole = false;
        $P = sqrt($X * $X + $Y * $Y);
        $RR = sqrt($X * $X + $Y * $Y + $Z * $Z);

        // Special cases for latitude and longitude.
        if ($P / $this->a < $genau) {
            // special case, if P=0. (X=0., Y=0.)
            $AtPole = true;
            $Longitude = 0.0;

            // If (X,Y,Z)=(0.,0.,0.) then Height becomes semi-minor axis
            // of ellipsoid (=center of mass), Latitude becomes PI/2
            if ($RR / $this->a < $genau) {
                $Latitude = Common::HALF_PI;
                $Height = -$this->b;
                return;
            }
        } else {
            // ellipsoidal (geodetic) longitude
            // interval: -PI < Longitude <= +PI
            $Longitude = atan2($Y, $X);
        }

        /* --------------------------------------------------------------
         * Following iterative algorithm was developped by
         * "Institut für Erdmessung", University of Hannover, July 1988.
         * Internet: www.ife.uni-hannover.de
         * Iterative computation of CPHI,SPHI and Height.
         * Iteration of CPHI and SPHI to 10**-12 radian res$p->
         * 2*10**-7 arcsec.
         * --------------------------------------------------------------
         */
        $CT = $Z / $RR;
        $ST = $P / $RR;
        $RX = 1.0 / sqrt(1.0 - $this->es * (2.0 - $this->es) * $ST * $ST);
        $CPHI0 = $ST * (1.0 - $this->es) * $RX;
        $SPHI0 = $CT * $RX;
        $iter = 0;

        // loop to find sin(Latitude) res$p-> Latitude
        // until |sin(Latitude(iter)-Latitude(iter-1))| < genau
        do {
            ++$iter;
            $RN = $this->a / sqrt(1.0 - $this->es * $SPHI0 * $SPHI0);

            /*  ellipsoidal (geodetic) height */
            $Height = $P * $CPHI0 + $Z * $SPHI0 - $RN * (1.0 - $this->es * $SPHI0 * $SPHI0);

            $RK = $this->es * $RN / ($RN + $Height);
            $RX = 1.0 / sqrt( 1.0 - $RK * (2.0 - $RK) * $ST * $ST);
            $CPHI = $ST * (1.0 - $RK) * $RX;
            $SPHI = $CT * $RX;
            $SDPHI = $SPHI * $CPHI0 - $CPHI * $SPHI0;
            $CPHI0 = $CPHI;
            $SPHI0 = $SPHI;
        } while ($SDPHI * $SDPHI > $genau2 && $iter < $maxiter);

        // ellipsoidal (geodetic) latitude
        $Latitude = atan($SPHI / abs($CPHI));

        $p->x = $Longitude;
        $p->y = $Latitude;
        $p->z = $Height;

        return $p;
    }

    /** 
     * Convert_Geocentric_To_Geodetic
     * The method used here is derived from 'An Improved Algorithm for
     * Geocentric to Geodetic Coordinate Conversion', by Ralph Toms, Feb 1996
     * 
     * @param object Point $p
     * @return object Point $p
     */
    public function geocentric_to_geodetic_noniter(Point $p)
    {
        /*
        $Longitude;
        $Latitude;
        $Height;

        $W;        // distance from Z axis 
        $W2;       // square of distance from Z axis 
        $T0;       // initial estimate of vertical component 
        $T1;       // corrected estimate of vertical component 
        $S0;       // initial estimate of horizontal component 
        $S1;       // corrected estimate of horizontal component
        $Sin_B0;   // sin(B0), B0 is estimate of Bowring aux variable 
        $Sin3_B0;  // cube of sin(B0) 
        $Cos_B0;   // cos(B0)
        $Sin_p1;   // sin(phi1), phi1 is estimated latitude 
        $Cos_p1;   // cos(phi1) 
        $Rn;       // Earth radius at location 
        $Sum;      // numerator of cos(phi1) 
        $AtPole;  // indicates location is in polar region 
        */

        // Cast from string to float.
        // Since we are accepting the Point class only, then we can already
        // guarantee we have floats. A simple list($x, $y $Z) = $p->toArray() will
        // give us our values.

        $X = floatval($p->x);
        $Y = floatval($p->y);
        $Z = floatval($p->z ? $p->z : 0);

        $AtPole = false;

        if ($X <> 0.0) {
            $Longitude = atan2($Y, $X);
        } else {
            if ($Y > 0) {
                $Longitude = Common::HALF_PI;
            } elseif (Y < 0) {
                $Longitude = -Common::HALF_PI;
            } else {
                $AtPole = true;
                $Longitude = 0.0;

                if ($Z > 0.0) { /* north pole */
                    $Latitude = Common::HALF_PI;
                } elseif (Z < 0.0) { /* south pole */
                    $Latitude = -Common::HALF_PI;
                } else { /* center of earth */
                    $Latitude = Common::HALF_PI;
                    $Height = -$this->b;
                    return;
                }
            }
        }

        $W2 = $X * $X + $Y * $Y;
        $W = sqrt($W2);
        $T0 = $Z * Common::AD_C;
        $S0 = sqrt($T0 * $T0 + $W2);
        $Sin_B0 = $T0 / $S0;
        $Cos_B0 = $W / $S0;
        $Sin3_B0 = $Sin_B0 * $Sin_B0 * $Sin_B0;
        $T1 = $Z + $this->b * $this->ep2 * $Sin3_B0;
        $Sum = $W - $this->a * $this->es * $Cos_B0 * $Cos_B0 * $Cos_B0;
        $S1 = sqrt($T1 * $T1 + $Sum * $Sum);
        $Sin_p1 = $T1 / $S1;
        $Cos_p1 = $Sum / $S1;
        $Rn = $this->a / sqrt( 1.0 - $this->es * $Sin_p1 * $Sin_p1);

        if ($Cos_p1 >= Common::COS_67P5) {
            $Height = $W / $Cos_p1 - $Rn;
        } elseif ($Cos_p1 <= -Common::COS_67P5) {
            $Height = $W / -$Cos_p1 - $Rn;
        } else {
            $Height = $Z / $Sin_p1 + $Rn * ($this->es - 1.0);
        }

        if ($AtPole == false) {
            $Latitude = atan($Sin_p1 / $Cos_p1);
        }

        $p->x = $Longitude;
        $p->y = $Latitude;
        $p->z = $Height;

        return $p;
    }

    /**
     * Datum shift.
     *
     * p = point to transform in geocentric coordinates (x,y,z)
     * Note: this will change the point by reference.
     */
    public function geocentric_to_wgs84(Point $p)
    {
        Proj4php::reportDebug(sprintf(
            "geocentric_to_wgs84(%s,%s)\n",
            $p->x,
            $p->y
        ));

        if ($this->datum_type == Common::PJD_3PARAM) {
            Proj4php::reportDebug(sprintf("+x=%f\n", $this->datum_params[0]));
            Proj4php::reportDebug(sprintf("+y=%f\n", $this->datum_params[1]));
            Proj4php::reportDebug(sprintf("+z=%f\n", $this->datum_params[2]));

            $p->x += $this->datum_params[0];
            $p->y += $this->datum_params[1];
            $p->z += $this->datum_params[2];
        } elseif ($this->datum_type == Common::PJD_7PARAM) {
            Proj4php::reportDebug(sprintf("Dx=%f\n", $this->datum_params[0]));
            Proj4php::reportDebug(sprintf("Dy=%f\n", $this->datum_params[1]));
            Proj4php::reportDebug(sprintf("Dz=%f\n", $this->datum_params[2]));
            Proj4php::reportDebug(sprintf("Rx=%f\n", $this->datum_params[3]));
            Proj4php::reportDebug(sprintf("Ry=%f\n", $this->datum_params[4]));
            Proj4php::reportDebug(sprintf("Rz=%f\n", $this->datum_params[5]));
            Proj4php::reportDebug(sprintf("M=%f\n", $this->datum_params[6])); 

            $Dx_BF = $this->datum_params[0];
            $Dy_BF = $this->datum_params[1];
            $Dz_BF = $this->datum_params[2];
            $Rx_BF = $this->datum_params[3];
            $Ry_BF = $this->datum_params[4];
            $Rz_BF = $this->datum_params[5];
            $M_BF = $this->datum_params[6];

            $p->x = $M_BF * ($p->x - $Rz_BF * $p->y + $Ry_BF * $p->z) + $Dx_BF;
            $p->y = $M_BF * ($Rz_BF * $p->x + $p->y - $Rx_BF * $p->z) + $Dy_BF;
            $p->z = $M_BF * (-$Ry_BF * $p->x + $Rx_BF * $p->y + $p->z) + $Dz_BF;
        }
    }

    /**
     * Datum shift.
     *
     * coordinate system definition,
     * point to transform in geocentric coordinates (x,y,z)
     * Note: this will change the point by reference.
     */
    public function geocentric_from_wgs84(Point $p)
    {
        Proj4php::reportDebug('geocentric_from_wgs84('.$p->x.','.$p->y.")\n");

        if ($this->datum_type == Common::PJD_3PARAM) {
            Proj4php::reportDebug(sprintf("+x=%f\n", $this->datum_params[0]));
            Proj4php::reportDebug(sprintf("+y=%f\n", $this->datum_params[1]));
            Proj4php::reportDebug(sprintf("+z=%f\n", $this->datum_params[2]));

            $p->x -= $this->datum_params[0];
            $p->y -= $this->datum_params[1];
            $p->z -= $this->datum_params[2];
        } elseif ($this->datum_type == Common::PJD_7PARAM) {
            Proj4php::reportDebug(sprintf("Dx=%f\n", $this->datum_params[0]));
            Proj4php::reportDebug(sprintf("Dy=%f\n", $this->datum_params[1]));
            Proj4php::reportDebug(sprintf("Dz=%f\n", $this->datum_params[2]));
            Proj4php::reportDebug(sprintf("Rx=%f\n", $this->datum_params[3]));
            Proj4php::reportDebug(sprintf("Ry=%f\n", $this->datum_params[4]));
            Proj4php::reportDebug(sprintf("Rz=%f\n", $this->datum_params[5]));
            Proj4php::reportDebug(sprintf("M=%f\n", $this->datum_params[6])); 

            $Dx_BF = $this->datum_params[0];
            $Dy_BF = $this->datum_params[1];
            $Dz_BF = $this->datum_params[2];
            $Rx_BF = $this->datum_params[3];
            $Ry_BF = $this->datum_params[4];
            $Rz_BF = $this->datum_params[5];
            $M_BF = $this->datum_params[6];

            $x_tmp = ($p->x - $Dx_BF) / $M_BF;
            $y_tmp = ($p->y - $Dy_BF) / $M_BF;
            $z_tmp = ($p->z - $Dz_BF) / $M_BF;

            $p->x = $x_tmp + $Rz_BF * $y_tmp - $Ry_BF * $z_tmp;
            $p->y = -$Rz_BF * $x_tmp + $y_tmp + $Rx_BF * $z_tmp;
            $p->z = $Ry_BF * $x_tmp - $Rx_BF * $y_tmp + $z_tmp;
        }
    }
}
