<?php

namespace App\Http\Controllers;

use App\Models\Talent;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class TalentOgImageController extends Controller
{
    public function show(string $talentName): Response
    {
        $decoded = urldecode($talentName);
        $normalizedName = str_replace('-', ' ', $decoded);
        
        $talent = Talent::where('band_name', $decoded)
            ->orWhere('band_name', $normalizedName)
            ->firstOrFail();
        
        $cacheKey = 'talent_og_image_' . $talent->id . '_' . $talent->updated_at?->timestamp;
        
        $imageContent = Cache::remember($cacheKey, now()->addDays(7), function () use ($talent) {
            return $this->generateImage($talent);
        });
        
        return response($imageContent, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }
    
    private function generateImage(Talent $talent): string
    {
        $width = 1200;
        $height = 630;
        
        $canvas = imagecreatetruecolor($width, $height);
        
        // Colors
        $bgColor = imagecolorallocate($canvas, 16, 21, 26); // #10151A
        $whiteColor = imagecolorallocate($canvas, 255, 255, 255);
        $accentColor = imagecolorallocate($canvas, 195, 39, 32); // #C32720
        $grayColor = imagecolorallocate($canvas, 150, 150, 150);
        
        imagefill($canvas, 0, 0, $bgColor);
        
        // Font settings
        $fontPath = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf');
        
        // 1. Determine best image
        $bestImageUrl = $talent->logoUrl();
        if (!$bestImageUrl) {
            $firstPhoto = $talent->media()->where('type', 'photo')->first();
            if ($firstPhoto) {
                $bestImageUrl = $firstPhoto->url;
            }
        }
        
        // 2. Draw the image on the left (square crop, e.g. 500x500)
        $padding = 65;
        $imgSize = 500;
        
        if ($bestImageUrl) {
            // Fetch external or local path
            try {
                $imageContext = stream_context_create([
                    'http' => [
                        'method' => 'GET',
                        'timeout' => 5,
                    ],
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                    ],
                ]);
                
                // If it's a relative URL, prepend app.url
                if (!str_starts_with(strtolower($bestImageUrl), 'http')) {
                    $bestImageUrl = rtrim(config('app.url'), '/') . '/' . ltrim($bestImageUrl, '/');
                }
                
                $tempImage = @file_get_contents($bestImageUrl, false, $imageContext);
                if ($tempImage) {
                    $srcImg = @imagecreatefromstring($tempImage);
                }
            } catch (\Exception $e) {
                $srcImg = null;
            }
            
            if (isset($srcImg) && $srcImg !== false) {
                $srcW = imagesx($srcImg);
                $srcH = imagesy($srcImg);
                
                // We want to crop it to a square
                $cropSize = min($srcW, $srcH);
                $cropX = ($srcW - $cropSize) / 2;
                $cropY = ($srcH - $cropSize) / 2;
                
                imagecopyresampled(
                    $canvas, $srcImg,
                    $padding, $padding, // Dest X, Y
                    $cropX, $cropY, // Src X, Y
                    $imgSize, $imgSize, // Dest W, H
                    $cropSize, $cropSize // Src W, H
                );
                
                imagedestroy($srcImg);
            } else {
                // Draw a placeholder box if image failed
                imagefilledrectangle($canvas, $padding, $padding, $padding + $imgSize, $padding + $imgSize, $grayColor);
            }
        } else {
            // Use radio logo as fallback if no image at all
            $logoPath = public_path('assets/lucille/logo.png');
            if (file_exists($logoPath)) {
                $srcImg = @imagecreatefrompng($logoPath);
                if ($srcImg) {
                    $srcW = imagesx($srcImg);
                    $srcH = imagesy($srcImg);
                    // Center it in the square
                    $ratio = min($imgSize / $srcW, $imgSize / $srcH);
                    $newW = $srcW * $ratio;
                    $newH = $srcH * $ratio;
                    $posX = $padding + ($imgSize - $newW) / 2;
                    $posY = $padding + ($imgSize - $newH) / 2;
                    
                    imagecopyresampled($canvas, $srcImg, $posX, $posY, 0, 0, $newW, $newH, $srcW, $srcH);
                    imagedestroy($srcImg);
                }
            }
        }
        
        // 3. Draw Text
        if (file_exists($fontPath)) {
            $textX = $padding + $imgSize + 50;
            
            // Band Name
            $bandName = mb_strtoupper($talent->band_name, 'UTF-8');
            $fontSize = 48;
            
            // Wrap text if too long
            $wrappedText = $this->wrapText($fontSize, $fontPath, $bandName, $width - $textX - $padding);
            
            // Calculate total height of band name
            $bbox = imagettfbbox($fontSize, 0, $fontPath, $wrappedText);
            $textHeight = abs($bbox[7] - $bbox[1]);
            
            // Draw Band Name (Centered vertically relative to the image)
            // Let's just hardcode Y for simplicity or calculate it
            $textY = $padding + ($imgSize / 2) - ($textHeight / 2) - 20;
            if ($textY < $padding + 100) $textY = $padding + 100;
            
            imagettftext($canvas, $fontSize, 0, $textX, $textY, $whiteColor, $fontPath, $wrappedText);
            
            // Draw Subtitle "Seven Rock Radio"
            $subTitleY = $textY + 80;
            imagettftext($canvas, 20, 0, $textX, $subTitleY, $accentColor, $fontPath, "SEVEN ROCK RADIO");
            
            // Draw Subtitle "Muro del Rock"
            imagettftext($canvas, 20, 0, $textX, $subTitleY + 40, $grayColor, $fontPath, "Muro del Rock");
        }
        
        // Capture output
        ob_start();
        imagepng($canvas);
        $imageContent = ob_get_clean();
        
        imagedestroy($canvas);
        
        return $imageContent;
    }
    
    private function wrapText($fontSize, $fontFace, $string, $maxWidth)
    {
        $ret = "";
        $arr = explode(' ', $string);
        foreach ($arr as $word) {
            $teststring = $ret . ' ' . $word;
            $testbox = imagettfbbox($fontSize, 0, $fontFace, $teststring);
            if ($testbox[2] > $maxWidth) {
                $ret .= ($ret == "" ? "" : "\n") . $word;
            } else {
                $ret .= ($ret == "" ? "" : ' ') . $word;
            }
        }
        return $ret;
    }
}
