<?php
namespace App\Http\Controllers;
use App\Models\AuditLog;
use App\Models\Center;
use Illuminate\Http\Request;
use Illuminate\View\View;
class AuditController extends Controller {
    public function index(Request $request): View {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->isCenterAdmin() && !$user->isSecretary()) abort(403);
        $query = AuditLog::query()->with('user');
        if ($user->isCenterAdmin() || $user->isSecretary()) $query->where('center_id', $user->center_id);
        if ($action = $request->input('action')) $query->where('action',$action);
        if ($userId = $request->input('user_id')) $query->where('user_id',$userId);
        if ($date = $request->input('date')) $query->whereDate('created_at',$date);
        $logs = $query->orderBy('created_at','desc')->paginate(30)->withQueryString();
        $centers = $user->isSuperAdmin() ? Center::orderBy('name')->get() : collect();
        $actions = AuditLog::when(!$user->isSuperAdmin(), fn($q) => $q->where('center_id',$user->center_id))->distinct()->orderBy('action')->pluck('action');
        return view('audit.index', compact('logs','centers','actions'));
    }
}