<?php

namespace Asdfprah\Fasttrack\Commands;

use Asdfprah\Fasttrack\SchemaExporter;
use Illuminate\Console\Command;

class SchemaCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fasttrack:schema {--path=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export a JSON map of the app models, their attributes and their relations';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $schema = SchemaExporter::build();

        $path = $this->option('path') ?: config('fasttrack.schema_path');

        file_put_contents($path, json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info("Schema written to {$path}");

        return 0;
    }
}
