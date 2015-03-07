<?php
require 'vendor/autoload.php';

$x = new Slugger\Model\SlugArray('test', ['foo' => 'bar', 'zar' => 123]);
echo 'Name: ', $x->getName(), PHP_EOL,
    $x->get('foo'), ' is identical to ', $x->get('test.foo'), PHP_EOL;
