<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

final class ExternalHttp
{
    public static function client(): PendingRequest
    {
        $options = ['proxy' => ''];

        if (defined('CURLOPT_PROXY')) {
            $options['curl'] = [CURLOPT_PROXY => ''];
        }

        return Http::withOptions($options);
    }
}
