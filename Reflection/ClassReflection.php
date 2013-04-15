<?php


namespace BeSimple\SoapBundle\Reflection;

/**
 * Reflection
 *
 * @author Marius Balčytis <m.balcytis@evp.lt>
 */
class ClassReflection
{
    protected $reflectionClass;
    protected $reflectionProperties = array();
    protected $className;
    protected $forceReflection;

    public function __construct($className)
    {
        $this->className = $className;
    }

    public function getByMethod($object, $property)
    {
        $getter = array($object, 'get' . ucfirst($property));
        if (is_callable($getter)) {
            return call_user_func($getter);
        } else {
            $getter = array($object, 'is' . ucfirst($property));
            if (is_callable($getter)) {
                return call_user_func($getter);
            } else {
                throw new ReflectionException('Getter not found for ' . $this->className . ' property ' . $property);
            }
        }
    }

    public function getByReflection($object, $property)
    {
        $p = $this->getReflectionProperty($property);
        if ($p->isPublic()) {
            return $object->{$property};
        } else {
            return $p->getValue($object);
        }
    }

    public function setByMethod($object, $property, $value)
    {
        $setter = array($object, 'set' . ucfirst($property));
        if (is_callable($setter)) {
            call_user_func($setter, $value);
        } else {
            $singular = rtrim($property, 's');
            if (substr($singular, -2) == 'ie') {
                $singular = substr($singular, 0, -2) . 'y';
            }
            $adder = array($object, 'add' . ucfirst($singular));
            if (is_callable($adder) && (is_array($value) || $value instanceof \Traversable)) {
                foreach ($value as $item) {
                    call_user_func($adder, $item);
                }
            } else {
                throw new ReflectionException('Setter not found for ' . $this->className . ' property ' . $property);
            }
        }
    }

    public function setByReflection($object, $property, $value)
    {
        $p = $this->getReflectionProperty($property);
        if ($p->isPublic()) {
            $object->{$property} = $value;
        } else {
            $p->setValue($object, $value);
        }
    }

    protected function getReflectionClass()
    {
        if ($this->reflectionClass === null) {
            $this->reflectionClass = new \ReflectionClass($this->className);
        }
        return $this->reflectionClass;
    }

    /**
     * @param $property
     *
     * @return \ReflectionProperty
     */
    protected function getReflectionProperty($property)
    {
        if (!isset($this->reflectionProperties[$property])) {
            $r = $this->getReflectionClass();
            $p = $r->getProperty($property);
            if (!$p->isPublic()) {
                $p->setAccessible(true);
            }
            $this->reflectionProperties[$property] = $p;
        }
        return $this->reflectionProperties[$property];
    }
}