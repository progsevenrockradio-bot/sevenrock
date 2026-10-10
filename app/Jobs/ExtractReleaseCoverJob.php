<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\NewRelease;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExtractReleaseCoverJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $release;
    public $r2FilePath;

    /**
     * Create a new job instance.
     */
    public function __construct(NewRelease $release, string $r2FilePath)
    {
        $this->release = $release;
        $this->r2FilePath = $r2FilePath;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->release->cover_image) {
            return;
        }

        $tmpPath = storage_path('app/temp_id3_' . Str::random(10) . '.mp3');

        try {
            $fileContents = Storage::disk('r2')->get($this->r2FilePath);
            if (!$fileContents) {
                return;
            }

            file_put_contents($tmpPath, $fileContents);

            $getId3 = new \JamesHeinrich\GetID3\GetID3();
            $fileInfo = $getId3->analyze($tmpPath);

            if (isset($fileInfo['comments']['picture'][0]['data'])) {
                $picture = $fileInfo['comments']['picture'][0];
                $imageBytes = $picture['data'];
                $imageMime = $picture['image_mime'] ?? 'image/jpeg';
                
                $ext = explode('/', $imageMime)[1] ?? 'jpg';
                $ext = str_replace('jpeg', 'jpg', $ext);
                
                $newFileName = 'catalog/releases/covers/' . Str::uuid()->toString() . '.' . $ext;
                
                Storage::disk('public')->put($newFileName, $imageBytes);
                
                $this->release->cover_image = $newFileName;
                $this->release->save();
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Error extracting cover in ExtractReleaseCoverJob for release {$this->release->id}: " . $e->getMessage());
        } finally {
            if (file_exists($tmpPath)) {
                @unlink($tmpPath);
            }
        }
    }
}
