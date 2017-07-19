<?php

namespace proj4php;

/**
 * An ellipsoid value object.
 */

class Ellipsoid
{
    /**
     * The code to identify the ellipsoid.
     */
    protected $code;

    /**
     * The long name for the ellipsoid.
     */
    protected $name;

    /**
     * The semi-major axis (equitorial radius).
     * The equatorial radius will be longer than the polar axis.
     */
    protected $a;

    /**
     * The inverse flattening.
     */
    protected $rf;

    /**
     * Semi-minor (polar) axis.
     * Derived.
     */
    protected $b;

    /**
     * Eccentricity.
     * Derived.
     */
    protected $e;

    /**
     * First eccentricity squared.
     * Derived.
     */
    protected $es;

    /**
     * Second eccentricity squared.
     * Derived.
     */
    protected $es2;

    /**
     * Tolerance (decimal digits) used to compare equality of floats.
     */
    protected $epsilon = 1E-6;

    protected $defaultEllipsoid = [
        6378137.0,
        298.257223563,
        'WGS84',
        'World Geodetic System (1984)',
    ];

    /**
     * The ellipsoid accepts the semi-minor axis and the reverse flattening
     * as default constructor arguments. Other parametes can be derived.
     */
    public function __construct($a = null, $rf = null, $code = null, $name = null)
    {
        // The default ellipsoid is WGS84.

        if ($a == null && $rf == null) {
            list($a, $rf, $code, $name) = $this->defaultEllipsoid;
        }

        $this->setParameters($a, $rf, $code, $name);
    }

    protected function setParameters($a, $rf, $code, $name)
    {
        $this->setA($a);
        $this->setRf($rf);
        $this->code = $code;
        $this->name = $name;
    }

    protected function getClone()
    {
        return clone $this;
    }

    /**
     * Reset all derived values after changing the source values.
     */
    protected function resetDerived()
    {
        $this->b = null;
        $this->e = null;
        $this->es = null;
        $this->es2 = null;

        return $this;
    }

    /**
     * Set the semi minor axis.
     */
    public function withA($a)
    {
        return $this->getClone()->setA($a)->resetDerived();
    }

    protected function setA($a)
    {
        $this->a = $a;
        return $this;
    }

    public function getA()
    {
        return $this->a;
    }

    /**
     * Set the inverse flattening.
     */
    public function withRf($rf)
    {
        return $this->getClone()->setRf($rf)->resetDerived();
    }

    protected function setRf($rf)
    {
        $this->rf = $rf;
        return $this;
    }

    public function getRf()
    {
        return $this->rf;
    }

    public function getF()
    {
        if ($this->getRf() === null) {
            // No flattening, i.e. a sphere.
            return 0;
        } else {
            return 1 / $this->rf;
        }
    }

    /**
     * Set the semi-minor axis and reverse flattening.
     * Derive the semi-major axis.
     */
    public function withBRf($b, $rf)
    {
        return $this->getClone()->setBRf($b, $rf);
    }

    protected function setBRf($b, $rf)
    {
        // Derive a.
        $a = ($b * $rf) / ($rf - 1);

        return $this->resetDerived()
            ->setA($a)
            ->setB($b)
            ->setRf($rf);
    }

    public static function fromBRf($b, $rf, $code = null, $name = null)
    {
        $instance = new static($b, $rf, $code, $name);
        return $instance->setBRf($b, $rf);
    }

    /**
     * Set the semi-minor axis and the semi-major axis.
     * Derive the reverse flattening.
     */
    public function withAB($a, $b)
    {
        return $this->getClone()->setAB($a, $b);
    }

    protected function setAB($a, $b)
    {
        // Derive rf from the major and minor axes.
        // If we are dealing with a sphere, then rf will be infinite, so
        // catch that.

        if (abs($a - $b) > $this->epsilon) {
            $rf = $a / ($a - $b);
        } else {
            $rf = null;
        }

        return $this->resetDerived()
            ->setA($a)
            ->setB($b)
            ->setRf($rf);
    }

    public static function fromAB($a, $b, $code = null, $name = null)
    {
        $instance = new static(null, null, $code, $name);
        return $instance->setAB($a, $b);
    }

    protected function setB($b)
    {
        $this->b = $b;
        return $this;
    }

    /**
     * Get the semi-minor axis, b.
     * Derived from a and rf.
     */
    public function getB()
    {
        if ($this->b === null) {
            // TODO: Validate these source values first.

            // Reset other derived values.
            $this->resetDerived();

            // Derived from a and rf.
            $this->b = (1.0 - 1.0 / $this->getRf()) * $this->getA();
        }

        return $this->b;
    }

    /**
     * Eccentricity.
     */
    public function getE()
    {
        if ($this->e === null) {
            $div = $this->getB() / $this->getA();
            $this->e = sqrt(1.0 - $div * $div);
        }

        return $this->e;
    }

    /**
     * First eccentricity squared.
     */
    public function getEs()
    {
        if ($this->es === null) {
            $a2 = $this->getA() * $this->getA();
            $b2 = $this->getB() * $this->getB();

            $this->es = ($a2 - $b2) / $a2;
        }

        return $this->es;
    }

    /**
     * Second eccentricity squared.
     */
    public function getEs2()
    {
        if ($this->es2 === null) {
            $a2 = $this->getA() * $this->getA();
            $b2 = $this->getB() * $this->getB();

            $this->es2 = ($a2 - $b2) / $b2;
        }

        return $this->es2;
    }

    public function getParameters()
    {
        return [
            'a' => $this->getA(),
            'rf' => $this->getRf(),
            'code' => $this->getCode(),
            'name' => $this->getName(),
        ];
    }

    /**
     * Determine if the spheroid is a sphere, within a tolerance.
     * TODO: Where should the tolerance come from? The current PHP setting?
     */
    public function isSphere()
    {
        return abs($this->getA() - $this->getB()) < $this->epsilon;
    }

    public function getCode()
    {
        return $this->code;
    }

    public function getName()
    {
        return $this->code;
    }
}
