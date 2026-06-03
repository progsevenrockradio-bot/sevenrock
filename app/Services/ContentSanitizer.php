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

        $allowedHtml = config('purify.allowed_html', [
            'p[class]',
            'div[class]',
            'span[class]',
            'br',
            'hr',
            'h1[class]',
            'h2[class]',
            'h3[class]',
            'h4[class]',
            'h5[class]',
            'h6[class]',
            'ul[class]',
            'ol[class]',
            'li[class]',
            '*[class]',
            'a[href|target|rel|class|title]',
            'img[src|alt|class]',
            'strong[class]',
            'em[class]',
            'b[class]',
            'i[class]',
            'u[class]',
            'blockquote[class]',
            'pre[class]',
            'code[class]',
            'table[class]',
            'thead[class]',
            'tbody[class]',
            'tr[class]',
            'th[class]',
            'td[class]',
            'figure[class]',
            'figcaption[class]',
            'video[class|controls|poster|preload]',
            'audio[class|controls|preload]',
            'source[src|type]',
            'embed[src|type]',
            'iframe[src|class|allow|allowfullscreen|frameborder|loading|title]',
        ]);

        $config->set('HTML.Allowed', implode(',', $allowedHtml));

        $config->set('HTML.SafeIframe', (bool) config('purify.safe_iframe', true));
        $config->set('URI.SafeIframeRegexp', (string) config('purify.safe_iframe_regexp', '%^(?:https?:)?//(?:www\.|)(?:youtube(?:-nocookie)?\.com|youtu\.be|vimeo\.com|youtube\.com)%'));

        $config->set('Attr.AllowedFrameborder', (bool) config('purify.allowed_frameborder', true));
        $config->set('Attr.AllowedFrameTargets', config('purify.allowed_frame_targets', ['_blank' => true]));
        $config->set('Attr.AllowedRel', config('purify.allowed_rel', ['nofollow' => true, 'noopener' => true, 'noreferrer' => true]));

        $config->set('HTML.TargetBlank', (bool) config('purify.target_blank', false));
        $config->set('HTML.TargetNoopener', (bool) config('purify.target_noopener', true));
        $config->set('HTML.TargetNoreferrer', (bool) config('purify.target_noreferrer', true));

        $config->set('CSS.AllowedProperties', config('purify.css_allowed_properties', [
            'text-align', 'color', 'background-color',
            'font-size', 'font-weight', 'margin', 'padding'
        ]));

        $config->set('HTML.ForbiddenElements', config('purify.forbidden_elements', [
            'script', 'style', 'object', 'applet',
            'meta', 'link', 'base', 'basefont',
        ]));

        $config->set('HTML.ForbiddenAttributes', config('purify.forbidden_attributes', [
            'onclick', 'onerror', 'onload', 'onmouseover',
            'onkeydown', 'onkeyup', 'onchange', 'onsubmit'
        ]));

        return new HTMLPurifier($config);
    }
}
