<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use Illuminate\Http\Request;

class EmailLogController extends Controller
{
    public function index()
    {
        $logs = EmailLog::orderBy('created_at', 'desc')->paginate(30);
        return view('admin.email-logs.index', compact('logs'));
    }
}
