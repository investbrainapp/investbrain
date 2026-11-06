<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PDO;
use Symfony\Component\Process\Process;

/**
 * Runs docker/telemetry.sh, which the Docker entrypoint calls on every start, against a migrated
 * SQLite database and a local server that records what it receives.
 */
class TelemetryScriptTest extends TestCase
{
    protected string $directory;

    protected string $database;

    protected string $received;

    protected ?Process $server = null;

    protected int $port;

    protected function setUp(): void
    {
        parent::setUp();

        if ((new Process(['sh', '-c', 'command -v bash && command -v curl']))->run() !== 0) {
            $this->markTestSkipped('bash and curl are needed to run docker/telemetry.sh.');
        }

        $this->directory = sys_get_temp_dir().'/investbrain-telemetry-'.Str::random(8);
        File::ensureDirectoryExists($this->directory);

        $this->database = $this->directory.'/database.sqlite';
        $this->received = $this->directory.'/received.json';
        touch($this->database);

        $migrate = new Process(['php', 'artisan', 'migrate', '--force'], base_path(), ['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $this->database]);
        $migrate->mustRun();

        $pdo = new PDO('sqlite:'.$this->database);
        $insert = $pdo->prepare('insert into users (id, name, email, password, options, last_seen_at, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?, ?)');
        $insert->execute([(string) Str::uuid(), 'Private Person', 'private@example.com', 'secret', '{"locale":"fr","display_currency":"eur"}', now()->toDateTimeString(), now()->toDateTimeString(), now()->toDateTimeString()]);
        $insert->execute([(string) Str::uuid(), 'Other Person', 'other@example.com', 'secret', '{}', null, now()->toDateTimeString(), now()->toDateTimeString()]);
    }

    protected function tearDown(): void
    {
        $this->server?->stop();

        if (isset($this->directory)) {
            File::deleteDirectory($this->directory);
        }

        parent::tearDown();
    }

    /**
     * @param  array<string, string>  $env
     */
    protected function runScript(array $env = [], array $arguments = []): Process
    {
        $process = new Process(['bash', 'docker/telemetry.sh', ...$arguments], base_path(), [
            'SELF_HOSTED' => 'true',
            'TELEMETRY_ENABLED' => 'true',
            'TELEMETRY_URL' => isset($this->port) ? "http://127.0.0.1:{$this->port}/telemetry" : 'http://127.0.0.1:9/telemetry',
            'TELEMETRY_INSTANCE_FILE' => $this->directory.'/.instance-id',
            'VERSION' => 'v1.2.3',
            'MARKET_DATA_PROVIDER' => 'yahoo, my-own-provider',
            'ENABLED_LOGIN_PROVIDERS' => 'github',
            'BASE_CURRENCY' => 'USD',
            'APP_LOCALE' => 'en',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => $this->database,
            'NO_PROXY' => '127.0.0.1',
            'no_proxy' => '127.0.0.1',
            ...$env,
        ]);

        $process->run();

        return $process;
    }

    /**
     * Start a local server that saves the body of each request it gets.
     */
    protected function startServer(): void
    {
        $router = $this->directory.'/router.php';
        File::put($router, '<?php file_put_contents('.var_export($this->received, true).', file_get_contents("php://input")); http_response_code(202); echo "{\"ok\":true}";');

        $this->port = random_int(20000, 60000);
        $this->server = new Process(['php', '-S', "127.0.0.1:{$this->port}", $router]);
        $this->server->start();

        for ($attempt = 0; $attempt < 50; $attempt++) {
            if (@fsockopen('127.0.0.1', $this->port)) {
                return;
            }

            usleep(100_000);
        }

        $this->fail('The local telemetry server did not start.');
    }

    public function test_it_posts_the_version_environment_and_aggregate_counts(): void
    {
        $this->startServer();

        $process = $this->runScript();

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringContainsString('Sent anonymous usage statistics', $process->getOutput());

        $body = (string) File::get($this->received);
        $report = json_decode($body, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(1, $report['schema']);
        $this->assertSame('started', $report['event']);
        $this->assertTrue(Str::isUuid($report['instance_id']));
        $this->assertSame($report['instance_id'], trim(File::get($this->directory.'/.instance-id')));
        $this->assertSame('v1.2.3', $report['version']);
        $this->assertSame('sqlite', $report['environment']['database']);
        $this->assertSame(['yahoo', 'custom'], $report['features']['market_data_providers']);
        $this->assertSame(['github'], $report['features']['login_providers']);
        $this->assertSame(2, $report['stats']['users']);
        $this->assertSame(1, $report['stats']['active_users_30d']);
        $this->assertSame(['fr' => 1, 'en' => 1], $report['stats']['locales']);
        $this->assertSame(['EUR' => 1, 'USD' => 1], $report['stats']['display_currencies']);

        foreach (['private@example.com', 'Private Person', 'my-own-provider', 'secret'] as $private) {
            $this->assertStringNotContainsString($private, $body);
        }

        // a restart reports as the same instance
        File::delete($this->received);
        $this->runScript();
        $this->assertSame($report['instance_id'], json_decode((string) File::get($this->received), true)['instance_id']);
    }

    public function test_nothing_is_sent_when_turned_off(): void
    {
        $this->startServer();

        $process = $this->runScript(['TELEMETRY_ENABLED' => 'false']);
        $this->assertSame(0, $process->getExitCode());
        $this->assertStringContainsString('Telemetry is turned off.', $process->getOutput());

        $this->assertSame(0, $this->runScript(['SELF_HOSTED' => 'false'])->getExitCode());

        $this->assertFileDoesNotExist($this->received);
    }

    public function test_show_prints_the_report_without_sending_it(): void
    {
        $this->startServer();

        $process = $this->runScript(arguments: ['--show']);

        $this->assertSame('v1.2.3', json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR)['version']);
        $this->assertFileDoesNotExist($this->received);
    }

    public function test_an_unreachable_server_does_not_stop_the_container_from_starting(): void
    {
        $process = $this->runScript();

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringContainsString('Could not send', $process->getOutput());
    }
}
