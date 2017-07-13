<?php

namespace proj4php\Point;

/**
 * An Geocentric ECEF (Earth Centred Earth Fixed) point value object.
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
     * X ordinate.
     * Centre of Earth to prime meridian on equitorial plane.
     * Also direction of major axis, a.
     */
    protected $x;

    /**
     * Y ordinate.
     * Centre of Earth to perpendictar to prime meridian on equitorial plane.
     */
    protected $y;

    /**
     * Z ordinate.
     * Centre of Earth to North pole.
     * Also direction of manor axis, b.
     */
    protected $z;

    /**
     * This datum this point is defined in.
     */
    protected $datum;

    /**
     * @param array|string|null $coords
     * @param Datum|null $datum
     */
    public function __construct($coords = null, Datum $datum = null)
    {
        if (isset($coords)) {
            $this->setCoords($coords);
        }

        if (isset($datum)) {
            $this->setDatum($datum);
        }
    }

    /**
     * Shift this point to a new datum.
     */
    public function shiftDatum(Datum $datum)
    {
        // The returned point will be a clone.
        $point = $this->getClone();

        // TODO: Only if the datum is different, do the point coordinates
        // need shifting.

        if ($datum !== $this->datum) { // Probably need to use a comparison method.
            $point = $point->withDatum($datum);

            // TODO: Do the datum shifts using the 3- or 7-value parameters.
            // This is done via the reference datum, so involves two steps.
        }

        return $point;
    }

    protected function getClone()
    {
        return clone $this;
    }

    /**
     * Set a datum without shifting any values.
     */
    public function withDatum(Datum $datum)
    {
        return $this->getClone()->setDatum($datum);
    }

    /**
     * Set a datum without shifting any values.
     */
    protected function setDatum(Datum $datum)
    {
        $this->datum = $datum;
    }

    protected function getDatum()
    {
        return $this->datum;
    }

    protected function validateOrdinate($ordinate, $name = 'unknown')
    {
        if (isset($ordinate)) {
            $type = gettype($ordinate);

            if ($type !== 'double' && $type !== 'integer') {
                throw new InvalidArgumentException(sprintf(
                    'Ordinate "%s" must be an integer, float or null; %s provided instead',
                    $name,
                    $type
                ));
            }

            return (float)$ordinate;
        } else {
            return null;
        }
    }

    /**
     * Clone with a new X ordinate.
     */
    public function withX($x)
    {
        return $this->getClone()->setX($x);
    }

    /**
     * Set the X ordinate.
     */
    protected function setX($x)
    {
        $this->x = $this->validateOrdinate($x, 'X');

        return $this;
    }

    protected function getX()
    {
        return $this->x;
    }

    /**
     * Clone with a new Y ordinate.
     */
    public function withY($y)
    {
        return $this->getClone()->setY($y);
    }

    /**
     * Set the Y ordinate.
     */
    protected function setY($y)
    {
        $this->y = $this->validateOrdinate($y, 'Y');

        return $this;
    }

    protected function getY()
    {
        return $this->y;
    }

    /**
     * Clone with a new Z ordinate.
     */
    public function withZ($z)
    {
        return $this->getClone()->setZ($z);
    }

    /**
     * Set the Z ordinate.
     */
    protected function setZ($z)
    {
        $this->z = $this->validateOrdinate($z, 'Z');

        return $this;
    }

    protected function getZ()
    {
        return $this->z;
    }

    /**
     * Set x, y, z, all defaulting to null.
     */
    public function withCoords($x = null, $y = null, $z = null)
    {
        return $this->getClone()->setCoords($x, $y, $z);
    }

    protected function setCoords($x = null, $y = null, $z = null)
    {
        // The first (and only) parameter could be a string.
        // We will assume it is a list of values.

        if (is_string($x) && $y === null && $z === null) {
            if (strpos($x, ',') !== false) {
                // Split by commas.
                $x = array_map('trim', explode(',', $x));
            } else {
                // Split by whitespace.
                $x = preg_split('/[\s]+/', trim($x));
            }

            // Make sure all elements are floats.
            $x = array_map('floatval', $x);
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

            // If numeric keys are used.
            list($x, $y, $z) = array_pad(array_values($x), 3, null);
        }

        $this->setX($x)->setY($y)->setZ($z);

        return $this;
    }

    public function getCoords()
    {
        return [
            'x' => $this->getX(),
            'y' => $this->getY(),
            'z' => $this->getZ(),
        ];
    }
}
