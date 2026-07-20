<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

final class ActiveUserFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $authenticator = auth('session')->getAuthenticator();
        $user = $authenticator->getUser();

        if ($user !== null && ! (bool) $user->active) {
            $authenticator->logout();

            return redirect()->route('login')->with('error', 'La cuenta se encuentra inactiva.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
    }
}
