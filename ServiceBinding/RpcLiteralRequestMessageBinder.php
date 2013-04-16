<?php
/*
 * This file is part of the BeSimpleSoapBundle.
 *
 * (c) Christian Kerl <christian-kerl@web.de>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace BeSimple\SoapBundle\ServiceBinding;

use BeSimple\SoapBundle\Reflection\ClassReflection;
use BeSimple\SoapBundle\Reflection\ReflectionException;
use BeSimple\SoapBundle\ServiceDefinition\ComplexType;
use BeSimple\SoapBundle\ServiceDefinition\Method;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Zend\Soap\Wsdl;

/**
 * @author Christian Kerl <christian-kerl@web.de>
 * @author Francis Besset <francis.besset@gmail.com>
 */
class RpcLiteralRequestMessageBinder implements MessageBinderInterface
{
    private $messageRefs = array();
    /**
     * @var ComplexType[]
     */
    private $definitionComplexTypes;
    private $logger;

    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }

    public function processMessage(Method $messageDefinition, $message, array $definitionComplexTypes = array())
    {
        $this->messageRefs = array();
        $this->definitionComplexTypes = $definitionComplexTypes;

        $result = array();
        $i      = 0;

        foreach ($messageDefinition->getArguments() as $argument) {
            if (isset($message[$i])) {
                $result[$argument->getName()] = $this->processType($argument->getType()->getPhpType(), $message[$i]);
            }

            $i++;
        }

        return $result;
    }

    protected function processType($phpType, $message)
    {
        if (
            is_object($message)
            && $message instanceof $phpType
            && isset($this->definitionComplexTypes[get_class($message)])
        ) {
            $phpType = get_class($message);
        }
        $isArray = false;

        if (preg_match('/^([^\[]+)\[\]$/', $phpType, $match)) {
            $isArray = true;
            $phpType = $match[1];
        }

        // @TODO Fix array reference
        if (isset($this->definitionComplexTypes[$phpType])) {
            if ($isArray) {
                $array = array();
                if (isset($message->item)) {
                    foreach ($message->item as $complexType) {
                        $array[] = $this->checkComplexType($phpType, $complexType);
                    }
                }

                $message = $array;
            } else {
                $message = $this->checkComplexType($phpType, $message);
            }
        } elseif ($isArray) {
            if (isset($message->item)) {
                $message = $message->item;
            } else {
                $message = array();
            }
        }

        return $message;
    }

    protected function checkComplexType($phpType, $message)
    {
        $hash = spl_object_hash($message);
        if (isset($this->messageRefs[$hash])) {
            return $this->messageRefs[$hash];
        }

        $className = get_class($message);
        $result = new $className();
        $this->messageRefs[$hash] = $result;

        $reflection = new ClassReflection($className);
        foreach ($this->definitionComplexTypes[$phpType] as $type) {
            /** @var ComplexType $type */
            if (!$type->isReadonly()) {
                try {
                    $value = $reflection->getByMethod($message, $type->getName());
                } catch (ReflectionException $exception) {
                    if ($this->logger) {
                        $this->logger->notice(
                            'Getter not found for property, using reflection',
                            array($className, $type->getName())
                        );
                    }
                    $value = $reflection->getByReflection($message, $type->getName());
                }

                if (null !== $value) {
                    $value = $this->processType($type->getValue(), $value);
                    try {
                        $reflection->setByMethod($result, $type->getName(), $value);
                    } catch (ReflectionException $exception) {
                        if ($this->logger) {
                            $this->logger->notice(
                                'Setter not found for property, using reflection',
                                array($className, $type->getName())
                            );
                        }
                        $reflection->setByReflection($result, $type->getName(), $value);
                    }
                }

                if (!$type->isNillable() && null === $value) {
                    throw new \SoapFault(
                        'SOAP_ERROR_COMPLEX_TYPE',
                        sprintf('"%s:%s" cannot be null.', ucfirst($phpType), $type->getName())
                    );
                }
            }
        }

        return $result;
    }
}
