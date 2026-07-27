<?php

namespace App\Core;

use ReflectionClass;
use ReflectionNamedType;

class Container
{

    private array $bindings = [];

    private array $instances = [];

    private array $shared = [];



    public function bind(
        string $abstract,
        callable $factory
    ): void {

        $this->bindings[$abstract] = $factory;
    }


    public function singleton(
        string $abstract,
        callable $factory
    ): void {

        $this->bindings[$abstract] = function ($container) use ($factory, $abstract) {

            if (!isset($this->instances[$abstract])) {

                $this->instances[$abstract] = $factory($container);
            }

            return $this->instances[$abstract];
        };
    }



    public function get(
        string $abstract
    ) {


        if (
            isset($this->instances[$abstract])
        ) {

            return $this->instances[$abstract];
        }



        if (
            isset($this->bindings[$abstract])
        ) {


            $object = ($this->bindings[$abstract])(
                $this
            );


            if (
                isset($this->shared[$abstract])
            ) {

                $this->instances[$abstract] = $object;
            }


            return $object;
        }



        if (
            !class_exists($abstract)
        ) {

            throw new \Exception(
                "Clase no encontrada: {$abstract}"
            );
        }



        $reflection = new ReflectionClass(
            $abstract
        );



        if (
            !$reflection->isInstantiable()
        ) {

            throw new \Exception(
                "Clase no instanciable: {$abstract}"
            );
        }



        $constructor =
            $reflection->getConstructor();



        if (!$constructor) {

            return new $abstract();
        }



        $dependencies = [];



        foreach (
            $constructor->getParameters()
            as $parameter
        ) {


            $type =
                $parameter->getType();



            if (
                !$type instanceof ReflectionNamedType
                ||
                $type->isBuiltin()
            ) {


                if (
                    $parameter->isDefaultValueAvailable()
                ) {

                    $dependencies[] =
                        $parameter->getDefaultValue();

                    continue;
                }


                throw new \Exception(
                    "No se puede resolver {$parameter->getName()} en {$abstract}"
                );
            }



            $dependencies[] =
                $this->get(
                    $type->getName()
                );
        }



        return $reflection->newInstanceArgs(
            $dependencies
        );
    }



    public function make(
        string $abstract
    ) {

        return $this->get($abstract);
    }



    public function has(
        string $abstract
    ): bool {

        return isset($this->bindings[$abstract])
            || class_exists($abstract);
    }



    public function forget(
        string $abstract
    ): void {

        unset(
            $this->instances[$abstract]
        );
    }
}
