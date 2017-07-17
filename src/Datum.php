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

    public function getEllipsoid()
    {
        return $this->ellipsoid;
    }

    public function getEs()
    {
        return $this->getEllipsoid()->getEs();
    }

    public function getA()
    {
        return $this->getEllipsoid()->getA();
    }

    public function getB()
    {
        return $this->getEllipsoid()->getB();
    }

    protected function setEllipsoid(Ellipsoid $ellipsoid = null)
    {
        // If no ellipsoid is given then provide a default.
        if (! isset($ellipsoid)) {
            // The default will be WGS84.
            $ellipsoid = new Ellipsoid();
        }

        $this->ellipsoid = $ellipsoid;
        return $this;
    }






//
//
//
//
// OLD stuff below.
//
//
//
//


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
}
