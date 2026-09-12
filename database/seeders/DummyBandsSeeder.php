<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Talent;
use App\Models\CommunityPost;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DummyBandsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $bandNames = [
            'The Midnight Echoes',
            'Crimson Tide',
            'Electric Nomad',
            'Shadow Vipers',
            'Neon Prophets',
            'Velvet Steel',
            'Sonic Renegades',
            'Iron Haven'
        ];

        $dummyBands = [];

        foreach ($bandNames as $index => $name) {
            $user = \App\Models\User::firstOrCreate(
                ['email' => 'dummyband' . $index . '@sevenrockradio.com'],
                [
                    'name' => $name . ' Admin',
                    'password' => Hash::make('password123'),
                    'email_verified_at' => now(),
                ]
            );

            $dummyBands[] = Talent::create([
                'user_id' => $user->id,
                'band_name' => $name,
                'email' => 'dummyband' . $index . '@sevenrockradio.com',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
                'bio' => 'Somos ' . $name . ', una banda de rock nacida para hacer vibrar los escenarios. Nuestra música combina riffs potentes con melodías inolvidables.',
                'logo' => null, 
                'subscription_status' => 'active',
                'plan' => match($index % 3) {
                    0 => 'pro',
                    1 => 'basic',
                    2 => 'free',
                },
                'is_featured' => ($index % 3 === 0), // Alternamos destacados para armar el Bento Grid
                'interacts' => rand(10, 500),
            ]);
        }

        $postContents = [
            '¡Pronto lanzaremos nuestro nuevo sencillo! Estén atentos 🎸🔥',
            'Gracias a todos los que nos apoyaron en el show de ayer. ¡Fue increíble! 🤘',
            'Estamos en el estudio grabando nuevo material. Suena brutal.',
            '¿Qué opinan de nuestro último video? Dejen sus comentarios 👇',
            'El rock no está muerto, ¡apenas está tomando fuerza!',
            'Anunciando nueva fecha de gira para este verano. Revisen nuestra bio.',
            'Un clásico de nuestro primer álbum para empezar la semana. 🎶',
            'Preparando los motores para el festival de la próxima semana.',
        ];

        // Crear unos 24 posts aleatorios en el muro para que se vea lleno
        for ($i = 0; $i < 24; $i++) {
            $band = $dummyBands[array_rand($dummyBands)];
            $content = $postContents[array_rand($postContents)];
            
            // Ocasionalmente agregar un enlace a YouTube
            $youtubeUrl = (rand(1, 4) === 1) ? 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' : null;

            CommunityPost::create([
                'talent_id' => $band->id,
                'user_id' => null,
                'content' => $content,
                'youtube_url' => $youtubeUrl,
                'created_at' => now()->subHours(rand(1, 200)),
            ]);
        }
    }
}
