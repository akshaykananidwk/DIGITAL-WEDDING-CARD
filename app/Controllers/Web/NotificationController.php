<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\NotificationRepository;
use App\Services\SeoService;

final class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationRepository $notifications = new NotificationRepository()
    ) {
    }

    public function index(Request $request): Response
    {
        $userId = (int) Auth::id();
        $items = $this->notifications->forUser($userId, 50);

        if ($request->expectsJson()) {
            return $this->success([
                'notifications' => $items,
                'unread'        => $this->notifications->unreadCount($userId),
            ]);
        }

        return $this->view('dashboard.notifications', [
            'seo'           => SeoService::make()->title('Notifications')->noindex(),
            'notifications' => $items,
        ]);
    }

    public function markRead(Request $request): Response
    {
        $this->notifications->markRead($request->int('id'), (int) Auth::id());
        return $request->expectsJson()
            ? $this->success(null, 'Marked as read.')
            : $this->redirect('notifications');
    }

    public function markAllRead(Request $request): Response
    {
        $count = $this->notifications->markAllRead((int) Auth::id());
        return $request->expectsJson()
            ? $this->success(['count' => $count], 'All caught up.')
            : $this->redirect('notifications');
    }
}
