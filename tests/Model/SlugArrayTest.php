<?php
use Slugger\Model\SlugArray;
use Slugger\Model\Slugger\Model;

class SlugArrayTest extends PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider constructorProvider
     */
    public function testConstructorValid($name, $data, $writable, $badName)
    {
        $obj = new SlugArray($name, $data, $writable);
        foreach ($data as $k => $val) {
            if (!is_array($val)) {
                $this->assertEquals($val, $obj->get($k));
            } else {
                $slug = $k . '.';
                foreach ($val as $sub => $val) {
                    $this->assertEquals($val, $obj->get($slug.$sub));
                }
            }
        }
        if ($writable) {
            $val = $obj->get('foo');
            $obj->updateScalarValue('foo', 'new value');
            $this->assertNotEquals($val, $obj->get('foo'));
        } else {
            try {
                $obj->updateScalarValue('foo', '123');
                $e = null;
            } catch (RuntimeException $e) {
                $this->assertInstanceOf('RuntimeException', $e);
            }
            $this->assertNotNull($e);
        }
        $this->setExpectedException('InvalidArgumentException');
        $obj->setName($badName);
    }

    /**
     * @dataProvider flattenProvider
     * @param array $data
     * @param string $expectedName
     */
    public function testFlattenMethods(array $data, $expectedName)
    {
        $slugger = new SlugArray();
        $slugger->loadFlattened($data);
        foreach ($data as $slug => $value)
        {
            $this->assertEquals($value, $slugger->get($slug, $value === null ? false : null));
        }
        $this->assertEquals($data, $slugger->getFlattened());
        $this->assertEquals($expectedName, $slugger->getName());
    }

    /**
     * @return array
     */
    public function constructorProvider()
    {
        $data = array(
            'foo'	    => 'bar',
            'scalar'	=> 2,
            'tree'		=> array(
                'with'	=> 'scalar values'
            )
        );
        return array(
            array(
                'name'	    => 'test',
                'data'	    => $data,
                'writable'	=> true,
                'badName'	=> 123,
            ),
            array(
                'name'	    => 'readOnly',
                'data'	    => $data,
                'writable'	=> false,
                'badName'	=> new DateTime(),
            ),
            array(
                'name'	    => 'keyName',
                'data'	    => array('keyName' => $data),
                'writable'	=> true,
                'badName'	=> 'name.with.separators',
            )
        );
    }

    /**
     * @return array
     */
    public function flattenProvider()
    {
        $data = array(
            'test.foo.scalar'       => 'val1',
            'test.bar.tree.first'   => 'first tree value',
            'test.bar.tree.second'  => 'second tree value',
        );
        return array(
            array(
                $data,
                'test',
            )
        );
    }
}