<?php

namespace proj4php\Point;

/**
 * A Geocentric ECEF (Earth Centred, Earth Fixed) cartesian coordinate object.
 * This is a geocentric coordinate based on X, Y and Z.
 * See https://en.wikipedia.org/wiki/ECEF
 *
 * Datum shifts can be done through simple addition for this point with
 * three-value shift parameters.
 */

use InvalidArgumentException;
use proj4php\Datum;

class Geocentric
{
    /**
     * X ordinate, in metres.
     * Centre of Earth to prime meridian on equitorial plane.
     * Also direction of major axis, a.
     */
    protected $x;

    /**
     * Y ordinate, in metres.
     * Centre of Earth to perpendictar to prime meridian on equitorial plane.
     */
    protected $y;

    /**
     * Z ordinate, in metres.
     * Centre of Earth to North pole.
     * Also same direction as minor axis, b.
     */
    protected $z;

    /**
     * This datum this point is defined in.
     * The point needs a datum to be meaningful.
     */
    protected $datum;

    /**
     * @param array|string|null $coords
     * @param Datum|null $datum
     */
    public function __construct($coords, Datum $datum = null)
    {
        $this->setCoords($coords);

        // TODO: set a default WGS84 datum.

        if (isset($datum)) {
            $this->setDatum($datum);
        }
    }

    /**
     * Shift this point to a new datum.
     * A data shift needs to happen via WGS84 as the intermediate.
     *
     * @param Datum The new datum to shift to.
     * @return self A clone of self with the coordinate shifted and the new datum connected.
     */
    public function shiftDatum(Datum $datum)
    {
        // The returned point will be a clone.
        $point = $this->getClone();

        // TODO: Only if the new datum is different, does the point need shifting.

        if ($datum !== $this->datum) { // Probably need to use a comparison method, to compare within a tolorance.
            $point = $point->withDatum($datum);

            // TODO: are either datums WGS84? Only one shift will be needed if so,
            // otherwise two shifts will be needed.

            // TODO: Do the datum shifts using the 3- or 7-value parameters.
            // This is done via the reference datum, so involves two steps.

            // Instances:
            // 1. Both datums are the same - no shift.
            // 2. Just the source is WGS84 - shift from only.
            // 3. Just the destination is WGS84 - shift to only.
            // 4. Neither source nor destination is WGS84 - two shifts.
            //
            // However, if either end is WGS84, then the shift parameters will be a
            // trivial calculation anyway, so we will not loose much processing by
            // running them.

            // Convert coordinats to WGS84.
            $wgs84_coords = static::coordsToWgs84($this->getCoords(), $this->getDatum());

            // Convert from WGS84 to the new datum.
            $datum_coords = static::coordsFromWgs84($wgs84_coords, $datum);

            // Set the coordinate and datum on the cloned point.
            return $point->setCoords($datum_coords)->setDatum($datum);
        }

        return $point;
    }

    /**
     * Convert a set of coordinates (x,y,z) to WGS84 coordinates using the given datum.
     */
    public static function coordsToWgs84($coords, Datum $datum)
    {
        $count = $datum->getShiftParameterCount();

        // If there are no shifting parameters, then we are already on WGS84.
        if ($count == Datum::SHIFT_PARAM_COUNT_NONE) {
            return $coords;
        }

        list($Dx, $Dy, $Dz) = $datum->getDisplacementParameters();

        // TODO: use a generic mapping function for $coords so we can deal with
        // any format in the interface.
        list($x, $y, $z) = array_values($coords);

        // Just linear shift parameters; no rotation.
        if ($count == Datum::SHIFT_PARAM_COUNT_3) {
            return [
                $x + $Dx,
                $y + $Dy,
                $z + $Dz,
            ];
        }

        if ($count == Datum::SHIFT_PARAM_COUNT_7) {
            // Get the rotational parameters in radians.
            list($Rx, $Ry, $Rz) = $datum->getRotationalParameters(Datum::RADIANS);

            // Get the scalar parameter as a multiplier.
            $M_BF = $datum->getScalarParameter(Datum::MULTIPLIER);

            return [
                $M_BF * ($x         - $Rz * $y  + $Ry * $z) + $Dx,
                $M_BF * ($Rz * $x   + $y        - $Rx * $z) + $Dy,
                $M_BF * (-$Ry * $x  + $Rx * $y  + $z      ) + $Dz,
            ];
        }
    }

    public function coordsFromWgs84($coords, Datum $datum)
    {
        // TODO: do the reverse calculations...
        return $coords;
    }

    /**
     * @return self A simple clone of self; a convenience method.
     */
    protected function getClone()
    {
        return clone $this;
    }

    /**
     * Set a datum without shifting any values.
     *
     * @param Datum|null $datum The new datum to use for the current coordinates.
     * @return self Clone of self with the new datum set.
     */
    public function withDatum(Datum $datum)
    {
        return $this->getClone()->setDatum($datum);
    }

    /**
     * Set a datum without shifting any values.
     *
     * @param Datum|null $datum The new datum to use for the current coordinates.
     * @return self
     */
    protected function setDatum(Datum $datum)
    {
        $this->datum = $datum;

        return $this;
    }

    /**
     * @return Datum|null The current defined datum.
     */
    protected function getDatum()
    {
        return $this->datum;
    }

    protected function validateOrdinate($ordinate, $name = 'unknown')
    {
        if (isset($ordinate)) {
            // FIXME: make sure the string can converted to a float.
            if (is_string($ordinate) || is_integer($ordinate)) {
                $ordinate = floatval($ordinate);
            }

            $type = gettype($ordinate);

            if ($type !== 'double' && $type !== 'integer') {
                throw new InvalidArgumentException(sprintf(
                    'Ordinate "%s" must be an integer, float or null; %s provided instead',
                    $name,
                    $type
                ));
            }

            return $ordinate;
        } else {
            throw new \Exception(sprintf('Ordinate %s cannot be null', $name));
        }
    }

    /**
     * Clone with a new X ordinate.
     *
     * @param float|int|string|null $x The ordinate value, castable to float.
     * @return self A clone of self with the new X ordinate
     */
    public function withX($x)
    {
        return $this->getClone()->setX($x);
    }

    /**
     * Set the X ordinate.
     *
     * @param float|int|string $x The ordinate value, castable to float.
     * @return self
     */
    protected function setX($x)
    {
        $this->x = $this->validateOrdinate($x, 'X');

        return $this;
    }

    /**
     * @return floatl Return just the x ordinate.
     */
    protected function getX()
    {
        return $this->x;
    }

    /**
     * Clone with a new Y ordinate.
     *
     * @param float|int|string $y The ordinate value, castable to float.
     * @return self A clone of self with the new Y ordinate
     */
    public function withY($y)
    {
        return $this->getClone()->setY($y);
    }

    /**
     * Set the Y ordinate.
     *
     * @param float|int|string $y The ordinate value, castable to float.
     * @return self
     */
    protected function setY($y)
    {
        $this->y = $this->validateOrdinate($y, 'Y');

        return $this;
    }

    /**
     * @return float Return just the y ordinate.
     */
    protected function getY()
    {
        return $this->y;
    }

    /**
     * Clone with a new Z ordinate.
     *
     * @param float|int|string $z The ordinate value, castable to float.
     * @return self A clone of self with the new Z ordinate
     */
    public function withZ($z)
    {
        return $this->getClone()->setZ($z);
    }

    /**
     * Set the Z ordinate.
     *
     * @param float|int|string $z The ordinate value, castable to float.
     * @return self
     */
    protected function setZ($z)
    {
        $this->z = $this->validateOrdinate($z, 'Z');

        return $this;
    }

    /**
     * @return floatl Return just the z ordinate.
     */
    protected function getZ()
    {
        return $this->z;
    }

    /**
     * Set x, y, z, all defaulting to null.
     * @param string|array|float|int The x ordinate, or the coordinate as an array or string.
     * @param string|float|int|null The y ordinate.
     * @param string|float|int|null The z ordinate.
     * @return self
     */
    public function withCoords($x, $y = null, $z = null)
    {
        return $this->getClone()->setCoords($x, $y, $z);
    }

    /**
     * Set the full coordinate.
     * Either pass all three ordinates separately, or as one parameter.
     *
     * @param string|array|float|int The x ordinate, or the coordinate as an array or string.
     * @param string|float|int|null The y ordinate.
     * @param string|float|int|null The z ordinate.
     * @return self
     */
    protected function setCoords($x, $y = null, $z = null)
    {
        // The first (and only) parameter can be a string for parsing.
        // We will assume it is a list of values.

        if (is_string($x) && $y === null && $z === null) {
            if (strpos($x, ',') !== false) {
                // Split by commas.
                $x = array_map('trim', explode(',', $x));
            } else {
                // Split by whitespace.
                $x = preg_split('/[\s]+/', trim($x));
            }
        }

        // The first (and only) parameter can be an array.

        if (is_array($x) && $y === null && $z === null) {
            // Check if the array uses associative keys (x, y and z).
            $assoc = [];
            array_walk($x, function ($item, $key) use (&$assoc) {
                $lc = strtolower($key);
                if ($lc >= 'x' && $lc <= 'z') {
                    // Initialise the array for the first element we encounter.
                    if (! $assoc) {
                        $assoc = [null, null, null];
                    }
                    $assoc[ord($lc) - ord('x')] = $item;
                }
            });

            // Yes, we have found associative keys, and put them in order,
            // so use the new ordered array.
            if ($assoc) {
                $x = $assoc;
            }

            // Make sure all elements are floats.
            list($x, $y, $z) = array_pad(array_values($x), 3, null);
        }

        $this->setX($x)->setY($y)->setZ($z);

        return $this;
    }

    /**
     * @return array Return the coordinates as an array.
     */
    public function getCoords()
    {
        return [
            'x' => $this->getX(),
            'y' => $this->getY(),
            'z' => $this->getZ(),
        ];
    }
}
