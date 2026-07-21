<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\AuthMailerInterface;
use CodeIgniter\Shield\Entities\User;
use Config\Email as EmailConfig;
use Throwable;

final class AuthMailer implements AuthMailerInterface
{
    public function sendAccessLink(User $user, string $token, bool $invitation): bool
    {
        $emailAddress = $user->email;
        if ($emailAddress === null || $emailAddress === '') {
            return false;
        }

        $config = config(EmailConfig::class);
        $missing = $this->missingConfiguration($config);
        if ($missing !== []) {
            log_message('error', 'Configuración SMTP incompleta. Faltan: {fields}.', [
                'fields' => implode(', ', $missing),
            ]);

            return false;
        }

        helper('email');

        try {
            $email = emailer(['mailType' => 'html'])
                ->setFrom($config->fromEmail, $config->fromName)
                ->setTo($emailAddress)
                ->setSubject($invitation ? 'Activa tu cuenta de inventario' : 'Restablece tu acceso al inventario')
                ->setMessage(view('emails/auth_access_link', [
                    'user'       => $user,
                    'accessUrl'  => url_to('verify-magic-link') . '?token=' . rawurlencode($token),
                    'invitation' => $invitation,
                    'expiresIn'  => (int) (setting('Auth.magicLinkLifetime') / MINUTE),
                ]));

            $sent = $email->send(false);
            if (! $sent) {
                $this->logDeliveryFailure($email->printDebugger([]), $config, $emailAddress);
            }
            $email->clear();
        } catch (Throwable $exception) {
            $this->logDeliveryFailure($exception->getMessage(), $config, $emailAddress);

            return false;
        }

        return $sent;
    }

    /**
     * @return list<string>
     */
    private function missingConfiguration(EmailConfig $config): array
    {
        $required = [
            'fromEmail' => $config->fromEmail,
            'SMTPHost'  => $config->SMTPHost,
            'SMTPUser'  => $config->SMTPUser,
            'SMTPPass'  => $config->SMTPPass,
        ];

        $missing = [];
        foreach ($required as $field => $value) {
            if (trim($value) === '') {
                $missing[] = $field;
            }
        }

        if ($config->protocol !== 'smtp') {
            $missing[] = 'protocol=smtp';
        }

        return $missing;
    }

    private function logDeliveryFailure(string $diagnostic, EmailConfig $config, string $recipient): void
    {
        $sanitized = strip_tags($diagnostic);
        foreach ([$config->SMTPUser, $config->SMTPPass, $recipient] as $sensitive) {
            if ($sensitive !== '') {
                $sanitized = str_ireplace($sensitive, '[oculto]', $sanitized);
            }
        }

        $sanitized = trim((string) preg_replace('/\s+/', ' ', $sanitized));
        log_message('error', 'Falló la entrega SMTP: {diagnostic}', [
            'diagnostic' => mb_substr($sanitized !== '' ? $sanitized : 'sin detalle del servidor', 0, 800),
        ]);
    }
}
