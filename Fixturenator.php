<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 syntax=php: */

class Fixturenator
{
    protected static $factories = array();
    protected static $sequences = array();

    // options are FixturenatorDefinition::OPT_*
    public static function define($name, $data = array(), $options = array())
    {
        // wire up parent
        if (isset($options[FixturenatorDefinition::OPT_PARENT]))
        {
            if (!isset(self::$factories[$options[FixturenatorDefinition::OPT_PARENT]])) throw new Exception("No FixturenatorDefinition for {$options[FixturenatorDefinition::OPT_PARENT]}.");
            $options[FixturenatorDefinition::OPT_PARENT] = self::$factories[$options[FixturenatorDefinition::OPT_PARENT]];
        }
        if (isset(self::$factories[$name])) throw new Exception("A factory named {$name} has already been defined.");
        self::$factories[$name] = new FixturenatorDefinition($name, $data, $options);
    }

    public static function createSequence($name, $sequenceProcessor = NULL)
    {
        if (isset(self::$sequences[$name])) throw new Exception("A sequence named {$name} has already been defined.");
        $s = new FixturenatorSequence($sequenceProcessor);
        self::$sequences[$name] = $s;
    }

    public static function getSequence($name)
    {
        if (!isset(self::$sequences[$name])) throw new Exception("A sequence named {$name} has not been defined.");
        return self::$sequences[$name];
    }
    
    public static function nextSequence($name)
    {
        return self::getSequence($name)->next();
    }

    public static function clearFactories()
    {
        self::$factories = array();
    }

    public static function clearSequences()
    {
        self::$sequences = array();
    }

    private static function requireFactoryNamed($name)
    {
        if (!isset(self::$factories[$name])) throw new Exception("A factory named {$name} has not been defined.");
        return self::$factories[$name];
    }

    public static function create($name, $data = array())
    {
        return self::requireFactoryNamed($name)->create($data);
    }
    
    /**
     * Helper function to call "saved" on the named FixturenatorDefinition.
     *
     * @param string THe FixturenatorDefinition name (must be previously defined).
     * @param array A hash of data to override the default data.
     * @param mixed Any additional parameters will be passed along as to the "save method".
     * @return object Whatever
     * @throws object Exception
     */
    public static function saved($name, $data = array())
    {
        $saveMethodArgs = array();
        if (func_num_args() > 2)
        {
            $passedArgs = func_get_args();
            $saveMethodArgs = array_slice($passedArgs, 2);
        }
        $allArgs = array_merge(array($data), $saveMethodArgs);
        return call_user_func_array(array(self::requireFactoryNamed($name), 'saved'), $allArgs);
    }

    public static function stub($name, $data = array())
    {
        return self::requireFactoryNamed($name)->stub($data);
    }

    // maybe call this "asArray"? "toArray"?
    public static function asArray($name, $data = array())
    {
        return self::requireFactoryNamed($name)->asArray($data);
    }
}

class FixturenatorDefinition
{
    protected $name;
    protected $class;
    protected $valueGenerators;
    protected $saveMethod;
    protected $saveMethodArgs;
    protected $parent;

    const OPT_CLASS             = 'class';
    const OPT_PARENT            = 'parent';
    const OPT_SAVE_METHOD       = 'saveMethod';
    const OPT_SAVE_METHOD_ARGS  = 'saveMethodArgs';

    public function __construct($name, $valueGenerators = array(), $options = array())
    {
        $this->name = $name;
        $this->valueGenerators = array();
        $this->parent = NULL;

        if (is_callable($valueGenerators))  // allow a passed block to dynamically add generators to FixturenatorDefinition
        {
            $valueGenerators($this);
        }
        else    // generate a "static" generator
        {
            foreach ($valueGenerators as $k => $v) {
                $this->prepareAttributeGenerator($k, $v);
            }
        }

        foreach (array_merge(array(
                                    self::OPT_CLASS             => $name,
                                    self::OPT_PARENT            => NULL,
                                    self::OPT_SAVE_METHOD       => 'save',
                                    self::OPT_SAVE_METHOD_ARGS  => NULL,
                               ), $options) as $k => $v) {
            $this->$k = $v;
        }
        if ($this->parent)
        {
            if (!($this->parent instanceof FixturenatorDefinition)) throw new Exception("OPT_PARENT must be a FixturenatorDefinition object.");
            $this->class = $this->parent->class;
        }
    }

    private function prepareAttributeGenerator($k, $v)
    {
        if ($v instanceof FixturenatorGenerator)
        {
            $this->valueGenerators[$k] = $v;
        }
        else    // static data
        {
            $this->valueGenerators[$k] = new FixturenatorGenerator($v);
        }
    }

    /**
     * Magic-ness for when new'ing a FixturenatorDefinition with a block, so that you can:
     *
     * <code>
     * $f->attr = 'foo';
     * $f->attr = new FixturenatorGenerator(function($o) { return rand(1000,9999); } );
     * $f->attr = new FixturenatorSequence;
     * </code>
     *
     * inside the block.
     *
     * Internally this just calls "prepareAttributeGenerator" with the info.
     *
     * @param string Key
     * @param mixed Value
     */
    public function __set($k, $v)
    {
        $this->prepareAttributeGenerator($k, $v);
    }

    public function resolveData($newObj, $overrideData = array())
    {
        // resolve inheritance
        if ($this->parent)
        {
            $this->parent->resolveData($newObj);
        }

        // wire up data for this factory
        $allKeys = array_merge(array_keys($overrideData), array_keys($this->valueGenerators));
        foreach ($allKeys as $k) {
            $value = NULL;
            if (isset($overrideData[$k]))
            {
                $value = $overrideData[$k];
                if (is_callable($value))
                {
                    $value = call_user_func($value, $newObj);
                }
                else if (FixturenatorDefinition::__detectLambda($value, '$o'))
                {
                    $lambda = create_function('$o', $value);
                    $value = $lambda($newObj);
                }
            }
            else
            {
                $generator = $this->valueGenerators[$k];
                if ($generator instanceof FixturenatorGenerator)
                {
                    $value = $generator->next($newObj);
                }
                else if (is_callable($generator))
                {
                    $value = $generator($newObj);
                }
            }
            if (is_callable(array($newObj, 'setValueForKey')))
            {
                $newObj->setValueForKey($value, $k);
            }
            else
            {
                // emulated setValueForKey
                $performed = false;

                // try calling setter
                $setMethod = "set" . ucfirst($k);
                if (method_exists($newObj, $setMethod))
                {
                    $newObj->$setMethod($value);
                    $performed = true;
                }

                if (!$performed)
                {  
                    // try accesing instance var directly
                    $vars = get_object_vars($newObj);
                    if (array_key_exists($k, $vars))
                    {  
                        $newObj->$k = $value;
                        $performed = true;
                    }
                }

                if (!$performed) throw new Exception("Couldn't manage to set '$k' for object '" . get_class($newObj) . "'.");
            }
        }
    }
    
    public function create($overrideData = array())
    {
        $newObj = new $this->class;
        $this->resolveData($newObj, $overrideData);
        return $newObj;
    }

    /**
     * Return a saved object.
     *
     * @param array A hash of data to override the default data.
     * @param mixed Any additional parameters will be passed along as to the "save method".
     * @return object Whatever An instance of the new object.
     * @throws object Exception If the save method is not callable.
     */
    public function saved($overrideData = array())
    {
        $saveMethodArgs = $this->saveMethodArgs;
        if (func_num_args() > 1)
        {
            $passedArgs = func_get_args();
            $saveMethodArgs = array_slice($passedArgs, 1);
        }

        $newObj = $this->create($overrideData);
        call_user_func_array(array($newObj, $this->saveMethod), $saveMethodArgs);
        return $newObj;
    }

    public function stub($overrideData = array())
    {
        $newObj = new MagicArray;
        $this->resolveData($newObj, $overrideData);
        return $newObj;
    }

    public function asArray($overrideData = array())
    {
        return $this->stub($overrideData)->getArrayCopy();
    }

    public static function __detectLambda($possibleLambda, $expectedVarName)
    {
        if (strncasecmp('return', $possibleLambda, 6) === 0) return true;
        if (strpos($possibleLambda, "\${$expectedVarName}") !== false) return true;
        return false;
    }
}

class FixturenatorGenerator
{
    public $generator;

    public function __construct($value)
    {
        if (is_string($value) && FixturenatorDefinition::__detectLambda($value, '$o'))
        {
            $this->generator = create_function('$o', $value);
        }
        else
        {
            $this->generator = $value;
        }
    }
    public function next($obj = NULL)
    {
        if (is_callable($this->generator))
        {
            $genF = $this->generator;
            $result = call_user_func($genF, $obj);
            unset($genF);
            return $result;
        }
        else
        {
            return $this->generator;  // static values
        }
    }
}

class FixturenatorSequence extends FixturenatorGenerator
{
    protected $val;
    protected $sequenceProcessor;

    public function __construct($sequenceProcessor = NULL)
    {
        $this->val = 1;
        $this->sequenceProcessor = NULL;

        if (is_callable($sequenceProcessor))
        {
            $this->sequenceProcessor = $sequenceProcessor;
        }
        else if (is_string($sequenceProcessor) && FixturenatorDefinition::__detectLambda($sequenceProcessor, '$n') !== false)
        {
            $this->sequenceProcessor = create_function('$n', $sequenceProcessor);
        }
        else if ($sequenceProcessor !== NULL)
        {
            throw new Exception("Someting besides a function was passed.");
        }

        parent::__construct(array($this, 'nextVal'));
    }

    public function nextVal()
    {
        $nextVal = $this->val++;
        if ($this->sequenceProcessor)
        {
            $sequenceProcessorF = $this->sequenceProcessor;
            $nextVal = call_user_func($sequenceProcessorF, $nextVal);
            unset($sequenceProcessorF);
        }
        return $nextVal;
    }
}

class MagicArray extends ArrayObject
{
    public function setValueForKey($v, $k)
    {
        $this->offsetSet($k, $v);
    }
    public function __get($k)
    {
        return $this->offsetGet($k);
    }
}
