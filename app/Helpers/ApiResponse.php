<?php

namespace App\Helpers;

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
}
