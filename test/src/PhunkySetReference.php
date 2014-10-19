<?php

use Eloquent\Phony\Phpunit\Phony;

class PhunkySetReference
{
    public function __construct($value)
    {
        $this->value = $value;
    }

    public $value;
}
