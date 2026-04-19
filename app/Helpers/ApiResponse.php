<?php

namespace App\Helpers;

class ApiResponse
{
    public static function success($data = null, $message = null)
    {
        return response()->json([
            'success' => true,
            'message' => $message ?? 'Operation successful',
            'errors' => null,
            'data' => $data,
        ]);
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
