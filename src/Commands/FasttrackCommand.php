<?php

namespace Asdfprah\Fasttrack\Commands;

use Illuminate\Console\GeneratorCommand;

abstract class FasttrackCommand extends GeneratorCommand
{
    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    abstract protected function getStub();

    /**
     * Get the code array to remplace
     * 
     * @return array
     */
    abstract protected function getCodeToReplace();

    /**
     * Build the class with the given name.
     *
     * @param  string  $name
     * @return string
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected function buildClass($name)
    {
        $stub = $this->files->get($this->getStub());

        $stub =  $this->replaceNamespace($stub, $name);

        $stub = $this->replaceCode($stub);

        return $this->replaceClass($stub, $name);
    }

    protected function replaceCode(&$stub)
    {
        $codeToReplace = $this->getCodeToReplace();
        foreach ($codeToReplace as $key => $code) {
            $search = "DummyCode:{$key}";
            $stub = str_replace($search, $code, $stub);
        }
        return $stub;
    }

    protected function replaceNamespace(&$stub, $name)
    {
        $searches = [
            ['DummyNamespace', 'DummyRootNamespace', 'NamespacedDummyUserModel'],
            ['{{ namespace }}', '{{ rootNamespace }}', '{{ namespacedUserModel }}'],
            ['{{namespace}}', '{{rootNamespace}}', '{{namespacedUserModel}}'],
        ];

        foreach ($searches as $search) {
            $stub = str_replace(
                $search,
                [$this->getNamespace($name), $this->rootNamespace(), $this->userProviderModel()],
                $stub
            );
        }

        return $stub;
    }
}
