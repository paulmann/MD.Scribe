<?php

/**
 * MD.Viewer 3.0 — CSRF protection for every mutating request.
 *
 * Token lives in the session; forms embed it as `_token`, fetch() calls send
 * the `X-CSRF-Token` header. Compared with hash_equals().
 */

declare(strict_types=1);

namespace MdViewer\Core;

final class Csrf
{
    public const string SESSION_KEY = '_mdv_csrf';
    public const string FIELD = '_token';
    public const string HEADER = 'X-CSRF-Token';

    public function __construct(private readonly Session $session)
    {
    }

    public function token(): string
    {
        $token = $this->session->get(self::SESSION_KEY);
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $this->session->set(self::SESSION_KEY, $token);
        }

        return $token;
    }

    public function field(): string
    {
        return '<input type="hidden" name="' . self::FIELD . '" value="' . htmlspecialchars($this->token(), ENT_QUOTES) . '">';
    }

    public function validate(Request $request): bool
    {
        if (!$request->isPost()) {
            return true;
        }
        $sent = $request->post(self::FIELD) ?? $request->header(self::HEADER);

        return is_string($sent) && $sent !== '' && hash_equals($this->token(), $sent);
    }

    public function rotate(): void
    {
        $this->session->set(self::SESSION_KEY, bin2hex(random_bytes(32)));
    }
}
