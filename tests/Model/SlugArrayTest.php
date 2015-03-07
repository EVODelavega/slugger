<?php
use Slugger\Model\SlugArray;

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
}