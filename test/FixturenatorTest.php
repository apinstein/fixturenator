<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 syntax=php: */

require dirname(__FILE__).'/../Fixturenator.php';

define('TestObject', 'TestObject');
class TestObject
{
    public $username;
    public $password;
    public $email;

    public $saved = false;

    public function save()
    {
        $this->saved = func_get_args();
    }
}

class FixturenatorTest extends PHPUnit_Framework_TestCase
{
    public function setup()
    {
        Fixturenator::clearFactories();
        Fixturenator::clearSequences();
    }

    // fixture creation modalities
    public function testCreate()
    {
        $expected = new TestObject;
        $expected->username = '1';

        Fixturenator::define(TestObject, array('username' => '1'));
        $this->assertEquals($expected, Fixturenator::create(TestObject));
    }

    public function testSaved()
    {
        $saveArgs = array(1,2);

        Fixturenator::define(TestObject, array('username' => '1'), array(
            FixturenatorDefinition::OPT_SAVE_METHOD => 'save',
            FixturenatorDefinition::OPT_SAVE_METHOD_ARGS => $saveArgs
        ));

        $newObject = Fixturenator::saved(TestObject);
        $this->assertEquals($saveArgs, $newObject->saved);
    }

    public function testStub()
    {
        Fixturenator::define(TestObject, array('username' => '1'));
        $this->assertEquals(1, Fixturenator::stub(TestObject)->username);
    }

    public function testAsArray()
    {
        Fixturenator::define(TestObject, array('username' => '1'));
        $this->assertEquals(array('username' => '1'), Fixturenator::asArray(TestObject));
    }

    // supporting stuff
    public function testStaticGenerator()
    {
        Fixturenator::define(TestObject, array(
            'username' => 1,
        ));
        $newObj = Fixturenator::create(TestObject);
        $this->assertEquals($newObj->username, 1);
    }

    public function testDefineFactoryWithLambda()
    {
        Fixturenator::define(TestObject, create_function('$f', '
            $f->username = 1;
            $f->password = new FixturenatorSequence;
        '));

        $newObj = Fixturenator::create(TestObject);
        $this->assertEquals($newObj->username, 1);
        $this->assertEquals($newObj->password, 1);
    }

    public function testDynamicGenerator()
    {
        Fixturenator::define(TestObject, array(
            'username'  => 'joe',
            'email'     => new FixturenatorGenerator(create_function('$o', 'return "{$o->username}@email.com";')),
            'password'  => new FixturenatorGenerator('return "pass_for_{$o->username}";'),
        ));

        $newObj = Fixturenator::create(TestObject);
        $this->assertEquals($newObj->email, 'joe@email.com');
        $this->assertEquals($newObj->password, 'pass_for_joe');
    }

    /**
     * @dataProvider magicLambdaStylesTestData
     */
    public function testMagicLambdaStyles($expr, $expectedResult)
    {
        Fixturenator::define(TestObject, array(
            'username'  => 'joe',
            'password'  => $expr,
        ));

        $newObj = Fixturenator::create(TestObject);
        $this->assertEquals($newObj->password, $expectedResult);
    }
    public function magicLambdaStylesTestData()
    {
        return array(
            // **not** generator
            array('pass_for_joe', 'pass_for_joe'),
            array('$XXXpass_for_joe', '$XXXpass_for_joe'),
            // generators
            array(new FixturenatorGenerator('return "pass_for_{$o->username}";'), 'pass_for_joe'),
            array(create_function('$o', 'return "pass_for_{$o->username}";'), 'pass_for_joe'),
            array('return "pass_for_{$o->username}";', 'pass_for_joe'),
            array('return "pass_for_joe";', 'pass_for_joe'),
            // sequencegenerators
            array(new FixturenatorSequence('return "pass_for_joe";'), 'pass_for_joe'),
            array(new FixturenatorSequence(create_function('', 'return "pass_for_joe";')), 'pass_for_joe'),
            array(new FixturenatorSequence('return "pass_for_joe_{$n}";'), 'pass_for_joe_1'),
            array(new FixturenatorSequence(create_function('$n', 'return "pass_for_joe_{$n}";')), 'pass_for_joe_1'),
        );
    }

    public function testSequenceGeneratorRequiresValidCallback()
    {
        $this->setExpectedException('Exception');
        new FixturenatorSequence('$XXXblah";');
    }

    public function testSequenceGenerator()
    {
        Fixturenator::define(TestObject, array(
            'username' => new FixturenatorSequence,
        ));

        $newObj = Fixturenator::create(TestObject);
        $this->assertEquals($newObj->username, 1);

        $newObj = Fixturenator::create(TestObject);
        $this->assertEquals($newObj->username, 2);
    }

    public function testGlobalSequences()
    {
        Fixturenator::createSequence('seq1');
        Fixturenator::createSequence('seq2');

        $this->assertEquals(Fixturenator::getSequence('seq1')->next(), 1);
        $this->assertEquals(Fixturenator::getSequence('seq1')->next(), 2);
        $this->assertEquals(Fixturenator::getSequence('seq1')->next(), 3);
        $this->assertEquals(Fixturenator::getSequence('seq2')->next(), 1);
        $this->assertEquals(Fixturenator::getSequence('seq2')->next(), 2);
        $this->assertEquals(Fixturenator::getSequence('seq2')->next(), 3);
    }

    public function testSequenceGeneratorWithCallback()
    {
        Fixturenator::define(TestObject, array(
            'username' => new FixturenatorSequence(create_function('$n', 'return "username{$n}";')),
            'password' => new FixturenatorSequence('return "pass_for_{$n}";'),
        ));

        $newObj = Fixturenator::create(TestObject);
        $this->assertEquals($newObj->username, 'username1');
        $this->assertEquals($newObj->password, 'pass_for_1');

        $newObj = Fixturenator::create(TestObject);
        $this->assertEquals($newObj->username, 'username2');
        $this->assertEquals($newObj->password, 'pass_for_2');
    }

    public function testInheritance()
    {
        Fixturenator::define(TestObject, array(
            'username' => 'parent',
            'password' => 'parentOnly',
        ));
        Fixturenator::define('TestObjectChild', array(
            'username' => 'child',
            'email'    => 'childOnly',
        ), array(FixturenatorDefinition::OPT_PARENT => TestObject));
        Fixturenator::define('TestObjectGrandchild', array(
            'username' => 'grandchild',
        ), array(FixturenatorDefinition::OPT_PARENT => 'TestObjectChild'));

        $newObj = Fixturenator::create('TestObjectChild');
        $this->assertEquals('child', $newObj->username);
        $this->assertEquals('parentOnly', $newObj->password);
        $this->assertEquals('childOnly', $newObj->email);

        $newObj = Fixturenator::create('TestObjectGrandchild');
        $this->assertEquals('grandchild', $newObj->username);
        $this->assertEquals('parentOnly', $newObj->password);
        $this->assertEquals('childOnly', $newObj->email);
    }

    // FixturenatorDefinition::OPT_*
    public function testUseOptClassToHaveFactoryNameDifferentFromClassProduced()
    {
        Fixturenator::define('foo', array(
            'username' => 1,
        ), array(FixturenatorDefinition::OPT_CLASS => TestObject));
        $newObj = Fixturenator::create('foo');
        $this->assertTrue($newObj instanceof TestObject);
    }
}
