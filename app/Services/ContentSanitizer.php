<?php

namespace App\Services;

use HTMLPurifier;
use HTMLPurifier_Config;

class ContentSanitizer
{
    protected static ?HTMLPurifier $purifier = null;

    public static function clean(string $content): string
    {
        if (self::$purifier === null) {
            self::$purifier = self::createPurifier();
        }

        return self::$purifier->purify($content);
    }

    private static function createPurifier(): HTMLPurifier
    {
        $config = HTMLPurifier_Config::createDefault();

        $config->set('HTML.Allowed', implode(',', [
            'p', 'div', 'span', 'br', 'hr',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'ul', 'ol', 'li',
            'a', 'img',
            'strong', 'em', 'b', 'i', 'u',
            'blockquote', 'pre', 'code',
            'table', 'thead', 'tbody', 'tr', 'th', 'td',
            'figure', 'figcaption',
            'video', 'audio', 'source', 'embed', 'iframe'
        ]));

        $config->set('HTML.SafeIframe', true);
        $config->set('URI.SafeIframeRegexp', '%^(?:https?:)?//(?:www\.|)(?:youtube(?:-nocookie)?\.com|vimeo\.com|youtube\.com)%');

        $config->set('Attr.AllowedFrameborder', true);

        $config->set('Attr.AllowedRel', 'nofollow');

        $config->set('CSS.AllowedProperties', [
            'text-align', 'color', 'background-color',
            'font-size', 'font-weight', 'margin', 'padding'
        ]);

        $config->set('HTML.ForbiddenElements', [
            'script', 'style', 'object', 'applet',
            'meta', 'link', 'base', 'basefont',
        ]);

        $config->set('HTML.ForbiddenAttributes', [
            'onclick', 'onerror', 'onload', 'onmouseover',
            'onkeydown', 'onkeyup', 'onchange', 'onsubmit'
        ]);

        return new HTMLPurifier($config);
    }
}
