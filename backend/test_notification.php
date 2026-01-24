<?php
/**
 * Script test tạo notification
 * Chạy: php test_notification.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

echo "=== Test tạo notification ===\n\n";

// 1. Kiểm tra bảng có tồn tại không
try {
    if (!Schema::hasTable('notifications')) {
        echo "✗ Bảng 'notifications' chưa tồn tại!\n";
        echo "  → Chạy: php artisan migrate\n";
        exit(1);
    }
    echo "✓ Bảng 'notifications' đã tồn tại\n\n";
} catch (\Exception $e) {
    echo "✗ Lỗi khi kiểm tra bảng: " . $e->getMessage() . "\n";
    exit(1);
}

// 2. Đếm số notifications hiện tại
try {
    $countBefore = Notification::count();
    echo "Số notifications hiện tại: {$countBefore}\n\n";
} catch (\Exception $e) {
    echo "✗ Lỗi khi đếm notifications: " . $e->getMessage() . "\n";
    exit(1);
}

// 3. Tạo test notification
echo "Tạo test notification...\n";
try {
    $notification = Notification::create([
        'type' => 'order',
        'title' => 'Đơn hàng test',
        'message' => 'Đây là notification test từ script',
        'url' => '/admin/orders/1',
        'reference_id' => 1,
        'is_read' => false,
    ]);
    echo "✓ Tạo notification thành công! ID: {$notification->id}\n\n";
} catch (\Exception $e) {
    echo "✗ Lỗi khi tạo notification: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

// 4. Đếm lại
try {
    $countAfter = Notification::count();
    echo "Số notifications sau khi tạo: {$countAfter}\n";
    echo "Đã tăng: " . ($countAfter - $countBefore) . " notification(s)\n\n";
} catch (\Exception $e) {
    echo "✗ Lỗi khi đếm lại: " . $e->getMessage() . "\n";
}

// 5. Lấy notification vừa tạo
try {
    $notif = Notification::find($notification->id);
    echo "Notification vừa tạo:\n";
    echo "  - ID: {$notif->id}\n";
    echo "  - Type: {$notif->type}\n";
    echo "  - Title: {$notif->title}\n";
    echo "  - Message: {$notif->message}\n";
    echo "  - Is Read: " . ($notif->is_read ? 'Yes' : 'No') . "\n";
    echo "  - Created At: {$notif->created_at}\n\n";
} catch (\Exception $e) {
    echo "✗ Lỗi khi lấy notification: " . $e->getMessage() . "\n";
}

echo "=== Test hoàn thành ===\n";







