<?php
/**
 * Script kiểm tra và sửa lỗi notifications
 * Chạy: php fix_notifications.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

echo "=== Kiểm tra và sửa lỗi notifications ===\n\n";

// 1. Kiểm tra migration
echo "1. Kiểm tra migration...\n";
try {
    $migrations = DB::table('migrations')
        ->where('migration', 'like', '%notifications%')
        ->get();
    
    if ($migrations->count() > 0) {
        echo "   ✓ Migration notifications đã chạy\n";
        foreach ($migrations as $migration) {
            echo "     - {$migration->migration}\n";
        }
    } else {
        echo "   ✗ Migration notifications CHƯA CHẠY\n";
        echo "   → Đang chạy migration...\n";
        Artisan::call('migrate', ['--force' => true]);
        echo "   ✓ Migration đã chạy\n";
    }
} catch (\Exception $e) {
    echo "   ✗ Lỗi khi kiểm tra migration: " . $e->getMessage() . "\n";
    echo "   → Thử chạy migration thủ công: php artisan migrate\n";
}

// 2. Kiểm tra bảng
echo "\n2. Kiểm tra bảng notifications...\n";
try {
    if (!Schema::hasTable('notifications')) {
        echo "   ✗ Bảng 'notifications' chưa tồn tại!\n";
        echo "   → Đang chạy migration...\n";
        Artisan::call('migrate', ['--force' => true]);
        if (Schema::hasTable('notifications')) {
            echo "   ✓ Bảng đã được tạo\n";
        } else {
            echo "   ✗ Vẫn không tạo được bảng. Kiểm tra lại migration.\n";
            exit(1);
        }
    } else {
        echo "   ✓ Bảng 'notifications' đã tồn tại\n";
    }
} catch (\Exception $e) {
    echo "   ✗ Lỗi khi kiểm tra bảng: " . $e->getMessage() . "\n";
    exit(1);
}

// 3. Kiểm tra cấu trúc bảng
echo "\n3. Kiểm tra cấu trúc bảng...\n";
try {
    $columns = DB::select("SHOW COLUMNS FROM notifications");
    $requiredColumns = ['id', 'type', 'title', 'message', 'url', 'reference_id', 'is_read', 'created_at'];
    $existingColumns = array_map(function($col) { return $col->Field; }, $columns);
    
    $missing = array_diff($requiredColumns, $existingColumns);
    if (empty($missing)) {
        echo "   ✓ Tất cả các cột cần thiết đã tồn tại\n";
    } else {
        echo "   ✗ Thiếu các cột: " . implode(', ', $missing) . "\n";
        echo "   → Cần chạy lại migration\n";
    }
} catch (\Exception $e) {
    echo "   ✗ Lỗi khi kiểm tra cấu trúc: " . $e->getMessage() . "\n";
}

// 4. Đếm notifications hiện tại
echo "\n4. Đếm notifications hiện tại...\n";
try {
    $count = Notification::count();
    $unreadCount = Notification::where('is_read', false)->count();
    echo "   - Tổng số: {$count}\n";
    echo "   - Chưa đọc: {$unreadCount}\n";
} catch (\Exception $e) {
    echo "   ✗ Lỗi khi đếm: " . $e->getMessage() . "\n";
}

// 5. Test tạo notification
echo "\n5. Test tạo notification...\n";
try {
    $testNotif = Notification::create([
        'type' => 'test',
        'title' => 'Test Notification',
        'message' => 'Đây là notification test được tạo bởi script fix_notifications.php',
        'url' => '/admin',
        'reference_id' => 0,
        'is_read' => false,
    ]);
    echo "   ✓ Tạo notification thành công! ID: {$testNotif->id}\n";
    
    // Xóa notification test
    $testNotif->delete();
    echo "   ✓ Đã xóa notification test\n";
} catch (\Exception $e) {
    echo "   ✗ Lỗi khi tạo notification: " . $e->getMessage() . "\n";
    echo "   Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

// 6. Kiểm tra model
echo "\n6. Kiểm tra model Notification...\n";
try {
    $model = new Notification();
    echo "   ✓ Model Notification tồn tại\n";
    echo "   - Table: {$model->getTable()}\n";
    echo "   - Fillable: " . implode(', ', $model->getFillable()) . "\n";
} catch (\Exception $e) {
    echo "   ✗ Lỗi khi kiểm tra model: " . $e->getMessage() . "\n";
}

echo "\n=== Kết thúc kiểm tra ===\n";
echo "\nBây giờ hãy:\n";
echo "1. Test tạo đơn hàng hoặc liên hệ từ frontend\n";
echo "2. Kiểm tra admin panel xem có thông báo không\n";
echo "3. Kiểm tra logs trong storage/logs/laravel.log\n";
echo "4. Kiểm tra console trong browser (F12) để xem logs từ NotificationDropdown\n";







