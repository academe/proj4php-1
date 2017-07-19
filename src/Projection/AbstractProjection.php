<?php

namespace proj4php\Projection;

abstract class AbstractProjection
{
    const EPSLN = 1.0e-10;

    /**
     * Function to compute the constant small t for use in the forward
     * computations in the Lambert Conformal Conic and the Polar
     * Stereographic projections.
     * 
     * @param float $eccent
     * @param float $phi
     * @param float $sinphi
     * @return float
     */
    public static function tsfnz($eccent, $phi, $sinphi)
    {
        $con = $eccent * $sinphi;
        $com = 0.5 * $eccent;
        $con = pow(((1.0 - $con) / (1.0 + $con)), $com);

        return (tan(0.5 * (M_PI_2 - $phi) ) / $con);
    }

    /**
     * Adjust longitude to -180 to 180; input in radians
     * 
     * @param float $x
     * @return float
     */
    public static function adjust_lon($x)
    {
        return (abs($x) < M_PI)
            ? $x
            : ($x - (static::sign($x) * (M_PI + M_PI)));
    }

    /**
     * Return the sign of an argument.
     * This differs from PHP's core sign() function in that zero returns as postive.
     * 
     * @param int|float $x The numeric valid to test.
     * @return int -1 for negative; +1 for positive or zero
     */
    public static function sign($x)
    {
        return ($x < 0.0 ? -1 : 1);
    }

    /**
     * Function to compute the constant small m which is the radius of
     * a parallel of latitude, phi, divided by the semimajor axis.
     * 
     * @param float $eccent
     * @param float $sinphi
     * @param float $cosphi
     * @return float
     */
    public static function msfnz($eccent, $sinphi, $cosphi)
    {
        $con = $eccent * $sinphi;
        return $cosphi / (sqrt(1.0 - $con * $con));
    }

    /**
     * Latitude Isometrique - close to tsfnz ...
     * 
     * @param float $eccent
     * @param float $phi
     * @param float $sinphi
     * @return float
     */
    public static function latiso($eccent, $phi, $sinphi)
    {
        if (abs($phi) > M_PI_2) {
            return +NaN;
        }

        if ($phi == M_PI_2) {
            return INF;
        }

        if ($phi == -1.0 * M_PI_2) {
            return -1.0 * INF;
        }

        $con = $eccent * $sinphi;

        return log(tan((M_PI_2 + $phi) / 2.0 ) )
            + $eccent * log( (1.0 - $con) / (1.0 + $con)) / 2.0;
    }

    /**
     * Function to compute the latitude angle, phi2, for the inverse of the
     * Lambert Conformal Conic and Polar Stereographic projections.
     * 
     * rise up an assertion if there is no convergence.
     * 
     * @param float $eccent
     * @param float $ts
     * @return float|int
     */
    public static function phi2z($eccent, $ts)
    {
        $eccnth = 0.5 * $eccent;
        $phi = M_PI_2 - 2 * atan($ts);

        for ($i = 0; $i <= 15; $i++) {
            $con = $eccent * sin($phi);
            $dphi = M_PI_2
                - 2 * atan($ts * (pow(((1.0 - $con) / (1.0 + $con)), $eccnth )))
                - $phi;
            $phi += $dphi;

            // Is this self::EPSLN? I think it is.
            if (abs($dphi) <= 0.0000000001) {
                return $phi;
            }
        }

        assert("false; /* phi2z has NoConvergence */");

        // What does this return value mean? Maybe return null or raise an exeption.
        return (-9999);
    }
}
