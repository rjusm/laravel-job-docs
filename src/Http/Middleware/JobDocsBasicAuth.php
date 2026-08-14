<?php

namespace Rjusm\LaravelJobDocs\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JobDocsBasicAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('job-docs.basic_auth.enabled', false)) {
            return $next($request);
        }

        $expectedUsername = (string) config('job-docs.basic_auth.username', '');
        $expectedPassword = (string) config('job-docs.basic_auth.password', '');

        $providedUsername = (string) $request->getUser();
        $providedPassword = (string) $request->getPassword();

        if (
            $expectedUsername !== ''
            && hash_equals($expectedUsername, $providedUsername)
            && hash_equals($expectedPassword, $providedPassword)
        ) {
            return $next($request);
        }

        return response('Unauthorized.', 401, ['WWW-Authenticate' => 'Basic realm="Job Docs"']);
    }
}
