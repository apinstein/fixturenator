Fixturenator
============

Fixturenator is a factory-style test data generator inspired by [factory_girl][1], thoughtfully ported to PHP.

Download / Installation
=======================

pear install apinstein.pearfarm.org/fixturenator

require 'fixturenator/Fixturenator.php';

[Source Code][2] available on GitHub.

Why Factories?
==============

There are many patterns for preparing test data for running tests against, but in my experience they all have problems in terms of maintenance or ease of creation.

The factory pattern provides a DRY way to specify basic valid data for objects, and then to re-use that specification as a prototype for making similar objects. 

The result is test data generation that doesn't break in 1000 places when you add a column to a model object, or change a validator. Fix the core factory definition and all your tests are working again!

Usage
=====

Each factory is identified by a name, and some default data for the created objects.

    // Static data for each instance        
    Fixturenator::define('User', array('username' => 'joe'));
    
    // Closure-passing syntax on 5.3
    Fixturenator::define('User', function($factory) {
      $factory->username = 'joe';
    });
    
    // Closure-passing syntax on 5.2
    Fixturenator::define('User', create_function('$factory', '
      $factory->username = 'joe';
    '});

It is recommended for you to have one factory per class that provides the minimal valid data for that class. Additional factories can be created via inheritance to easily create common scenario data.

Factory names must be unique.

**Using Factories**

There are four main ways to create data based on factories:

  * create - creates a new, unsaved instance
  * saved - same as create, but calls the defined save method ('save' by default)
  * stub - creates a MagicArray instance for easy stubbing functionality.
  * asArray - a php array (hash) with all data values set by the factory

Examples:

    // Returns an unsaved User instance
    $user = Fixturenator::create('User');
    
    // Customer the generated object
    $user = Fixturenator::create('User', array('password' => '1234'));

    // returns a "saved" User object, as if you called $user->save($dbCon)
    $user = Fixturenator::saved('User', array(), $dbCon);

**Dynamically Generated Attributes**

You might want to generate some data on the fly each time:

    // the 'return ...' gets turned into a lambda; on 5.3 you can pass true lambda    
    $user = Fixturenator::create('User', array('password' => 'return rand(1000,9999);'));
    
    // Generate one attribute based on others
    $user = Fixturenator::create('User', array('email' => 'return "{$o->username}@domain.com";'));

**Sequences**

Sequences are very useful for generating unique and predictable data:

    Fixturenator::createSequence('username', 'return "username{$n}";');
    Fixturenator::getSequence('username')->next();    // => username1
    Fixturenator::getSequence('username')->next();    // => username2

You can use these in your defines like so:

    Fixturenator::define(TestObject, array(
        'username' => new FixturenatorSequence('return "username{$n}";')
    ));
    Fixturenator::define(TestObject, array(
        'username' => 'return Fixturenator::getSequence('username')->next()'
    ));


Thanks
======

Thanks to the factory_girl team for all the research and time that went in to figuring out the factory pattern and the great factory_girl implementation!


  [1]: http://github.com/thoughtbot/factory_girl
  [2]: http://github.com/apinstein/fixturenator



