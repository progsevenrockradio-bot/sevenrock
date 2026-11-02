<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use Illuminate\Http\Request;

class EmailLogController extends Controller
{
    public function index(?Request $request = null)
    {
        $request = $request ?? request();

        $query = EmailLog::with('trackSubmission')->orderBy('created_at', 'desc');

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(function ($inner) use ($search): void {
                $inner->where('to_email', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        $status = trim((string) $request->query('status', ''));
        if ($status === 'sent') {
            $query->where('status', 'sent');
        } elseif ($status === 'failed') {
            $query->where('status', '<>', 'sent');
        }

        $logs = $query->paginate(30)->withQueryString();

        return view('admin.email-logs.index', compact('logs'));
    }
}
