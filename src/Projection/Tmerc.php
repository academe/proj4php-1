<?php

namespace proj4php\Projection;

class Tmerc extends AbstractProjection
{
    /**
     * following functions from gctpc cproj.c for transverse mercator projections
     *
     * @param float $es
     * @return float
     */
    public function e0fn($es)
    {
        return 1.0 - 0.25 * $es * (1.0 + $es / 16.0 * (3.0 + 1.25 * $es));
    }

    /**
     * @param float $es
     * @return float
     */
    public function e1fn($es)
    {
        return (0.375 * $es * (1.0 + 0.25 * $es * (1.0 + 0.46875 * $es)));
    }

    /**
     * @param float $es
     * @return float
     */
    public function e2fn($es)
    {
        return (0.05859375 * $es * $es * (1.0 + 0.75 * $es));
    }

    /**
     * @param float $es
     * @return float
     */
    public function e3fn($es)
    {
        return ($es * $es * $es * (35.0 / 3072.0));
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
     * Wrap up all the e0-e3 and ml0 calculations into one function.
     *
     * @param float $es Eccentricity squared.
     * @param float $phi lat_0 units TBC but I guess is radians?
     * @param float $a Ellipsoid major axis, metres.
     * @return array [e0, e1, e2, e3, ml0]
     */
    public function emlfn($es, $phi, $a)
    {
        $e0 = $this->e0fn($es);
        $e1 = $this->e1fn($es);
        $e2 = $this->e2fn($es);
        $e3 = $this->e3fn($es);

        $ml = $this->mlfn($e0, $e1, $e2, $e3, $phi);

        return [$e0, $e1, $e2, $e3, $a * $ml];
    }
}
