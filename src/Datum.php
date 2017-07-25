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

        // Check the ellipsoids - are they the same?
        // The two driving values are a and rf.
        // FIXME: this hard-coded tolerance.
        if (
            abs($this->getEllipsoid()->getA() - $datum->getEllipsoid()->getA()) > 1.0e-6
            || abs($this->getEllipsoid()->getRf() - $datum->getEllipsoid()->getRf()) > 1.0e-6
        ) {
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

    public function getA()
    {
        return $this->getEllipsoid()->getA();
    }

    public function getB()
    {
        return $this->getEllipsoid()->getB();
    }

    public function getRf()
    {
        return $this->getEllipsoid()->getRf();
    }

    public function getEs()
    {
        return $this->getEllipsoid()->getEs();
    }

    public function getEp2()
    {
        return $this->getEllipsoid()->getEp2();
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
}
