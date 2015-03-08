<?php
namespace Slugger\Service;

use Slugger\Model\SlugArray;

class SlugFactory
{
    public static $writable = true;

    /**
     * Factory method to wrap all sorts of types into slug model
     
     * @param string $name
     * @param array|\stdClass|\Traversable $mixed
     * @param bool $writable
     * @return SlugArray
     * @throws \RuntimeException
     */
    public static function createSlugModel($name, $mixed, $writable = null)
    {
        if ($writable === null) {
            $writable = static::$writable;
        }
        if (is_array($mixed)) {
            $model = new SlugArray($name, $mixed, $writable);
        } else {
            throw new \RuntimeException('Non-array types not supported yet');
        }
        return $model;
    }
}