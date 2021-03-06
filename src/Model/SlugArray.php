<?php
namespace Slugger\Model;

class SlugArray
{
    const CACHE_HASH_KEY = '.cache_hash'; 

    /**
     * @var array
     */
    protected $data = array();

    /**
     * @var string
     */
    protected $name = '';

    /**
     * @var string
     */
    protected $hash = null;

    /**
     * @var array
     */
    protected $cached = array();

    /**
     * Set this object as read-only data, or not (default writable)
     *
     * @var bool
     */
    protected $writable = true;

    /**
     * Array constructor
     * 
     * @param string $name
     * @param array $data
     * @param bool $writable
     */
    public function __construct($name = null, array $data = null, $writable = true)
    {
        if ($name) {
            $this->setName($name);
        }
        if ($data) {
            $this->setData($data);
        }
        $this->writable = (bool) $writable;
    }

    /**
     * Set data to read-only - There is no clean way back!
     *
     * @return $this
     */
    public function lock()
    {
        $this->writable = false;
        return $this;
    }

    /**
     * Set the actual data (reset cache, name, hash, ...)
     *
     * @param array $data
     * @return $this
     */
    public function setData(array $data)
    {
        if ($this->writable === false) {
            throw new \RuntimeException(
                sprintf(
                    '%s is read-only',
                    $this->name
                )
            );
        }
        if ($this->data) {
            $this->hash = null;
            $this->cached = array();
        }
        //check keys
        $keys = array_keys($data);
        if (count($keys) === 1 && $keys[0] === $this->name) {
            $data = $data[$this->name];
        }
        $this->data = $data;
        $this->hash = sha1(json_encode($data));
        //set special key to validate cache array
        $this->cached[self::CACHE_HASH_KEY] = $this->hash;
        return $this;
    }

    /**
     * Get value by slug (caches new slugs)
     *
     * @param string $slug
     * @param mixed $default
     * @return mixed
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    public function get($slug, $default = null)
    {
        if (!is_string($slug)) {
            throw new \InvalidArgumentException(
                sprintf(
                    '%s expects $slug to be a string, %s given',
                    __METHOD__,
                    is_object($slug) ? get_class($slug) : gettype($slug)
                )
            );
        }
        $path = $this->expandSlug($slug);
        $actualSlug = implode('.', $path);
        if (isset($this->cached[$actualSlug]) && $this->cached[self::CACHE_HASH_KEY] === $this->hash) {
            $data = $this->cached[$actualSlug];
        } else {
            $data = $this->data;
            //recursive search
            do {
                $key = array_shift($path);
                if (array_key_exists($key, $data)) {
                    $data = $data[$key];
                    if ($path && !is_array($data)) {
                        throw new \RuntimeException(
                            sprintf(
                            	'Unable to access %s in slug %s, %s resolved to SCALAR',
                                $path[0], $slug, $key
                            )
                        );
                    }
                } else if (!$path) {
                    //last slug, set data to default
                    $data = $default;
                } else {
                    throw new \RuntimeException(
                        sprintf(
                            'Invalid slug %s (%s not found)',
                            $slug, $key
                        )
                    );
                }
            } while ($path);
            $this->cached[$actualSlug] = $data;
        }
        return $data;
    }

    /**
     * Remove SCALAR at slug, this method can't remove TREE's, for safety
     * The caller must show clear intent, knowing what is being removed
     *
     * @param string $slug
     * @return $this
     * @throws \RuntimeException
     * @throws \LogicException
     * @throws \BadMethodCallException
     */
    public function removeScalarValue($slug)
    {
        if ($this->writable === false) {
            throw new \RuntimeException(
                sprintf(
                    'Cannot remove %s from %s, object is locked',
                    $slug,
                    $this->name
                )
            );
        }
        $path = $this->expandSlug($slug);
        $cleanSlug = implode('.', $path);
        //get the name of the node we're updating
        $key = array_pop($path);
        try {
            $data = &$this->getTreeReference(
                $path,
                $this->data
            );
        } catch (\RuntimeException $e) {
            throw new \LogicException(
                sprintf(
                    'Invalid slug %s: %s',
                    $slug,
                    $e->getMessage()
                ),
                0,
                $e
            );
        }
        if (array_key_exists($key, $data) && !is_array($data[$key])) {
            unset($data[$key]);
            $this->cached[$cleanSlug] = $value;
            $this->resetHash();
            $this->cached[self::CACHE_HASH_KEY] = $this->hash;
        } else {
            throw new \BadMethodCallException(
                sprintf(
                    '%s only removes existing SCALAR values, %s does not exist, or is a TREE',
                    __METHOD__,
                    $slug
                )
            );
        }
        return $this;
    }

    /**
     * Remove a tree from the data
     *
     * @param string $slug
     * @return $this
     * @throws \RuntimeException
     * @throws \LogicException
     * @throws \BadMethodCallException
     */
    public function removeTreeValue($slug)
    {
        if ($this->writable === false) {
            throw new \RuntimeException(
                sprintf(
                    'Cannot remove %s from %s, object is locked',
                    $slug,
                    $this->name
                )
            );
        }
        $path = $this->expandSlug($slug);
        $cleanSlug = implode('.', $path);
        //get the name of the node we're updating
        $key = array_pop($path);
        try {
            $data = &$this->getTreeReference(
                $path,
                $this->data
            );
        } catch (\RuntimeException $e) {
            throw new \LogicException(
                sprintf(
                    'Invalid slug %s: %s',
                    $slug,
                    $e->getMessage()
                ),
                0,
                $e
            );
        }
        if (array_key_exists($key, $data) && is_array($data[$key])) {
            unset($data[$key]);
            $this->cleanCacheTree($cleanSlug);
            $this->resetHash();
            $this->cached[self::CACHE_HASH_KEY] = $this->hash;
        } else {
            throw new \BadMethodCallException(
                sprintf(
                    '%s only removes existing TREE values, %s does not exist, or is SCALAR',
                    __METHOD__,
                    $slug
                )
            );
        }
        return $this;
    }

    /**
     * 
     * Enter description here ...
     * @param string $slug
     * @param string|int|float|bool|null $value
     * @return $this
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     * @throws \LogicExcpetion
     * @throws \BadMethodCallException
     */
    public function updateScalarValue($slug, $value)
    {
        if ($this->writable === false) {
            throw new \RuntimeException(
                sprintf(
                    'Cannot update %s on %s, object is locked',
                    $slug,
                    $this->name
                )
            );
        }
        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException(
                sprintf(
                    '%s only accepts bool, null, string or numerics (SCALAR) values, saw %s',
                    __METHOD__,
                    is_object($value) ? get_class($value) : gettype($value)
                )
            );
        }
        $path = $this->expandSlug($slug);
        $cleanSlug = implode('.', $path);
        //get the name of the node we're updating
        $key = array_pop($path);
        try {
            $data = &$this->getTreeReference(
                $path,
                $this->data
            );
        } catch (\RuntimeException $e) {
            throw new \LogicException(
                sprintf(
                    'Invalid slug %s: %s',
                    $slug,
                    $e->getMessage()
                ),
                0,
                $e
            );
        }
        if (array_key_exists($key, $data) && !is_array($data[$key])) {
            $data[$key] = $value;
            $this->cached[$cleanSlug] = $value;
            $this->resetHash();
            $this->cached[self::CACHE_HASH_KEY] = $this->hash;
        } else {
            throw new \BadMethodCallException(
                sprintf(
                    '%s only updates SCALAR values, %s does not exist, or is a TREE',
                    __METHOD__,
                    $slug
                )
            );
        }
        return $this;
    }

    /**
     * Update a section of the data (TREE). Values will be overwritten, TREE's will be merged
     * as conservitively as possible
     *
     * @param string $slug
     * @param array $value
     * @return $this
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     * @throws \LogicException
     */
    public function updateTreeValue($slug, array $value)
    {
        if ($this->writable === false) {
            throw new \RuntimeException(
                sprintf(
                    'Cannot update %s on %s, object is locked',
                    $slug,
                    $this->name
                )
            );
        }
        if (!is_array($value)) {
            throw new \InvalidArgumentException(
                sprintf(
                    '%s only accepts TREE values, saw %s',
                    __METHOD__,
                    is_object($value) ? get_class($value) : gettype($value)
                )
            );
        }
        $path = $this->expandSlug($slug);
        $cleanSlug = implode('.', $path);
        //get the name of the node we're updating
        $key = array_pop($path);
        try {
            $data = &$this->getTreeReference(
                $path,
                $this->data
            );
            if (!array_key_exists($data[$key]) || !is_array($data[$key])) {
                throw new \RuntimeException(
                    sprintf(
                    	'%s does not evaluate to a TREE',
                        $key
                    )
                );
            }
        } catch (\RuntimeException $e) {
            throw new \LogicException(
                sprintf(
                    'Invalid slug %s: %s',
                    $slug,
                    $e->getMessage()
                ),
                0,
                $e
            );
        }
        $data[$key] = $this->porcelainMerge($data[$key], $value);
        $this->cleanCacheTree($cleanSlug);
        $this->cached[$cleanSlug] = $data[$key];
        $this->resetHash();
        $this->cached[self::CACHE_HASH_KEY] = $this->hash;
        return $this;
    }

    /**
     * Add SCALAR to data
     *
     * @param string $slug
     * @param mixed $value
     * @return $this
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    public function addScalarValue($slug, $value)
    {
        if ($this->writable === false) {
            throw new \RuntimeException(
                sprintf(
                    '%s is locked',
                    $this->name
                )
            );
        }
        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException(
                sprintf(
                    '%s can only add scalar values, saw %s',
                    __METHOD__,
                    is_object($value) ? get_class($value) : gettype($value)
                )
            );
        }
        //make sure current slug is empty, and all TREE's are valid:
        $test = $this->get($slug, null);
        if ($test === null) {
            throw new \RuntimeException(
                sprintf(
                    'Cannot add SCALAR at %s, slug already exists and resolves to %s',
                    $slug,
                    is_array($test) ? 'TREE' : 'SCALAR'
                )
            );
        }
        $path = $this->expandSlug($slug);
        $cleanSlug = implode('.', $path);
        $add = array_pop($path);
        $ref = &$this->getTreeReference($path, $this->data);
        $ref[$add] = $value;
        $this->cached[$cleanSlug] = $value;
        $this->resetHash();
        $this->cached[self::CACHE_HASH_KEY] = $this->hash;
        return $this;
    }

    /**
     * Turn nested data structured into flat array with slugs as keys
     *
     * @todo: Arg validation, reverse this function (load from flat)
     * @param array $data = null
     * @param string $prefix = null
     * @return array
     */
    public function getFlattened(array $data = null, $prefix = null)
    {
        if ($data === null) {
            $data = $this->data;
        }
        if ($prefix === null) {
            $prefix = $this->name . '.';
        }
        $flattened = array();
        foreach ($data as $key => $value) {
            if (!is_array($value)) {
                $flattened[$prefix . $key] = $value;
            } else {
                $flattened += $this->getFlattened($value, $prefix . $key . '.');
            }
        }
        return $flattened;
    }

    /**
     * Load data from a previously flattened slug object
     *
     * @param array $data
     * @return $this
     * @throws \RuntimeException
     */
    public function loadFlattened(array $data)
    {
        if ($this->writable === false) {
            throw new \RuntimeException(
                sprintf(
                    'Cannot load flattened data onto %s, data is locked',
                    $this->name
                )
            );
        }
        $this->data = array();
        $this->hash = null;
        $this->chached = $data;
        $this->name = '';
        foreach ($data as $slug => $value) {
            $path = explode('.', trim($slug));
            $name = array_shift($path);
            if (!$this->name) {
                $this->name = $name;
            }
            $this->data = $this->assignRecursive($this->data, $path, $value);
        }
        $this->resetHash();
        $this->cached[self::CACHE_HASH_KEY] = $this->hash;
        return $this;
    }

    /**
     * Merges 2 arrays, without changing TREE/SCALAR types
     * Never removes values from the original, recursively
     * TREE's and SCALAR's can be added, SCALARS can only be updated
     * 
     * @param array $original
     * @param array $new
     * @return array
     * @throws \LogicException
     */
    protected function porcelainMerge(array $original, array $new)
    {
        foreach ($new as $key => $value) {
            if (!array_key_exists($key, $original)) {
                $original[$key] = $value;
            } else if (!is_array($value)) {
                if (is_array($original[$key])) {
                    throw new \LogicException(
                        sprintf(
                            'Trying to replace TREE at %s with SCALAR (%s)',
                            $key,
                            gettype($value)
                        )
                    );
                }
                $original[$key] = $value;
            } else {
                if (!is_array($original[$key])) {
                    throw new \LogicException(
                        sprintf(
                            'Trying to replace SCALAR (%s) at %s with TREE',
                            gettype($original[$key]),
                            $key
                        )
                    );
                }
                $original[$key] = $this->porcelainMerge($original[$key], $value);
            }
        }
        return $original;
    }

    /**
     * Clear all the cache related to a certain slug (one that is a TREE)
     *
     * @param string $slug
     * @return $this
     */
    protected function cleanCacheTree($slug)
    {
        //sanitize slug
        $slug = impode('.', $this->expandSlug($slug));
        $keys = array_keys($this->cached);
        foreach ($keys as $k) {
            if (strstr($k, $slug) !== false) {
                unset($this->cached[$k]);
            }
        }
        return $this;
    }

    /**
     * Update hash property after changes to data
     * @return $this
     */
    protected function resetHash()
    {
        $this->hash = sha1(json_encode($this->data));
        return $this;
    }

    /**
     * Recursively finds a reference to a TREE
     * If the $path is invalid, or resolves to SCALAR, an exception is thrown
     *
     * CAUTION 	=> This method uses REFERENCES (both in arguments and return values)
     * 			=> Use at own risk, and only if you REALLY know what you're doing
     *
     * @param array $path
     * @param array &$data
     * @return array &$data
     * @throws \RuntimeException
     */
    protected function &getTreeReference(array $path, array &$data)
    {
        //path is not empty, key exists
        if ($path && isset($data[$path[0]])) {
            $key = array_shift($path);
            if (!is_array($data[$key])) {
                throw new \RuntimeException(
                    sprintf(
                        'Expected to find a TREE at %s, instead found SCALAR',
                        $key
                    )
                );
            }
            $data = &$this->getTreeReference($path, $data[$key]);
        } else if ($path) {
            throw new \RuntimeException(
                sprintf(
                    '%s not found',
                    $path[0]
                )
            );
        }
        return $data;
    }

    /**
     * Name setter
     *
     * @param string $name
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setName($name)
    {
        if (!is_string($name)) {
            throw new \InvalidArgumentException(
                sprintf(
                    '%s expects $name to be a string, %s given',
                    __METHOD__,
                    is_object($name) ? get_class($name) : gettype($name)
                )
            );
        }
        $cleanName = str_replace('.', '', trim($name));
        if ($cleanName !== $name) {
            throw new \InvalidArgumentException(
                'The name cannot contain whitespace chars, or separators (.)'
            );
        }
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Check if value of Slug-array has changed
     *
	 * @return bool
     */
    public function isDirty()
    {
        return ($this->hash !== sha1(json_encode($this->data)));
    }

    /**
     * Explodes + normalizes the slugs
     *
     * @param string $slug
     * @return array
     * @throws \InvalidArgumentException
     */
    protected function expandSlug($slug)
    {
        //trim whitespace (default) AND separators (.)
        $trimmedSlug = trim($slug, " \t\n\r\0\x0B.");
        $path = explode('.', $trimmedSlug);
        if ($path && $path[0] === $this->name) {
            array_shift($path);//remove name from slug
        }
        if (!$path) {
            //slug resolved to empty path => error
            throw new \InvalidArgumentException(
                sprintf(
                	'%s is not a valid slug',
                    $slug
                )
            );
        }
        return $path;
    }

    /**
     * Recursively assign values to target array
     *
     * @param array $target
     * @param array $path
     * @param mixed $value
     */
    protected function assignRecursive(array $target, array $path, $value)
    {
        if ($path) {
            $key = array_shift($path);
            //default to empty array, scalar will overwrite this if required
            if (!isset($target[$key])) {
                $target[$key] = array();
            }
            $target[$key] = $this->assignRecursive($target[$key], $path, $value);
            $returnVal = $target;
        } else {
            $returnVal = $value;
        }
        return $returnVal;
    }

    /**
     * Simple getter
     *
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }
}