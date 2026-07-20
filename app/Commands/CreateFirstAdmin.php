<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Shield\Models\UserModel;
use Config\Services;
use Throwable;

final class CreateFirstAdmin extends BaseCommand
{
    protected $group = 'Inventario';
    protected $name = 'invmat:admin:create';
    protected $description = 'Crea el primer administrador y envía un enlace para establecer su contraseña.';
    protected $usage = 'invmat:admin:create --username administrador --email admin@institucion.edu';
    protected $options = [
        '--username' => 'Nombre de usuario (letras, números y puntos).',
        '--email'    => 'Correo del primer administrador.',
        '--resend'   => 'Reenvía la invitación a un administrador existente.',
    ];

    public function run(array $params): int
    {
        $username = (string) ($params['username'] ?? CLI::getOption('username') ?? '');
        $email = (string) ($params['email'] ?? CLI::getOption('email') ?? '');
        $resend = array_key_exists('resend', $params) || CLI::getOption('resend') === true;

        if ($email === '') {
            $email = CLI::prompt('Correo del administrador');
        }

        if ($resend) {
            return $this->resend($email);
        }

        if (Services::userAccounts()->hasAdministrator()) {
            CLI::error('Ya existe un administrador activo. Crea los demás usuarios desde la aplicación.');

            return EXIT_ERROR;
        }

        if ($username === '') {
            $username = CLI::prompt('Nombre de usuario');
        }

        $tables = config('Auth')->tables;
        $validation = Services::validation();
        $validation->setRules([
            'username' => "required|min_length[3]|max_length[30]|regex_match[/\\A[a-zA-Z0-9.]+\\z/]|is_unique[{$tables['users']}.username]",
            'email'    => "required|max_length[254]|valid_email|is_unique[{$tables['identities']}.secret]",
        ]);

        if (! $validation->run(['username' => $username, 'email' => $email])) {
            foreach ($validation->getErrors() as $error) {
                CLI::error($error);
            }

            return EXIT_ERROR;
        }

        try {
            $result = Services::userAccounts()->createInvitedUser($username, $email, 'admin');
        } catch (Throwable $exception) {
            log_message('error', 'Fallo la creación del primer administrador: {message}', [
                'message' => $exception->getMessage(),
            ]);
            CLI::error('No fue posible crear el administrador. Revisa el log de la aplicación.');

            return EXIT_ERROR;
        }

        if (! $result['emailSent']) {
            CLI::error('La cuenta fue creada, pero el correo no se envió. Corrige SMTP y usa --resend con el mismo correo.');

            return EXIT_ERROR;
        }

        CLI::write('Administrador creado. Revisa el correo capturado por Mailpit o el proveedor SMTP.', 'green');

        return EXIT_SUCCESS;
    }

    private function resend(string $email): int
    {
        $user = model(UserModel::class)->findByCredentials(['email' => $email]);

        if ($user === null || ! $user->inGroup('admin')) {
            CLI::error('No existe un administrador con ese correo.');

            return EXIT_ERROR;
        }

        try {
            $sent = Services::userAccounts()->sendPasswordSetup($user, true);
        } catch (Throwable $exception) {
            log_message('error', 'Fallo el reenvío de la invitación inicial: {message}', [
                'message' => $exception->getMessage(),
            ]);
            $sent = false;
        }

        if (! $sent) {
            CLI::error('No fue posible enviar la invitación. Revisa la configuración SMTP.');

            return EXIT_ERROR;
        }

        CLI::write('Invitación reenviada correctamente.', 'green');

        return EXIT_SUCCESS;
    }
}
