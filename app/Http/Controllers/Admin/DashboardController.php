<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RadioArtist;
use App\Models\MasterProgram;
use App\Models\PostTaxonomy;
use App\Models\Post;
use App\Models\BandContact;
use App\Models\OutreachCampaign;
use App\Models\OutreachLog;
use App\Models\RadioProgram;
use App\Models\Song;
use App\Models\ThemeSetting;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('admin.dashboard', [
            'settings' => ThemeSetting::current(),
            'stats' => [
                'users' => User::query()->count(),
                'admin_users' => $this->countAdminUsers(),
                'radio_artists' => $this->countIfTable('radio_artists', RadioArtist::query()),
                'songs' => $this->countIfTable('songs', Song::query()),
                'master_programs' => $this->countIfTable('master_programs', MasterProgram::query()),
                'radio_programs' => $this->countIfTable('radio_programs', RadioProgram::query()),
                'posts' => $this->countIfTable('posts', Post::query()),
                'categories' => $this->countTaxonomies(PostTaxonomy::TYPE_CATEGORY),
                'tags' => $this->countTaxonomies(PostTaxonomy::TYPE_TAG),
                'outreach_contacts' => $this->countIfTable('band_contacts', BandContact::query()),
                'outreach_sent' => $this->countIfTable('outreach_logs', OutreachLog::query()->where('status', 'sent')),
                'outreach_responded' => $this->countIfTable('outreach_logs', OutreachLog::query()->where('status', 'responded')),
                'outreach_registered' => $this->countIfTable('band_contacts', BandContact::query()->where('status', 'registered')),
            ],
            'taxonomies' => [
                'categories' => $this->taxonomiesFor(PostTaxonomy::TYPE_CATEGORY),
                'tags' => $this->taxonomiesFor(PostTaxonomy::TYPE_TAG),
            ],
        ]);
    }

    private function countTaxonomies(string $type): int
    {
        if (! Schema::hasTable('post_taxonomies')) {
            return 0;
        }

        return PostTaxonomy::query()->where('type', $type)->count();
    }

    private function taxonomiesFor(string $type): Collection
    {
        if (! Schema::hasTable('post_taxonomies')) {
            return collect();
        }

        return PostTaxonomy::query()
            ->where('type', $type)
            ->orderBy('name')
            ->get();
    }

    private function countIfTable(string $table, $query): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        return (int) $query->count();
    }

    private function countAdminUsers(): int
    {
        $query = User::query();

        $hasRoleColumn = Schema::hasColumn('users', 'role');
        $hasIsAdminColumn = Schema::hasColumn('users', 'is_admin');

        if ($hasRoleColumn && $hasIsAdminColumn) {
            $query->where(function ($innerQuery): void {
                $innerQuery->where('role', 'admin')->orWhere('is_admin', true);
            });
        } elseif ($hasRoleColumn) {
            $query->where('role', 'admin');
        } elseif ($hasIsAdminColumn) {
            $query->where('is_admin', true);
        }

        return (int) $query->count();
    }
}
