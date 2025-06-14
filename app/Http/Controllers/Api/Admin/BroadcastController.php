<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Notifications\AdminBroadcastNotification;
use Illuminate\Support\Facades\Notification;

class BroadcastController extends Controller
{
    public function send(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        $users = User::where('role', 'user')->get(); // Mengirim ke semua user
        Notification::send($users, new AdminBroadcastNotification($request->title, $request->message));

        return response()->json(['message' => 'Broadcast notification sent successfully.']);
    }
}
