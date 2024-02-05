<?php

namespace Asdfprah\Fasttrack\Commands;

use Asdfprah\Fasttrack\Commands\FasttrackCommand;
use Asdfprah\Fasttrack\Describer;
use Asdfprah\Fasttrack\FormRequest\ValidationGenerator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;


class MakeRequestCommand extends FasttrackCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fasttrack:request {name} {model} {--force}';

    /**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
    protected $name = 'fasttrack:request';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates a new Form Request';


    /**
	 * The type of class being generated.
	 *
	 * @var string
	 */
    protected $type = 'Request';

    protected function getCodeToReplace(){
        $model = $this->getModel();
        return [
            "rules" => $this->getRulesCode( $model ),
        ];
    }


    protected function getRulesCode( $model ){
        $description = Describer::describe( new $model );
        $rules = "";
        $count = 0;
        foreach ($description as $column => $columnDescription) {
            if self::shouldBeIgnored($model->getTable(), $column) { continue; }
            if( $columnDescription["isPrimaryKey"] ){ continue; }

            $lineBreak="\r\n            "; 

            if( $count == 0){ $lineBreak = ""; }

            $columnRules = (new ValidationGenerator)->generate($columnDescription);
            $rules = $rules . $lineBreak ."'{$column}' => '{$columnRules}',";
            $count ++;
        }
        return $rules;
    }

	/**
	 * Get the stub file for the generator.
	 *
	 * @return string
	 */
	protected function getStub()
	{
		return  dirname( dirname(__FILE__) ).'/Stubs/Request.stub';
	}

	/**
	 * Get the default namespace for the class.
	 *
	 * @param  string  $rootNamespace
	 * @return string
	 */
	protected function getDefaultNamespace($rootNamespace)
	{
		return $rootNamespace . '\Http\Requests';
	}

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['model', InputArgument::REQUIRED, 'The model to generate request.'],
            ['name', InputArgument::REQUIRED, 'The name to generate request.'],
        ];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['force', null, InputOption::VALUE_NONE, 'Create the abstract class even if it already exists'],
        ];
    }


    protected function getModel(){
        $modelName = $this->argument("model");
        return "App\\Models\\".$modelName;   
    }

    /**
     * Check if a given column is ignored by user config
     * 
     * @param string $table database table name
     * @param string $column database table column
     * @return boolean
     */
    protected function shouldBeIgnored( string $table, string $column){
        return in_array(config('fasttrack.ignored_columns'), "*.$column") || in_array(config('fasttrack.ignored_columns'), "$table.$column") 
    }
}