<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MasterProgramCreateWithoutIsAdminColumnTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_form_renders_when_users_table_lacks_is_admin_column(): void
    {
        if (Schema::hasColumn('users', 'is_admin')) {
            Schema::table('users', function ($table) {
                $table->dropColumn('is_admin');
            });
        }

        if (!Schema::hasColumn('users', 'role')) {
            Schema::table('users', function ($table) {
                $table->string('role', 20)->default('user');
            });
        }

        $admin = new User([
            'name' => 'Admin User',
            'email' => 'admin_test@sevenrockradio.com',
            'password' => bcrypt('password123'),
        ]);
        $admin->role = 'admin';
        $admin->save();

        $conductor = new User([
            'name' => 'Conductor User',
            'email' => 'conductor_test@sevenrockradio.com',
            'password' => bcrypt('password123'),
        ]);
        $conductor->role = 'user';
        $conductor->save();

        $response = $this->actingAs($admin)->get(route('admin.master-programs.create'));

        $response->assertOk()
            ->assertSee('Nuevo programa de radio')
            ->assertSee('Conductor User');
    }
}
