<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class ProductionReadinessTest extends CIUnitTestCase
{
    public function testContainerExposesOnlyPublicDirectoryAndDisablesPhpErrors(): void
    {
        $vhost = file_get_contents($this->projectRoot() . 'docker/apache-vhost.conf');
        $phpIni = file_get_contents($this->projectRoot() . 'docker/php-production.ini');

        $this->assertIsString($vhost);
        $this->assertIsString($phpIni);
        $this->assertStringContainsString('DocumentRoot /var/www/html/public', $vhost);
        $this->assertStringContainsString('Options -Indexes', $vhost);
        $this->assertStringContainsString('display_errors = Off', $phpIni);
        $this->assertStringContainsString('expose_php = Off', $phpIni);
    }

    public function testRenderBlueprintDeclaresSecretsWithoutValues(): void
    {
        $blueprint = file_get_contents($this->projectRoot() . 'render.yaml');

        $this->assertIsString($blueprint);
        $blueprint = str_replace("\r\n", "\n", $blueprint);
        $this->assertStringContainsString('autoDeployTrigger: checksPass', $blueprint);
        $this->assertStringContainsString('healthCheckPath: /health', $blueprint);
        $this->assertStringContainsString("- key: DATABASE_URL\n        sync: false", $blueprint);
        $this->assertStringContainsString("- key: email_SMTPHost\n        value: smtp.gmail.com", $blueprint);
        $this->assertStringContainsString("- key: email_SMTPPort\n        value: 587", $blueprint);
        $this->assertStringNotContainsString('postgresql://', $blueprint);
    }

    public function testProductionCheckRequiresAuthenticatedSmtp(): void
    {
        $check = file_get_contents($this->projectRoot() . 'ops/check-production-env.php');

        $this->assertIsString($check);
        $this->assertStringContainsString("'email_SMTPUser'", $check);
        $this->assertStringContainsString("'email_SMTPPass'", $check);
        $this->assertStringContainsString("email_protocol=smtp", $check);
        $this->assertStringContainsString("email_SMTPPort válido", $check);
    }

    public function testCiUsesReadOnlyPermissionsAndAvoidsPullRequestTarget(): void
    {
        $workflow = file_get_contents($this->projectRoot() . '.github/workflows/ci.yml');

        $this->assertIsString($workflow);
        $workflow = str_replace("\r\n", "\n", $workflow);
        $this->assertStringContainsString("permissions:\n  contents: read", $workflow);
        $this->assertStringContainsString('composer audit', $workflow);
        $this->assertStringContainsString('npm audit --audit-level=high', $workflow);
        $this->assertStringNotContainsString('pull_request_target', $workflow);
    }

    private function projectRoot(): string
    {
        return dirname(rtrim(APPPATH, '\\/')) . DIRECTORY_SEPARATOR;
    }
}
