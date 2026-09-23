<?php

namespace App\Helpers;

use Illuminate\Database\QueryException;

class ApiResponse
{
    public static function success($data = null, $message = null, $token = null)
    {
        $payload = [
            'success' => true,
            'message' => $message ?? 'Operation successful',
            'errors' => null,
        ];

        if ($token !== null) {
            $payload['token'] = $token;
            if (is_array($data)) {
                $data['token'] = $token;
            } elseif ($data === null) {
                $data = ['token' => $token];
            }
        }

        $payload['data'] = $data;

        return response()->json($payload);
    }

    public static function error($message = null, $errors = null, $status = 400)
    {
        return response()->json([
            'success' => false,
            'message' => $message ?? 'Something went wrong',
            'errors' => $errors,
            'data' => null,
        ], $status);
    }

    /**
     * Whether crash details may be shown to the caller. Debug builds and test
     * environments only — a live production API must never hand out SQL, file
     * paths or stack frames.
     */
    public static function exposesDebug(): bool
    {
        return (bool) config('app.debug') || (bool) config('app.is_testing');
    }

    /**
     * A crashed request, in the same envelope as every other API error. When
     * debugging is exposed the response carries a `debug` block with the real
     * exception — including the failing SQL and its bindings — so a tester can
     * read the cause straight off the response instead of asking for the log.
     */
    public static function exception(\Throwable $e, int $status = 500)
    {
        $expose = self::exposesDebug();

        $payload = [
            'success' => false,
            'message' => $expose ? $e->getMessage() : 'Server error',
            'errors' => null,
            'data' => null,
        ];

        if ($expose) {
            $debug = [
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'file' => str_replace(base_path().'/', '', $e->getFile()).':'.$e->getLine(),
            ];

            if ($e instanceof QueryException) {
                $debug['sql'] = $e->getSql();
                $debug['bindings'] = $e->getBindings();
            }

            if ($previous = $e->getPrevious()) {
                $debug['previous'] = $previous::class.': '.$previous->getMessage();
            }

            // App frames only, and only the top of the stack — vendor noise buries
            // the one line that actually matters.
            $debug['trace'] = collect($e->getTrace())
                ->filter(fn ($frame) => isset($frame['file']) && str_starts_with($frame['file'], app_path()))
                ->take(10)
                ->map(fn ($frame) => str_replace(base_path().'/', '', $frame['file']).':'.($frame['line'] ?? '?')
                    .' '.($frame['class'] ?? '').($frame['type'] ?? '').($frame['function'] ?? ''))
                ->values()
                ->all();

            $payload['debug'] = $debug;
        }

        return response()->json($payload, $status);
    }
}
