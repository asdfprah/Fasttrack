<?php

use Vifrost\Laravel\Commands\MakeAPICommand;

function callProtected(object $object, string $method, array $args = [])
{
    $reflection = new ReflectionMethod($object, $method);
    $reflection->setAccessible(true);

    return $reflection->invokeArgs($object, $args);
}

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/vifrost-make-api-test-' . uniqid();
    mkdir($this->tempDir, recursive: true);
    $this->command = app(MakeAPICommand::class);
});

afterEach(function () {
    array_map('unlink', glob($this->tempDir . '/*'));
    @rmdir($this->tempDir);
});

it('creates routes/api.php with a PHP opening tag when the file does not exist yet', function () {
    $path = $this->tempDir . '/api.php';

    callProtected($this->command, 'ensureApiRoutesFile', [$path]);

    expect(file_exists($path))->toBeTrue();
    expect(file_get_contents($path))->toStartWith('<?php');
});

it('leaves an existing routes/api.php untouched', function () {
    $path = $this->tempDir . '/api.php';
    file_put_contents($path, "<?php\r\n\r\nRoute::get('existing', fn () => null);");

    callProtected($this->command, 'ensureApiRoutesFile', [$path]);

    expect(file_get_contents($path))->toContain("Route::get('existing'");
});

it('registers the api routing entry in bootstrap/app.php when missing', function () {
    // routes/api.php existing isn't enough on its own — a fresh Laravel 11+
    // bootstrap/app.php has no `api:` entry in withRouting() either, so every
    // route 404s until it's registered.
    $path = $this->tempDir . '/app.php';
    file_put_contents($path, <<<'PHP'
    <?php

    return Application::configure(basePath: dirname(__DIR__))
        ->withRouting(
            web: __DIR__.'/../routes/web.php',
            commands: __DIR__.'/../routes/console.php',
            health: '/up',
        )
        ->create();
    PHP);

    callProtected($this->command, 'ensureApiRoutingRegistered', [$path]);

    expect(file_get_contents($path))->toContain("api: __DIR__.'/../routes/api.php',");
});

it('does not duplicate the api routing entry if already registered', function () {
    $path = $this->tempDir . '/app.php';
    $original = <<<'PHP'
    <?php

    return Application::configure(basePath: dirname(__DIR__))
        ->withRouting(
            web: __DIR__.'/../routes/web.php',
            api: __DIR__.'/../routes/api.php',
            commands: __DIR__.'/../routes/console.php',
            health: '/up',
        )
        ->create();
    PHP;
    file_put_contents($path, $original);

    callProtected($this->command, 'ensureApiRoutingRegistered', [$path]);

    expect(file_get_contents($path))->toBe($original);
});
