<?php

namespace BeSimple\SoapBundle\Tests\fixtures\ServiceBinding;

class BarRecursive
{
    private $foo;

    public function __construct(FooRecursive $foo = null)
    {
        $this->foo = $foo;
    }
}