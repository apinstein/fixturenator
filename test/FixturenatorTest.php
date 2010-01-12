<?php

require dirname(__FILE__).'/../Fixturenator.php';

define('TestObject', 'TestObject');
class TestObject
{
    public $username;
    public $password;
    public $email;
}

class FixturenatorTest extends PHPUnit_Framework_TestCase
{
    public function setup()
    {
        Fixturenator::clearFactories();
    }

    public function tearDown()
    {
    }

    public function testStaticGenerator()
    {
        Fixturenator::define(TestObject, array(
            'username' => 1,
        ));
        $newObj = Fixturenator::build(TestObject);
        $this->assertEquals($newObj->username, 1);
    }

    public function testDefineFactoryWithLambda()
    {
        Fixturenator::define(TestObject, create_function('$f', '
            $f->username = 1;
            $f->password = new WFSequenceGenerator;
        '));

        $newObj = Fixturenator::build(TestObject);
        $this->assertEquals($newObj->username, 1);
        $this->assertEquals($newObj->password, 1);
    }

    public function testDynamicGenerator()
    {
        Fixturenator::define(TestObject, array(
            'username'  => 'joe',
            'email'     => new WFGenerator(create_function('$o', 'return "{$o->username}@email.com";')),
            'password'  => new WFGenerator('return "pass_for_{$o->username}";'),
        ));

        $newObj = Fixturenator::build(TestObject);
        $this->assertEquals($newObj->email, 'joe@email.com');
        $this->assertEquals($newObj->password, 'pass_for_joe');
    }

    public function testSequenceGenerator()
    {
        Fixturenator::define(TestObject, array(
            'username' => new WFSequenceGenerator,
        ));

        $newObj = Fixturenator::build(TestObject);
        $this->assertEquals($newObj->username, 1);

        $newObj = Fixturenator::build(TestObject);
        $this->assertEquals($newObj->username, 2);
    }

    public function testSequenceGeneratorWithCallback()
    {
        Fixturenator::define(TestObject, array(
            'username' => new WFSequenceGenerator(create_function('$n', 'return "username{$n}";')),
            'password' => new WFSequenceGenerator('return "pass_for_{$n}";'),
        ));

        $newObj = Fixturenator::build(TestObject);
        $this->assertEquals($newObj->username, 'username1');
        $this->assertEquals($newObj->password, 'pass_for_1');

        $newObj = Fixturenator::build(TestObject);
        $this->assertEquals($newObj->username, 'username2');
        $this->assertEquals($newObj->password, 'pass_for_2');
    }

    // FixturenatorDefinition::OPT_*
    public function testUseOptClassToHaveFactoryNameDifferentFromClassProduced()
    {
        Fixturenator::define('foo', array(
            'username' => 1,
        ), array(FixturenatorDefinition::OPT_CLASS => TestObject));
        $newObj = Fixturenator::build('foo');
        $this->assertTrue($newObj instanceof TestObject);
    }

}
