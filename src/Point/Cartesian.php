<?php

namespace proj4php\Point;

/**
 * A 2D cartesian x,y (plus zones if necessary) projected map reference.
 */

use proj4php\projection\AbstractProjection;
use proj4php\IsCloneable;
use proj4php\Datum;

class Cartesian
{
    use IsCloneable;

    /**
     * The 2D cartesian coordinates.
     */
    protected $x;
    protected $y;

    /**
     * Options to put the coordinates into context, such as zones and
     * hemisphere indicators.
     */
    protected $options = [];

    protected $projection;

    /**
     * Coords can be a CSV/SSV string, or an array.
     * Numerically keyed coords will be in x, y [, options] order.
     * The options are not the projection details; they are further details
     * to ensure the cartesian coordinates are unambiguous. Some map projections
     * may use options and some may not.
     *
     * @param array|string $coords
     * @param AbstractProjection|null $projection
     */
    public function __construct($coords, AbstractProjection $projection = null)
    {
        $this->setCoords($coords);

        if (isset($projection)) {
            $this->setProjection($projection);
        }
    }

    /**
     * Rename parseCoords?
     */
    protected function validateCoords($coords)
    {
        // TODO: expand a CSV string to an array.

        $x = null;
        $y = null;
        $options = [];

        foreach($coords as $key => $value) {
            $lkey = is_string($key) ? strtolower($key) : $key;

            if ($lkey === 'x' || $lkey === 0) {
                $x = $value;
            } elseif ($lkey === 'y' || $lkey === 1) {
                $y = $value;
            } else {
                $options[$lkey] = $value;
            }
        }

        return [
            'x' => floatval($x),
            'y' => floatval($y),
            'options' => $options,
        ];
    }

    protected function setCoords($x, $y = null, $options = [])
    {
        if ($y === null && $options === []) {
            list($x, $y, $options) = array_values($this->validateCoords($x));
        }

        $this->setX($x)->setY($y)->setOptions($options);

        return $this;
    }

    public function getX()
    {
        return $this->x;
    }

    public function getY()
    {
        return $this->y;
    }

    public function getOptions()
    {
        return $this->options;
    }

    public function getOption($key, $default = null)
    {
        $lkey = is_string($key) ? strtolower($key) : $key;

        if (array_key_exists($key, $this->getOptions())) {
            return $this->options[$lkey];
        }

        return $default;
    }

    protected function setOptions($value)
    {
        $this->options = $value;
        return $this;
    }

    protected function setX($value)
    {
        $this->x = $value;
        return $this;
    }

    protected function setY($value)
    {
        $this->y = $value;
        return $this;
    }

    protected function setProjection(AbstractProjection $projection)
    {
        $this->projection = $projection;
        return $this;
    }

    public function withProjection(AbstractProjection $projection)
    {
        return $this->getClone()->setProjection($projection);
    }

    public function getProjection()
    {
        return $this->projection;
    }

    public function getDatum()
    {
        $projection = $this->getProjection();

        return $projection ? $projection->getDatum() : null;
    }

    public function toArray()
    {
        return get_object_vars($this);
    }
}
