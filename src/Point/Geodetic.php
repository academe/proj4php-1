<?php

namespace proj4php\Point;

/**
 * A Geodetic point value object.
 * Geodetic coordinates consist of latitude ϕ (phi), longitude λ (lambda) and height h.
 * See https://en.wikipedia.org/wiki/Geographic_coordinate_conversion
 *
 * This is an unprojected point.
 */

use proj4php\Datum;

class Geodetic
{
    /**
     * Various units.
     */
    const DEGREES = 'degrees';
    const RADIANS = 'radians';

    /**
     * Latitude, degrees.
     */
    protected $lat;

    /**
     * Longitude, degrees.
     */
    protected $long;

    /**
     * Height from the reference ellipsoid, metres.
     */
    protected $height;

    /**
     * This datum this point is defined in.
     * The point needs a datum to be meaningful.
     */
    protected $datum;

    /**
     * @param array|string $latLong
     * @param Datum|null $datum
     */
    public function __construct($latLong, Datum $datum = null)
    {
        $this->setLatLong($latLong);

        // TODO: set a default WGS84 datum.

        if (isset($datum)) {
            $this->setDatum($datum);
        }
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
    public function getDatum()
    {
        return $this->datum;
    }

    /**
     * Ensure the latitude is the correct data type.
     * Throws an exception if out of range (plus or minus 90 degrees).
     */
    protected function validateLatitude($lat)
    {
        if (gettype($lat) != 'double') {
            $lat = floatval($lat);
        }

        if ($lat > 90 || $lat < -90) {
            throw new \Exception(sprintf(
                'Latitude must lie between -90 and +90 degrees; %f provided',
                $lat
            ));
        }

        return $lat;
    }

    /**
     * Clone with a new latitude, degrees.
     *
     * @param float|int|string $lat The ordinate value, castable to float.
     * @return self A clone of self with the new X ordinate
     */
    public function withLat($lat)
    {
        return $this->getClone()->setLat($lat);
    }

    /**
     * Set the latitude.
     *
     * @param float|int|string $lat The latitude value, castable to float.
     * @return self
     */
    protected function setLat($lat)
    {
        $this->lat = $this->validateLatitude($lat);

        return $this;
    }

    /**
     * @return float Return just the latitude.
     */
    public function getLat($units = self::DEGREES)
    {
        if ($units == self::DEGREES) {
            return $this->lat;
        }

        if ($units == self::RADIANS) {
            return deg2rad($this->lat);
        }

        // TODO: error
    }

    /**
     * Ensure the longitude is the correct data type.
     * Wrap the longitude into a vald range (plus or minus 180 degrees).
     */
    protected function validateLongitude($long)
    {
        if (gettype($long) != 'double') {
            $long = floatval($long);
        }

        while($long > 180.0) $long -= 360;
        while($long <= -180.0) $long += 360;

        return $long;
    }

    /**
     * Clone with a new longitude, degrees.
     *
     * @param float|int|string $lat The ordinate value, castable to float.
     * @return self A clone of self with the new X ordinate
     */
    public function withLong($lon)
    {
        return $this->getClone()->setLon($long);
    }

    /**
     * Set the longitude.
     *
     * @param float|int|string $lat The longitude value, castable to float.
     * @return self
     */
    protected function setLong($long)
    {
        $this->long = $this->validateLongitude($long);

        return $this;
    }

    /**
     * @return float Return just the longitude.
     */
    public function getLong($units = self::DEGREES)
    {
        if ($units == self::DEGREES) {
            return $this->long;
        }

        if ($units == self::RADIANS) {
            return deg2rad($this->long);
        }

        // TODO: error
    }

    /**
     * TODO: Ensure the height is the correct data type.
     */
    protected function validateHeight($height)
    {
        return $height;
    }

    /**
     * Clone with a new height, metres.
     *
     * @param float|int|string $height The height value, castable to float.
     * @return self A clone of self with the new height.
     */
    public function withHeight($height)
    {
        return $this->getClone()->setHeight($height);
    }

    /**
     * Set the height.
     *
     * @param float|int|string $height The height value, castable to float.
     * @return self
     */
    protected function setHeight($height)
    {
        $this->height = $this->validateHeight($height);

        return $this;
    }

    /**
     * @return float Return just the height.
     */
    public function getHeight()
    {
        return isset($this->height) ? $this->height : 0.0;
    }

    /**
     * Set the full coordinate.
     * Either pass all three ordinates separately, or as one parameter.
     *
     * @param string|array|float|int $lat The latitude, or the coordinate as an array or string.
     * @param string|float|int|null $long The longitude.
     * @param string|float|int|null $height The height (optional).
     * @return self
     */
    protected function setLatLong($lat, $long = null, $height = null)
    {
        // The first (and only) parameter can be a string for parsing.
        // We will assume it is a list of values.

        if (is_string($lat) && $long === null && $height === null) {
            if (strpos($lat, ',') !== false) {
                // Split by commas.
                $lat = array_map('trim', explode(',', $lat));
            } else {
                // Split by whitespace.
                $lat = preg_split('/[\s]+/', trim($lat));
            }
        }

        // The first (and only) parameter can be an array.

        if (is_array($lat) && $long === null && $height === null) {
            // Check if the array uses associative keys (lat, long and height, or equivalent).
            $assoc = [];
            $aliases = [
                'lat'       => 'lat',
                'latitude'  => 'lat',
                'lon'       => 'long',
                'long'      => 'long',
                'longitude' => 'long',
                'h'         => 'height',
                'height'    => 'height',
            ];
            array_walk($lat, function ($item, $key) use (&$assoc, $aliases) {
                $lc = strtolower($key);
                if (array_key_exists($lc, $aliases)) {
                    // Initialise the array for the first element we encounter.
                    if (! $assoc) {
                        $assoc = ['lat' => null, 'long' => null, 'height' => null];
                    }
                    $assoc[$aliases[$lc]] = $item;
                }
            });

            // Yes, we have found associative keys, and put them in order,
            // so use the new ordered array.
            if ($assoc) {
                $lat = $assoc;
            }

            // Make sure all elements are floats.
            list($lat, $long, $height) = array_pad(array_values($lat), 3, null);
        }

        $this->setLat($lat)->setLong($long)->setHeight($height);

        return $this;
    }

    /**
     * The datum needs to be read separately.
     * @return array Return the coordinates as an array.
     */
    public function toArray()
    {
        return [
            'lat' => $this->getLat(),
            'long' => $this->getLong(),
            'height' => $this->getHeight(),
        ];
    }

    /**
     * Create a Geodetic coordinate from a Geocentric coordinate.
     */
    public static function fromGeocentric(Geocentric $geocentric)
    {
        $x = $geocentric->getX();
        $y = $geocentric->getY();
        $z = $geocentric->getZ();

        $datum = $geocentric->getDatum();

        $a = $datum->getA();
        $b = $datum->getB();
        $es = $datum->getEs();

        $genau = 1e-12;
        $genau2 = ($genau * $genau);
        $maxiter = 30;

        $x2 = $x * $x;
        $y2 = $y * $y;
        $z2 = $z * $z;

        $P = sqrt($x2 + $y2);
        $RR = sqrt($x2 + $y2 + $z2);

        $atPole = false;

        // Special cases for latitude and longitude.
        if ($P / $a < $genau) {
            // Special case, if P=0. (X=0., Y=0.)
            $atPole = true;
            $long = 0.0;

            // If (X,Y,Z)=(0.,0.,0.) then Height becomes semi-minor axis
            // of ellipsoid (=center of mass), Latitude becomes PI/2

            // Something here smells wrong - the height, plus does it cater for both poles?
            if ($RR / $a < $genau) {
                $lat = 90;
                $height = -$b;
                return new static([$long, $lat, $height], $datum);
            }
        } else {
            // ellipsoidal (geodetic) longitude
            // interval: -PI < Longitude <= +PI
            $long = atan2($y, $x);
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
        $CT = $z / $RR;
        $ST = $P / $RR;
        $RX = 1.0 / sqrt(1.0 - $es * (2.0 - $es) * $ST * $ST);
        $CPHI0 = $ST * (1.0 - $es) * $RX;
        $SPHI0 = $CT * $RX;
        $iter = 0;

        // loop to find sin(Latitude) res$p-> Latitude
        // until |sin(Latitude(iter)-Latitude(iter-1))| < genau
        do {
            ++$iter;
            $RN = $a / sqrt(1.0 - $es * $SPHI0 * $SPHI0);

            //  ellipsoidal (geodetic) height
            $height = $P * $CPHI0 + $z * $SPHI0 - $RN * (1.0 - $es * $SPHI0 * $SPHI0);

            $RK = $es * $RN / ($RN + $height);
            $RX = 1.0 / sqrt(1.0 - $RK * (2.0 - $RK) * $ST * $ST);
            $CPHI = $ST * (1.0 - $RK) * $RX;
            $SPHI = $CT * $RX;
            $SDPHI = $SPHI * $CPHI0 - $CPHI * $SPHI0;
            $CPHI0 = $CPHI;
            $SPHI0 = $SPHI;
        } while ($SDPHI * $SDPHI > $genau2 && $iter < $maxiter);

        // ellipsoidal (geodetic) latitude
        $lat = atan($SPHI / abs($CPHI));

        return new self([rad2deg($lat), rad2deg($long), $height], $datum);
    }

    /**
     * Create a Geodetic coordinate from a Geocentric coordinate.
     * The method used here is derived from 'An Improved Algorithm for
     * Geocentric to Geodetic Coordinate Conversion', by Ralph Toms, Feb 1996
     */
    public static function fromGeocentricNonIter(Geocentric $geocentric)
    {
        /*
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
            $atPole;   // indicates location is in polar region 
        */

        // Cast from string to float.
        // Since we are accepting the Point class only, then we can already
        // guarantee we have floats. A simple list($x, $y $Z) = $p->toArray() will
        // give us our values.

        $x = $geocentric->getX();
        $y = $geocentric->getY();
        $z = $geocentric->getZ();

        $datum = $geocentric->getDatum();

        $a = $datum->getA();
        $b = $datum->getB();
        $es = $datum->getEs();

        $atPole = false;

        $half_pi = deg2rad(90);

        // cosine of 67.5 degrees
        $COS_67P5 = 0.38268343236508977;

        // Toms region 1 constant
        $AD_C = 1.0026000;

        if ($x <> 0.0) {
            $kong = atan2($y, $x);
        } else {
            if ($y > 0) {
                $long = $half_pi;
            } elseif ($y < 0) {
                $long = -$half_pi;
            } else {
                $atPole = true;
                $long = 0.0;

                if ($z > 0.0) {
                    // north pole
                    $lat = $half_pi;
                } elseif ($z < 0.0) {
                    // south pole
                    $lat = -$half_pi;
                } else {
                    // centre of earth
                    $lat = $half_pi;
                    $height = -$b;
                    return new self([rad2deg($lat), rad2deg($long), $height], $datum);
                }
            }
        }

        $W2 = $x * $x + $y * $y;
        $W = sqrt($W2);
        $T0 = $z * $AD_C;
        $S0 = sqrt($T0 * $T0 + $W2);
        $Sin_B0 = $T0 / $S0;
        $Cos_B0 = $W / $S0;
        $Sin3_B0 = $Sin_B0 * $Sin_B0 * $Sin_B0;
        $T1 = $z + $b * $ep2 * $Sin3_B0; // What is ep2
        $Sum = $W - $a * $es * $Cos_B0 * $Cos_B0 * $Cos_B0;
        $S1 = sqrt($T1 * $T1 + $Sum * $Sum);
        $Sin_p1 = $T1 / $S1;
        $Cos_p1 = $Sum / $S1;
        $Rn = $a / sqrt(1.0 - $es * $Sin_p1 * $Sin_p1);

        if ($Cos_p1 >= $COS_67P5) {
            $height = $W / $Cos_p1 - $Rn;
        } elseif ($Cos_p1 <= -$COS_67P5) {
            $height = $W / -$Cos_p1 - $Rn;
        } else {
            $height = $z / $Sin_p1 + $Rn * ($es - 1.0);
        }

        if ($atPole == false) {
            $lat = atan($Sin_p1 / $Cos_p1);
        }

        return new self([rad2deg($lat), rad2deg($long), $height], $datum);
    }

    /**
     * Shift to a new datum.
     */
    public function shiftDatum(Datum $datum)
    {
        // Only if the new datum is different, does the point need shifting,
        // so we can avoid some work by checking first.

        if (! $this->getDatum()->isSame($datum)) {
            $point = $this->fromGeocentric(
                Geocentric::fromGeodetic($this)->shiftDatum($datum)
            );
        } else {
            $point = $this->getClone()->setDatum($datum);
        }

        return $point;
    }
}
