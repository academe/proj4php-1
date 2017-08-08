<?php

namespace proj4php\Point;

/**
 * A UTM point, with x, y, zone and north/south indicator.
 *
 * CHECKME: is a UTM coordinate really an extension if an ENU coordinate?
 * It seems to have no height component.
 * TODO: this is an extension of a Cartesian coorinate.
 */

use proj4php\Datum;

class Utm extends Enu // TODO: No, it extends a 2D coordinate.
{
    protected $zone;
    protected $south = false;

    protected $projection;
    protected $datum;

    public function getZone()
    {
        return $this->zone;
    }

    public function getSouth()
    {
        return $this->south;
    }

    // Maybe easting/northing/context-array for consistency across all ENU coordinate references?
    protected function setCoords($coords)
    {
        list($easting, $northing, $zone, $south) = array_values($this->validateCoords($coords));

        $this->setEasting($easting)
            ->setNorthing($northing)
            ->setZone($zone)
            ->setSouth($south);

        return $this;
    }

    protected function validateCoords($coords)
    {
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
                case 'zone':
                    $zone = $value;
                    break;
                case 'south':
                    $south = (bool)$value;
                    break;
                case 'north':
                    $south = ! (bool)$value;
                    break;
            }
        }

        return [
            'easting' => floatval($easting),
            'northing' => floatval($northing),
            'zone' => $zone,
            'south' => $south,
        ];
    }

    protected function setZone($value)
    {
        $this->zone = $value;
        return $this;
    }

    protected function setSouth($value)
    {
        $this->south = (bool)$value;
        return $this;
    }
}
