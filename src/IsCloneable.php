<?php

namespace proj4php;

trait IsCloneable
{
    /**
     * Clone the current object.
     * A comvenience method to help implement with* methods.
     *
     * @return $this A clone of $this
     */
    public function getClone()
    {
        return clone $this;
    }
}
