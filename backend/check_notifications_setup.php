<?php
/**
 * Script kiểm tra setup notifications
 * Chạy: php check_notifications_setup.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

echo "=== Kiểm tra setup notifications ===\n\n";

// 1. Kiểm tra bảng notifications có tồn tại không
try {
    $tableExists = Schema::hasTable('notifications');
    if ($tableExists) {
        echo "✓ Bảng 'notifications' đã tồn tại\n";
        
        // Đếm số lượng notifications
        $count = DB::table('notifications')->count();
        echo "  - Số lượng notifications: {$count}\n";
        
        // Đếm số lượng chưa đọc
        $unreadCount = DB::table('notifications')->where('is_read', false)->count();
        echo "  - Số lượng chưa đọc: {$unreadCount}\n";
        
        // Hiển thị 5 notifications gần nhất
        $recent = DB::table('notifications')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
        
        if ($recent->count() > 0) {
            echo "  - 5 notifications gần nhất:\n";
            foreach ($recent as $notif) {
                echo "    * [{$notif->type}] {$notif->title} - " . ($notif->is_read ? 'Đã đọc' : 'Chưa đọc') . "\n";
            }
        }
    } else {
        echo "✗ Bảng 'notifications' CHƯA TỒN TẠI\n";
        echo "  → Cần chạy migration: php artisan migrate\n";
    }
} catch (\Exception $e) {
    echo "✗ Lỗi khi kiểm tra bảng: " . $e->getMessage() . "\n";
}

// 2. Kiểm tra migration
echo "\n=== Kiểm tra migration ===\n";
try {
    $migrations = DB::table('migrations')
        ->where('migration', 'like', '%notifications%')
        ->get();
    
    if ($migrations->count() > 0) {
        echo "✓ Migration notifications đã chạy:\n";
        foreach ($migrations as $migration) {
            echo "  - {$migration->migration}\n";
        }
    } else {
        echo "✗ Migration notifications CHƯA CHẠY\n";
        echo "  → Cần chạy migration: php artisan migrate\n";
    }
} catch (\Exception $e) {
    echo "✗ Lỗi khi kiểm tra migration: " . $e->getMessage() . "\n";
}

// 3. Kiểm tra model Notification
echo "\n=== Kiểm tra model Notification ===\n";
try {
    $notification = new \App\Models\Notification();
    echo "✓ Model Notification tồn tại\n";
    echo "  - Table: {$notification->getTable()}\n";
    echo "  - Fillable: " . implode(', ', $notification->getFillable()) . "\n";
} catch (\Exception $e) {
    echo "✗ Lỗi khi kiểm tra model: " . $e->getMessage() . "\n";
}

echo "\n=== Kết thúc kiểm tra ===\n";







