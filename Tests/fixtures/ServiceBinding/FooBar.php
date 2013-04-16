<?php

namespace BeSimple\SoapBundle\Tests\fixtures\ServiceBinding;

class FooBar
{
    protected $foo;
    protected $bar;

    public function __construct(Foo $foo = null, Bar $bar = null)
    {
        $this->foo = $foo;
        $this->bar = $bar;
    }
}