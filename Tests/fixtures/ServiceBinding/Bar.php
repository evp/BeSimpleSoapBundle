<?php

namespace BeSimple\SoapBundle\Tests\fixtures\ServiceBinding;

class Bar
{
    private $foo;
    private $bar;

    public function __construct($foo = null, $bar = null)
    {
        $this->foo = $foo;
        $this->bar = $bar;
    }
}