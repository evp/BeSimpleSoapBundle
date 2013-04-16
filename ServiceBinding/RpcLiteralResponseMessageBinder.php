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

use Doctrine\ORM\Proxy\Proxy;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Zend\Soap\Wsdl;

/**
 * @author Christian Kerl <christian-kerl@web.de>
 * @author Francis Besset <francis.besset@gmail.com>
 */
class RpcLiteralResponseMessageBinder implements MessageBinderInterface
{
    private $messageRefs = array();
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

        return $this->processType($messageDefinition->getReturn()->getPhpType(), $message);
    }

    private function processType($phpType, $message)
    {
        $isArray = false;
        if (preg_match('/^([^\[]+)\[\]$/', $phpType, $match)) {
            $isArray = true;
            $phpType = $match[1];
        }

        if (isset($this->definitionComplexTypes[$phpType])) {
            if ($isArray) {
                $array = array();

                foreach ($message as $complexType) {
                    $array[] = $this->checkComplexType($phpType, $complexType);
                }

                $message = $array;
            } else {
                $message = $this->checkComplexType($phpType, $message);
            }
        }

        return $message;
    }

    private function checkComplexType($phpType, $message)
    {
        $hash = spl_object_hash($message);
        if (isset($this->messageRefs[$hash])) {
            return $this->messageRefs[$hash];
        }

        if (
            is_object($message)
            && $message instanceof $phpType
            && isset($this->definitionComplexTypes[get_class($message)])
        ) {
            $phpType = get_class($message);
        }

        $this->messageRefs[$hash] = $message;

        if (!$message instanceof $phpType) {
            throw new \InvalidArgumentException(sprintf('The instance class must be "%s", "%s" given.', get_class($message), $phpType));
        }
        if ($message instanceof Proxy && !$message->__isInitialized()) {
            $message->__load();
        }

        $className = get_class($message);
        $reflection = new ClassReflection($className);
        foreach ($this->definitionComplexTypes[$phpType] as $type) {
            /** @var ComplexType $type */
            try {
                $value = $reflection->getByMethod($message, $type->getName());
            } catch (ReflectionException $exception) {
                $this->logger->notice(
                    'Getter not found for property, using reflection',
                    array($className, $type->getName())
                );
                $value = $reflection->getByReflection($message, $type->getName());
            }

            if (null !== $value) {
                $value = $this->processType($type->getValue(), $value);
                $reflection->setByReflection($message, $type->getName(), $value);
            }

            if (!$type->isNillable() && null === $value) {
                $exception = new \InvalidArgumentException(sprintf('"%s::%s" cannot be null.', $phpType, $type->getName()));
                if ($this->logger === null) {
                    throw $exception;
                } else {
                    $this->logger->warn('SOAP result passed as null, even if type declaration does not allow this');
                    $this->logger->warn($exception, array($message, $type));
                }
            }
        }

        return $message;
    }
}
