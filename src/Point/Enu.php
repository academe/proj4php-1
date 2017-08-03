<?php

namespace proj4php\Point;

/**
 * An ENU (East North Up) point value object.
 * This represents a projected map coordiate.
 */

use proj4php\Datum;

class Enu
{
    protected $easting;
    protected $northing;
    protected $up;

    protected $projection;

    /**
     * @param array|string $coords
     * @param Datum|null $datum
     */
    public function __construct($coords, Datum $datum = null)
    {
        $this->setCoords($coords);

        if (isset($datum)) {
            $this->setDatum($datum);
        }
    }

    protected function validateCoords($coords)
    {
        // TODO: expand a CSV string to an array.

        foreach($coords as $key => $value) {
            $lkey = strtolower($key);

            // CHECKME: does x always equate to an easting, or can the order be optionally switched?

            switch ($lkey) {
                case 'easting':
                case 'x':
                    $easting = $value;
                    break;
                case 'northing':
                case 'y':
                    $northing = $value;
                    break;
            }
        }

        return [
            'easting' => floatval($easting),
            'northing' => floatval($northing),
        ];
    }

    protected function setCoords($coords)
    {
        list($easting, $northing) = array_values($this->validateCoords($coords));

        $this->setEasting($easting)
            ->setNorthing($northing);

        return $this;
    }

    public function getEasting()
    {
        return $this->easting;
    }

    public function getX()
    {
        return $this->getEasting();
    }

    public function getNorthing()
    {
        return $this->northing;
    }

    public function getY()
    {
        return $this->getNorthing();
    }

    protected function setEasting($value)
    {
        $this->easting = $value;
        return $this;
    }

    protected function setNorthing($value)
    {
        $this->northing = $value;
        return $this;
    }

    protected function setDatum(Datum $datum)
    {
        $this->datum = $datum;
        return $this;
    }

    public function getDatum()
    {
        return $this->datum;
    }

    public function toArray()
    {
        return get_object_vars($this);
    }
}
