<?php

namespace proj4php;

/**
 * Author : Julien Moquet
 * 
 * Inspired by Proj4js from Mike Adair madairATdmsolutions.ca
 * and Richard Greenwood rich@greenwoodmap.com 
 * License: LGPL as per: http://www.gnu.org/copyleft/lesser.html 
 *
 * All methods in this class are static and all data are constants.
 * There is no need to instantiate this class.
 */

class Common
{
    // 3.141592653589793238; //Math.PI,
    const PI = M_PI;
    // 1.570796326794896619; //Math.PI*0.5,
    const HALF_PI = M_PI_2;
    // Math.PI*2,
    const TWO_PI = 6.283185307179586477;
    const FORTPI = 0.78539816339744833;
    const R2D = 57.29577951308232088;
    const D2R = 0.01745329251994329577;
    const SEC_TO_RAD = 4.84813681109535993589914102357e-6; /* SEC_TO_RAD = Pi/180/3600 */
    const EPSLN = 1.0e-10;
    const MAX_ITER = 20;
    // following constants from geocent.c
    // cosine of 67.5 degrees
    const COS_67P5 = 0.38268343236508977;
    // Toms region 1 constant
    const AD_C = 1.0026000;

    // datum_type values
    const PJD_UNKNOWN   = 0;
    const PJD_3PARAM    = 1;
    const PJD_7PARAM    = 2;
    const PJD_GRIDSHIFT = 3;
    // WGS84 or equivalent
    const PJD_WGS84     = 4;
    // WGS84 or equivalent
    const PJD_NODATUM   = 5;

    // only used in grid shift transforms
    const SRS_WGS84_SEMIMAJOR = 6378137.0;

    // ellipoid pj_set_ell.c

    const SIXTH = .1666666666666666667; /* 1/6 */
    const RA4 = .04722222222222222222; /* 17/360 */
    const RA6 = .02215608465608465608; /* 67/3024 */
    const RV4 = .06944444444444444444; /* 5/72 */
    const RV6 = .04243827160493827160; /* 55/1296 */


    /**
     * meridinal distance for ellipsoid and inverse
     * 8th degree - accurate to < 1e-5 meters when used in conjuction
     * with typical major axis values.
     * Inverse determines phi to EPS (1e-11) radians, about 1e-6 seconds.
     */
    const C00 = 1.0;
    const C02 = 0.25;
    const C04 = 0.046875;
    const C06 = 0.01953125;
    const C08 = 0.01068115234375;
    const C22 = 0.75;
    const C44 = 0.46875;
    const C46 = 0.01302083333333333333;
    const C48 = 0.00712076822916666666;
    const C66 = 0.36458333333333333333;
    const C68 = 0.00569661458333333333;
    const C88 = 0.3076171875;




    /**
     * Function to compute constant small q which is the radius of a 
     * parallel of latitude, phi, divided by the semimajor axis.
     * 
     * @param float $eccent
     * @param float $sinphi
     * @return float
     */
    public static function qsfnz($eccent, $sinphi)
    {
        if ($eccent > 1.0e-7) {
            $con = $eccent * $sinphi;

            return (
                ( 1.0 - $eccent * $eccent)
                * ($sinphi / (1.0 - $con * $con) - (.5 / $eccent) * log( (1.0 - $con) / (1.0 + $con) ))
            );
        }

        return (2.0 * $sinphi);
    }

    /**
     * @param float $esinp
     * @param float $exp
     * @return float
     */
    public static function srat($esinp, $exp)
    {
        return pow((1.0 - $esinp) / (1.0 + $esinp), $exp);
    }

    /**
     * IGNF - DGR : algorithms used by IGN France
     * Adjust latitude to -90 to 90; input in radians
     * 
     * @param float $x
     * @return float
     */
    public static function adjust_lat($x)
    {
        $x = (abs($x) < M_PI_2)
            ? $x
            : ($x - (static::sign($x) * M_PI) );

        return $x;
    }


    /**
     * 
     * @param float $x
     * @param float $L
     * @return float
     */
    public static function fL($x, $L)
    {
        return 2.0 * atan($x * exp($L)) - M_PI_2;
    }

    /**
     * Inverse Latitude Isometrique - close to ph2z
     * 
     * @param float $eccent
     * @param float $ts
     * @return float
     */
    public static function invlatiso($eccent, $ts)
    {
        $phi = static::fL(1.0, $ts);
        $Iphi = 0.0;
        $con = 0.0;

        do {
            $Iphi = $phi;
            $con = $eccent * sin($Iphi);
            $phi = static::fL(exp($eccent * log((1.0 + $con) / (1.0 - $con)) / 2.0 ), $ts);
        } while(abs($phi - $Iphi) > 1.0e-12);

        return $phi;
    }

    /**
     * Grande Normale
     * 
     * @param float $a
     * @param float $e
     * @param float $sinphi
     * @return float
     */
    public static function gN($a, $e, $sinphi)
    {
        $product = $e * $sinphi;
        return $a / sqrt(1.0 - $product * $product);
    }

    /**
     * code from the PROJ.4 pj_mlfn.c file;  this may be useful for other projections
     * 
     * @param float $es
     * @return float
     */
    public static function pj_enfn($es)
    {
        $en = array();
        $en[0] = static::C00 - $es * (static::C02 + $es * (static::C04 + $es * (static::C06 + $es * static::C08)));
        $en[1] = $es * (static::C22 - $es * (static::C04 + $es * (static::C06 + $es * static::C08)));
        $t = $es * $es;
        $en[2] = $t * (static::C44 - $es * (static::C46 + $es * static::C48));
        $t *= $es;
        $en[3] = $t * (static::C66 - $es * static::C68);
        $en[4] = $t * $es * static::C88;

        return $en;
    }

    /**
     * @param float $phi
     * @param float $sphi
     * @param float $cphi
     * @param float $en
     * @return float
     */
    public static function pj_mlfn($phi, $sphi, $cphi, $en)
    {
        $cphi *= $sphi;
        $sphi *= $sphi;

        return (
            $en[0] * $phi
            - $cphi * ($en[1] + $sphi * ($en[2] + $sphi * ($en[3] + $sphi * $en[4])))
        );
    }

    /**
     * 
     * @param float $arg
     * @param float $es
     * @param float $en
     * @return float
     */
    public static function pj_inv_mlfn($arg, $es, $en)
    {
        $k = (float) 1 / (1 - $es);
        $phi = $arg;
        for ($i = Common::MAX_ITER; $i; --$i) {
            // rarely goes over 2 iterations
            $s = sin($phi);
            $t = 1.0 - $es * $s * $s;

            //$t = static::pj_mlfn($phi, $s, cos($phi), $en) - $arg;
            //$phi -= $t * ($t * sqrt($t)) * $k;
            $t = (static::pj_mlfn( $phi, $s, cos($phi), $en) - $arg) * ($t * sqrt($t)) * $k;
            $phi -= $t;

            if (abs($t) < Common::EPSLN) {
                return $phi;
            }
        }

        Proj4php::reportError("cass:pj_inv_mlfn: Convergence error");

        return $phi;
    }
}
